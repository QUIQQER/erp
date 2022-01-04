/**
 * @module package/quiqqer/erp/bin/backend/controls/articles/Article
 * @author www.pcsg.de (Henning Leutz)
 *
 * Freies Produkt
 * - Dieses Produkt kann vom Benutzer komplett selbst bestimmt werden
 *
 * @event onCalc [self]
 * @event onSelect [self]
 * @event onUnSelect [self]
 *
 * @event onDelete [self]
 * @event onRemove [self]
 * @event onDrop [self]
 * @event onEditCustomFields [self] - Fires if the user clicks a custom field
 *
 * @event onSetTitle [self]
 * @event onSetDescription [self]
 * @event onSetPosition [self]
 * @event onSetQuantity [self]
 * @event onSetUnitPrice [self]
 * @event onSetVat [self]
 * @event onSetDiscount [self]
 * @event onEditKeyDown [self, event]
 */
define('package/quiqqer/erp/bin/backend/controls/articles/Article', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'qui/utils/Elements',
    'package/quiqqer/erp/bin/backend/utils/Discount',
    'package/quiqqer/erp/bin/backend/utils/Money',
    'package/quiqqer/currency/bin/Currency',
    'Mustache',
    'Locale',
    'Ajax',
    'Editors',

    'text!package/quiqqer/erp/bin/backend/controls/articles/Article.html',
    'css!package/quiqqer/erp/bin/backend/controls/articles/Article.css'

], function (QUI, QUIControl, QUIButton, QUIConfirm, QUIElements, DiscountUtils, MoneyUtils, Currency, Mustache, QUILocale, QUIAjax, Editors, template) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/articles/Article',

        Binds: [
            '$onEditTitle',
            '$onEditDescription',
            '$onEditQuantity',
            '$onEditQuantityUnit',
            '$onEditArticleNo',
            '$onEditUnitPriceQuantity',
            '$onEditBruttoPrice',
            '$onEditVat',
            '$onEditDiscount',
            '$onEditBruttoDiscount',
            'openDeleteDialog',
            '$onReplaceClick',
            '$editNext',
            'remove',
            'select',
            '$onCustomFieldClick',
            '$sanitizeArticleDescription'
        ],

        options: {
            id          : false,
            articleNo   : '',
            description : '',
            discount    : '-',
            position    : 0,
            price       : 0,
            quantity    : 1,
            quantityUnit: '',
            title       : '',
            unitPrice   : 0,
            vat         : '',
            'class'     : 'QUI\\ERP\\Accounting\\Article',
            params      : false, // mixed value for API Articles
            currency    : false,

            showSelectCheckbox: false,  // select this article via checkbox instead of click

            // Determine article fields that can be edited
            editFields: {
                articleNo          : true,
                titleAndDescription: true,
                quantity           : true,
                quantityUnit       : true,
                unitPrice          : true,
                vat                : true,
                discount           : true
            },

            customFields: {},   // Custom fields (=fields where user can select/input a value)

            User: false,        // special user object (see this.addUser)

            deletable  : true,   // show "delete" button
            replaceable: true,   // show "replace" button
            calcByList : true     // calculate article prices by the associated ArticleList
        },

        initialize: function (options) {
            this.setAttributes(this.__proto__.options); // set the default values
            this.parent(options);

            this.$user = {};
            this.$calculations = {};
            this.$bruttoCalc = {};

            this.$SelectCheckbox = null;
            this.$Position = null;
            this.$Quantity = null;
            this.$UnitPrice = null;
            this.$Price = null;
            this.$VAT = null;
            this.$Total = null;

            this.$Text = null;
            this.$Title = null;
            this.$Description = null;
            this.$Editor = null;
            this.$textIsHtml = false;

            this.$isSelected = false;

            this.$Loader = null;
            this.$created = false;

            // discount
            if (options && "discount" in options) {
                this.setAttribute(
                    'discount',
                    DiscountUtils.parseToString(
                        DiscountUtils.unserialize(options.discount)
                    )
                );
            }
        },

        /**
         * Create the DOMNode element
         *
         * @returns {HTMLDivElement}
         */
        create: function () {
            var self = this;

            this.$Elm = this.parent();
            this.$Elm.addClass('quiqqer-erp-backend-erpArticle');

            var showSelectCheckbox = this.getAttribute('showSelectCheckbox');

            var nl2br = (str, is_xhtml) => {
                var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br ' + '/>' : '<br>'; // Adjust comment to avoid issue on phpjs.org display
                return str.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/gm, '$1' + breakTag + '$2');
            };

            var CustomFields = this.getAttribute('customFields');
            var CustomFieldValues = {};

            for (var [fieldId, FieldData] of Object.entries(CustomFields)) {
                var FieldDataClone = Object.clone(FieldData);

                CustomFieldValues[fieldId] = {
                    title    : FieldDataClone.title,
                    valueText: nl2br(FieldDataClone.valueText)
                };
            }

            this.$Elm.set({
                html      : Mustache.render(template, {
                    showSelectCheckbox: showSelectCheckbox,
                    customFields      : Object.values(CustomFieldValues)
                }),
                'tabindex': -1,
                styles    : {
                    outline: 'none'
                }
            });

            // Make custom fields clickable
            this.$Elm.getElements('.quiqqer-erp-backend-erpArticle-customFields-entry').addEvent(
                'click',
                this.$onCustomFieldClick
            );

            var EditFields = this.getAttribute('editFields');

            if (showSelectCheckbox) {
                this.$SelectCheckbox = this.$Elm.getElement('.quiqqer-erp-backend-erpArticle-selectbox > input');
                this.$SelectCheckbox.addEvent('change', function (event) {
                    if (event.target.checked) {
                        self.select();
                    } else {
                        self.unselect();
                    }
                });
            } else {
                this.$Elm.set('events', {
                    click: this.select
                });
            }

            this.$Position = this.$Elm.getElement('.quiqqer-erp-backend-erpArticle-pos');
            this.$ArticleNo = this.$Elm.getElement('.quiqqer-erp-backend-erpArticle-articleNo');
            this.$Text = this.$Elm.getElement('.quiqqer-erp-backend-erpArticle-text');
            this.$Quantity = this.$Elm.getElement('.quiqqer-erp-backend-erpArticle-quantity');
            this.$QuantityUnit = this.$Elm.getElement('.quiqqer-erp-backend-erpArticle-quantityUnit');
            this.$UnitPrice = this.$Elm.getElement('.quiqqer-erp-backend-erpArticle-unitPrice');
            this.$Price = this.$Elm.getElement('.quiqqer-erp-backend-erpArticle-price');
            this.$VAT = this.$Elm.getElement('.quiqqer-erp-backend-erpArticle-vat');
            this.$Discount = this.$Elm.getElement('.quiqqer-erp-backend-erpArticle-discount');
            this.$Total = this.$Elm.getElement('.quiqqer-erp-backend-erpArticle-total');
            this.$Buttons = this.$Elm.getElement('.quiqqer-erp-backend-erpArticle-buttons');

            if ('articleNo' in EditFields && EditFields.articleNo) {
                this.$ArticleNo.addEvent('click', this.$onEditArticleNo);
            } else {
                this.$ArticleNo.removeClass('cell-editable');
            }

            if ('quantity' in EditFields && EditFields.quantity) {
                this.$Quantity.addEvent('click', this.$onEditQuantity);
            } else {
                this.$Quantity.removeClass('cell-editable');
            }

            if ('unitPrice' in EditFields && EditFields.unitPrice) {
                this.$UnitPrice.addEvent('click', this.$onEditUnitPriceQuantity);
            } else {
                this.$UnitPrice.removeClass('cell-editable');
            }

            if ('vat' in EditFields && EditFields.vat) {
                this.$VAT.addEvent('click', this.$onEditVat);

                // Special VAT cell events
                this.$VAT.addEvent('keydown', function (event) {
                    if (event.key === 'tab') {
                        this.$editNext(event);
                        return;
                    }

                    if (event.key === 'enter') {
                        QUIElements.simulateEvent(event.target, 'click');
                    }
                }.bind(this));

                this.$VAT.addEvent('blur', function (event) {
                    if (event.key === 'tab') {
                        this.$editNext(event);
                    }
                }.bind(this));
            } else {
                this.$VAT.removeClass('cell-editable');
            }

            if ('discount' in EditFields && EditFields.discount) {
                this.$Discount.addEvent('click', this.$onEditDiscount);
            } else {
                this.$Discount.removeClass('cell-editable');
            }

            if ('quantityUnit' in EditFields && EditFields.quantityUnit) {
                this.$QuantityUnit.addEvent('click', this.$onEditQuantityUnit);

                // Special quantity unit cell events
                this.$QuantityUnit.addEvent('keydown', function (event) {
                    if (event.key === 'tab') {
                        this.$editNext(event);
                        return;
                    }

                    if (event.key === 'enter') {
                        QUIElements.simulateEvent(event.target, 'click');
                    }
                }.bind(this));

                this.$QuantityUnit.addEvent('blur', function (event) {
                    if (event.key === 'tab') {
                        this.$editNext(event);
                    }
                }.bind(this));
            } else {
                this.$QuantityUnit.removeClass('cell-editable');
            }

            // brutto stuff
            this.$UnitPriceBrutto = new Element('div', {
                'class': 'quiqqer-erp-backend-erpArticle-unitPrice-brutto cell cell-editable',
            }).inject(this.$UnitPrice, 'after');

            this.$PriceBrutto = new Element('div', {
                'class': 'quiqqer-erp-backend-erpArticle-price-brutto cell'
            }).inject(this.$Price, 'after');

            if ('unitPrice' in EditFields && EditFields.unitPrice) {
                this.$UnitPriceBrutto.addEvent('click', this.$onEditBruttoPrice);
            } else {
                this.$UnitPriceBrutto.removeClass('cell-editable');
            }

            this.$DiscountBrutto = new Element('div', {
                'class': 'quiqqer-erp-backend-erpArticle-discount-brutto cell cell-editable',
            }).inject(this.$Discount, 'after');

            if ('discount' in EditFields && EditFields.discount) {
                this.$DiscountBrutto.addEvent('click', this.$onEditBruttoDiscount);
            } else {
                this.$DiscountBrutto.removeClass('cell-editable');
            }

            this.$TotalBrutto = new Element('div', {
                'class': 'quiqqer-erp-backend-erpArticle-total-brutto cell'
            }).inject(this.$Total, 'after');

            this.$Loader = new Element('div', {
                html  : '<span class="fa fa-spinner fa-spin"></span>',
                styles: {
                    background: '#fff',
                    display   : 'none',
                    left      : 0,
                    padding   : 10,
                    position  : 'absolute',
                    top       : 0,
                    width     : '100%'
                }
            }).inject(this.$Position);

            new Element('span').inject(this.$Position);

            if (this.getAttribute('position')) {
                this.setPosition(this.getAttribute('position'));
            }

            this.$Title = new Element('div', {
                'class': 'quiqqer-erp-backend-erpArticle-text-title cell-editable'
            }).inject(this.$Text);

            this.$Description = new Element('div', {
                'class': 'quiqqer-erp-backend-erpArticle-text-description cell-editable quiqqer-erp-backend-erpArticle__cell_hidden'
            }).inject(this.$Text);

            if ('titleAndDescription' in EditFields && EditFields.titleAndDescription) {
                this.$Title.addEvent('click', this.$onEditTitle);
                this.$Description.addEvent('click', this.$onEditDescription);

                new QUIButton({
                    'class': 'quiqqer-erp-backend-erpArticle-text-btn-editor',
                    title  : QUILocale.get(lg, 'erp.articleList.article.button.editor'),
                    icon   : 'fa fa-edit',
                    events : {
                        onClick: this.$onEditDescription
                    }
                }).inject(this.$Text);
            } else {
                this.$Title.removeClass('cell-editable');
                this.$Description.removeClass('cell-editable');
            }

            this.$Elm.getElements('.cell-editable').set('tabindex', -1);

            this.setArticleNo(this.getAttribute('articleNo'));
            this.setVat(this.getAttribute('vat'));
            this.setTitle(this.getAttribute('title'));
            this.setDescription(this.getAttribute('description'));

            this.setQuantity(this.getAttribute('quantity'));
            this.setUnitPrice(this.getAttribute('unitPrice'));
            this.setDiscount(this.getAttribute('discount'));
            this.setQuantityUnit(this.getAttribute('quantityUnit'));

            if (!this.getAttribute('quantityUnit')) {
                this.$loadDefaultQuantityUnit().catch(function (err) {
                    console.error(err);
                });
            }

            // edit buttons
            if (this.getAttribute('replaceable')) {
                new QUIButton({
                    title : QUILocale.get(lg, 'erp.articleList.article.button.replace'),
                    icon  : 'fa fa-retweet',
                    styles: {
                        'float': 'none'
                    },
                    events: {
                        onClick: this.$onReplaceClick
                    }
                }).inject(this.$Buttons);
            }

            if (this.getAttribute('deletable')) {
                new QUIButton({
                    title : QUILocale.get(lg, 'erp.articleList.article.button.delete'),
                    icon  : 'fa fa-trash',
                    styles: {
                        'float': 'none'
                    },
                    events: {
                        onClick: this.openDeleteDialog
                    }
                }).inject(this.$Buttons);
            }

            this.$created = true;

            // User
            if (this.getAttribute('User')) {
                this.$user = this.getAttribute('User');
            }

            this.calc();

            this.addEvent('onEditKeyDown', function (me, event) {
                if (event.key === 'tab') {
                    this.$editNext(event);
                }
            }.bind(this));

            return this.$Elm;
        },

        /**
         * Deletes the article and destroy the Node
         *
         * @fires onDelete [self]
         * @fires onRemove [self]
         * @fires onDrop [self]
         */
        remove: function () {
            this.fireEvent('delete', [this]);
            this.fireEvent('remove', [this]);
            this.fireEvent('drop', [this]);

            this.destroy();
        },

        /**
         * Trigger the replace event
         */
        $onReplaceClick: function () {
            this.fireEvent('replace', [this]);
        },

        /**
         * Set the user data for the article
         *
         * @param {Object} user
         */
        setUser: function (user) {
            this.$user = user;
        },

        /**
         * Calculates the total price and refresh the display
         *
         * @return {Promise}
         */
        calc: function () {
            var self = this;

            if (!this.$created) {
                return Promise.resolve();
            }

            this.showLoader();

            return this.getCurrencyFormatter().then(function (Formatter) {
                return Promise.all([
                    self.$calc(),
                    Formatter
                ]);
            }).then(function (result) {
                var product = result[0];
                var Formatter = result[1];

                var unitPrice = Formatter.format(product.unitPrice);
                var price = Formatter.format(product.calculated.nettoSubSum);
                var total = Formatter.format(product.calculated.nettoSum);

                var setElement = function (Node, text) {
                    var isInEditMode = Node.getElement('input');

                    if (isInEditMode) {
                        Node.set({
                            title: text
                        });
                        return;
                    }

                    Node.set({
                        html : text,
                        title: text
                    });
                };

                setElement(self.$Total, total);
                setElement(self.$UnitPrice, unitPrice);
                setElement(self.$Price, price);
                setElement(self.$VAT, product.vat + '%');

                if (typeof self.$bruttoCalc !== 'undefined' &&
                    typeof self.$bruttoCalc.display_discount !== 'undefined') {
                    self.$DiscountBrutto.set('html', self.$bruttoCalc.display_discount);
                    self.$DiscountBrutto.set('data-value', self.$bruttoCalc.discount);
                } else {
                    self.$DiscountBrutto.set('html', '-');
                    self.$DiscountBrutto.set('data-value', '-');
                }

                if (typeof self.$bruttoCalc !== 'undefined' &&
                    typeof self.$bruttoCalc.display_sum !== 'undefined') {
                    self.$TotalBrutto.set('html', self.$bruttoCalc.display_sum);
                    self.$TotalBrutto.set('data-value', self.$bruttoCalc.sum);
                }

                if (typeof self.$bruttoCalc !== 'undefined' &&
                    typeof self.$bruttoCalc.display_unitPrice !== 'undefined') {
                    self.$UnitPriceBrutto.set('html', self.$bruttoCalc.display_unitPrice);
                    self.$UnitPriceBrutto.set('data-value', self.$bruttoCalc.unitPrice);
                }

                if (typeof self.$bruttoCalc !== 'undefined' &&
                    typeof self.$bruttoCalc.display_quantity_sum !== 'undefined') {
                    self.$PriceBrutto.set('html', self.$bruttoCalc.display_quantity_sum);
                    self.$PriceBrutto.set('data-value', self.$bruttoCalc.quantity_sum);
                }

                self.hideLoader();
                self.fireEvent('calc', [self]);

                return product;
            });
        },

        /**
         * Calculate the current article
         *
         * @return {Promise}
         */
        $calc: function () {
            var Calc;

            var self       = this,
                attr       = self.getAttributes(),
                pos        = parseInt(attr.position),
                calcByList = false;

            if (this.getAttribute('calcByList') && this.getAttribute('List')) {
                Calc = this.getAttribute('List').$executeCalculation();
                calcByList = true;
            } else {
                Calc = new Promise(function (resolve, reject) {
                    QUIAjax.get('package_quiqqer_erp_ajax_products_calc', resolve, {
                        'package': 'quiqqer/erp',
                        onError  : reject,
                        articles : JSON.encode({
                            articles: [attr]
                        }),
                        user     : JSON.encode(self.$user),
                        currency : self.getAttribute('currency')
                    });
                });
            }

            return Calc.then(function (result) {
                let article;
                let brutto;
                let articles = result.articles;

                if (!calcByList) {
                    article = articles[0];
                    brutto = result.brutto.articles[0];
                } else {
                    article = articles.filter(function (article) {
                        return parseInt(article.position) === pos;
                    })[0];

                    brutto = result.brutto.articles.filter(function (article) {
                        return parseInt(article.position) === pos;
                    })[0];
                }

                self.$calculations = article;
                self.$bruttoCalc = brutto;
                self.fireEvent('calc', [
                    self,
                    result,
                    article
                ]);

                return article;
            });
        },

        /**
         * Return the current calculations
         *
         * @returns {{}|*}
         */
        getCalculations: function () {
            if (!this.$calculations) {
                return {};
            }

            return this.$calculations.calculated;
        },

        /**
         * @returns {*}
         */
        getBruttoCalc: function () {
            return this.$bruttoCalc;
        },

        /**
         * Return the article currency
         *
         * @return {Promise|*}
         */
        getCurrencyFormatter: function () {
            if (this.$Formatter) {
                return Promise.resolve(this.$Formatter);
            }

            // admin format
            if (this.getAttribute('currency')) {
                this.$Formatter = QUILocale.getNumberFormatter({
                    style   : 'currency',
                    currency: this.getAttribute('currency')
                });

                return Promise.resolve(this.$Formatter);
            }


            var self = this;

            return new Promise(function (resolve) {
                Currency.getCurrency().then(function (currency) {
                    self.$Formatter = QUILocale.getNumberFormatter({
                        style   : 'currency',
                        currency: currency.code
                    });

                    resolve(self.$Formatter);
                });
            });
        },

        /**
         * Set the currency to the article
         *
         * @param {String} currency
         */
        setCurrency: function (currency) {
            if (this.getAttribute('currency') === currency) {
                return;
            }

            this.setAttribute('currency', currency);
            this.$Formatter = null;
        },

        /**
         * Set the product title
         *
         * @param {String} title
         */
        setTitle: function (title) {
            this.setAttribute('title', title);
            this.$Title.set('html', title);

            if (title === '') {
                this.$Title.set('html', '&nbsp;');
            }

            this.fireEvent('setTitle', [this]);
        },

        /**
         * Set the product description
         *
         * @param {String} description
         */
        setDescription: function (description) {
            this.setAttribute('description', description);
            this.$Description.set('html', description);

            if (description === '') {
                this.$Description.addClass('quiqqer-erp-backend-erpArticle__cell_hidden');
            } else {
                this.$Description.removeClass('quiqqer-erp-backend-erpArticle__cell_hidden');

                // If title / description were edited via WYSIWYG editor -> open editor on click
                this.$Title.removeEvent('click', this.$onEditTitle);
                this.$Title.addEvent('click', this.$onEditDescription);
            }

            this.fireEvent('setDescription', [this]);
        },

        /**
         * Set the Article-No
         *
         * @param {String} articleNo
         */
        setArticleNo: function (articleNo) {
            this.setAttribute('articleNo', articleNo);
            this.$ArticleNo.set('html', articleNo);

            if (articleNo === '') {
                this.$ArticleNo.set('html', '&nbsp;');
            }

            this.fireEvent('setArticleNo', [this]);
        },

        /**
         * Set the product position
         *
         * @param {Number} pos
         */
        setPosition: function (pos) {
            this.setAttribute('position', parseInt(pos));

            if (this.$Position) {
                this.$Position.getChildren('span').set('html', this.getAttribute('position'));
            }

            this.fireEvent('setPosition', [this]);
        },

        /**
         * Set the product quantity
         *
         * @param {Number} quantity
         * @return {Promise}
         */
        setQuantity: function (quantity) {
            this.setAttribute('quantity', parseFloat(quantity));

            if (this.$Quantity) {
                this.$Quantity.set('html', this.getAttribute('quantity'));
            }

            this.fireEvent('setQuantity', [this]);

            return this.calc();
        },

        /**
         * Set the unit of the quantity
         *
         * @param {Object} quantityUnit - {id:'piece', title:''}
         */
        setQuantityUnit: function (quantityUnit) {
            if (typeof quantityUnit.id === 'undefined') {
                return;
            }

            this.setAttribute('quantityUnit', quantityUnit);

            if (this.$QuantityUnit) {
                this.$QuantityUnit.set('html', this.getAttribute('quantityUnit').title);
            }

            this.fireEvent('setQuantityUnit', [
                this,
                quantityUnit
            ]);
        },

        /**
         * Set the product unit price
         *
         * @param {Number} price
         * @return {Promise}
         */
        setUnitPrice: function (price) {
            this.setAttribute('unitPrice', parseFloat(price));

            if (this.$UnitPrice) {
                this.$UnitPrice.set('html', this.getAttribute('unitPrice'));
            }

            this.fireEvent('setUnitPrice', [this]);

            return this.calc();
        },

        /**
         * Set the product unit price
         *
         * @param {Number|String} vat
         * @return {Promise}
         */
        setVat: function (vat) {
            if (vat === '-' || vat === '') {
                this.setAttribute('vat', '');
                this.$VAT.set('html', '-');

                return this.calc();
            }

            vat = parseInt(vat);

            if (vat > 100 || vat < 0) {
                return Promise.resolve();
            }

            this.setAttribute('vat', vat);

            if (this.$VAT) {
                vat = this.getAttribute('vat');
                vat = vat + '%';

                this.$VAT.set('html', vat);
            }

            this.fireEvent('setVat', [this]);

            return this.calc();
        },

        /**
         * Set the discount
         *
         * @param {String|Number} discount - 100 = 100€, 100€ = 100€ or 10% =  calculation
         */
        setDiscount: function (discount) {
            var self  = this,
                value = '',
                type  = '';

            if (discount === '' || !discount) {
                discount = '-';
            }

            if (typeOf(discount) === 'string' && discount.indexOf('%') !== -1) {
                type = '%';
            }

            var Prom;

            if (discount && type === '%') {
                Prom = Promise.resolve(discount);
            } else if (discount) {
                Prom = MoneyUtils.validatePrice(discount);
            } else {
                Prom = Promise.resolve('-');
            }

            return Prom.then(function (discount) {
                if (discount && type === '%') {
                    discount = (discount).toString().replace(/\%/g, '') + type;
                    value = discount;
                } else if (discount) {
                    value = self.$Formatter.format(discount) + type;
                } else {
                    value = '-';
                }

                self.fireEvent('setDiscount', [self]);

                self.setAttribute('discount', discount);
                self.$Discount.set('html', value);
            }).then(this.calc.bind(this));
        },

        /**
         * Show the loader
         */
        showLoader: function () {
            this.$Loader.setStyle('display', null);
        },

        /**
         * Hide the loader
         */
        hideLoader: function () {
            this.$Loader.setStyle('display', 'none');
        },

        /**
         * select the article
         */
        select: function () {
            if (!this.$Elm) {
                return;
            }

            if (this.$Elm.hasClass('quiqqer-erp-backend-erpArticle-select')) {
                return;
            }

            this.$Elm.addClass('quiqqer-erp-backend-erpArticle-select');

            if (this.$SelectCheckbox) {
                this.$SelectCheckbox.checked = true;
            }

            this.$isSelected = true;

            this.fireEvent('select', [this]);
        },

        /**
         * unselect the article
         */
        unselect: function () {
            if (!this.$Elm) {
                return;
            }

            this.$Elm.removeClass('quiqqer-erp-backend-erpArticle-select');

            if (this.$SelectCheckbox) {
                this.$SelectCheckbox.checked = false;
            }

            this.$isSelected = false;

            this.fireEvent('unSelect', [this]);
        },

        /**
         * @return {boolean}
         */
        isSelected: function () {
            return this.$isSelected;
        },

        /**
         * Dialogs
         */

        /**
         * Opens the delete dialog
         */
        openDeleteDialog: function () {
            new QUIConfirm({
                icon       : 'fa fa-trash',
                texticon   : 'fa fa-trash',
                title      : QUILocale.get(lg, 'dialog.delete.article.title'),
                information: QUILocale.get(lg, 'dialog.delete.article.information'),
                text       : QUILocale.get(lg, 'dialog.delete.article.text'),
                maxHeight  : 400,
                maxWidth   : 600,
                events     : {
                    onSubmit: this.remove.bind(this)
                }
            }).open();
        },

        /**
         * edit event methods
         */

        /**
         * event : on title edit
         */
        $onEditTitle: function () {
            this.$createEditField(
                this.$Title,
                this.getAttribute('title')
            ).then(function (value) {
                this.setTitle(value);
            }.bind(this));
        },

        /**
         * event : on description edit
         */
        $onEditDescription: function () {
            if (this.$Editor) {
                return;
            }

            var self = this;

            new QUIConfirm({
                title    : QUILocale.get(lg, 'dialog.edit.description.title', {
                    articleNo   : this.getAttribute('articleNo'),
                    articleTitle: this.getAttribute('title')
                }),
                icon     : 'fa fa-edit',
                maxHeight: 600,
                maxWidth : 800,
                events   : {
                    onOpen: function (Win) {
                        Win.Loader.show();

                        var Content = Win.getContent();

                        Content.addClass(
                            'quiqqer-erp-dialog-edit-article-description'
                        );

                        Content.set({
                            html: '' +
                                  '<label><input type="text" name="title" /></label>' +
                                  '<div class="quiqqer-erp-dialog-edit-article-description-editor"></div>'
                        });

                        var Title = Content.getElement('[name="title"]');
                        var EditorContainer = Content.getElement(
                            '.quiqqer-erp-dialog-edit-article-description-editor'
                        );

                        Title.set('value', self.getAttribute('title'));
                        Title.set('placeholder', QUILocale.get('quiqqer/system', 'title'));
                        Title.focus();

                        Editors.getEditor(null).then(function (Editor) {
                            self.$Editor = Editor;

                            // minimal toolbar
                            self.$Editor.setAttribute('buttons', {
                                lines: [
                                    [
                                        [
                                            {
                                                type  : "button",
                                                button: "Source"
                                            },
                                            {
                                                type: "separator"
                                            },
                                            {
                                                type  : "button",
                                                button: "Bold"
                                            },
                                            {
                                                type  : "button",
                                                button: "Italic"
                                            },
                                            {
                                                type  : "button",
                                                button: "Underline"
                                            },
                                            {
                                                type: "separator"
                                            },
                                            {
                                                type  : "button",
                                                button: "FontSize"
                                            },
                                            {
                                                type: "separator"
                                            },
                                            {
                                                type  : "button",
                                                button: "RemoveFormat"
                                            },
                                            {
                                                type: "separator"
                                            },
                                            {
                                                type  : "button",
                                                button: "NumberedList"
                                            },
                                            {
                                                type  : "button",
                                                button: "BulletedList"
                                            }
                                        ]
                                    ]
                                ]
                            });

                            self.$Editor.addEvent('onLoaded', function () {
                                self.$Editor.switchToWYSIWYG();
                                self.$Editor.showToolbar();
                                self.$Editor.setContent(self.getAttribute('description'));

                                Win.Loader.hide();
                            });

                            self.$Editor.inject(EditorContainer);
                            self.$Editor.setHeight(340);
                        });

                        Title.addEvent('keyup', function (event) {
                            if (event.key === 'enter') {
                                Win.submit();
                            }
                        });
                    },

                    onSubmit: function (Win) {
                        var description = self.$Editor.getContent();

                        Win.Loader.show();

                        self.$sanitizeArticleDescription(description).then((sanitizedDescription) => {
                            self.setDescription(sanitizedDescription);
                            self.setTitle(Win.getContent().getElement('[name="title"]').value);

                            var NextEditCell = self.$Text.getNext('.cell-editable');

                            if (NextEditCell) {
                                QUIElements.simulateEvent(self.$Text.getNext('.cell-editable'), 'click');
                            }

                            Win.Loader.hide();
                        });
                    },

                    onClose: function () {
                        self.$Editor.destroy();
                        self.$Editor = null;
                    }
                }
            }).open();
        },

        /**
         * event: on Article-Number edit
         */
        $onEditArticleNo: function () {
            this.$createEditField(
                this.$ArticleNo,
                this.getAttribute('articleNo')
            ).then(function (value) {
                this.setArticleNo(value);
            }.bind(this));
        },

        /**
         * event : on quantity edit
         */
        $onEditQuantity: function () {
            this.$createEditField(
                this.$Quantity,
                this.getAttribute('quantity'),
                'number'
            ).then(function (value) {
                this.setQuantity(value);
            }.bind(this));
        },

        /**
         * Edit quantity unit
         */
        $onEditQuantityUnit: function () {
            var self = this;

            require([
                'package/quiqqer/erp/bin/backend/controls/articles/product/QuantityUnitWindow'
            ], function (QuantityUnitWindow) {
                new QuantityUnitWindow({
                    events: {
                        onSubmit: function (Win, value, title) {
                            self.setQuantityUnit({
                                id   : value,
                                title: title
                            });

                            QUIElements.simulateEvent(
                                self.$Elm.getElement('.quiqqer-erp-backend-erpArticle-quantityUnit').getNext('.cell-editable'),
                                'click'
                            );
                        }
                    }
                }).open();
            });
        },

        /**
         * event : on quantity edit
         */
        $onEditUnitPriceQuantity: function () {
            var self = this;

            this.$createEditField(
                this.$UnitPrice,
                this.getAttribute('unitPrice'),
                'number'
            ).then(function (value) {
                self.setUnitPrice(value);
            });
        },

        /**
         * event : on brutto price edit
         */
        $onEditBruttoPrice: function () {
            var self = this;

            this.$createEditField(
                this.$UnitPriceBrutto,
                this.$UnitPriceBrutto.get('data-value'),
                'number'
            ).then(function (value) {
                return self.getNettoPrice(value, false);
            }).then(function (value) {
                self.setUnitPrice(value);
            });
        },

        /**
         * event: on edit VAT
         */
        $onEditVat: function () {
            var self = this;

            require([
                'package/quiqqer/tax/bin/controls/taxList/AvailableTaxListWindow'
            ], function (AvailableTaxListWindow) {
                new AvailableTaxListWindow({
                    events: {
                        onSubmit: function (Win, value) {
                            self.setVat(value);

                            QUIElements.simulateEvent(
                                self.$Elm.getElement('.quiqqer-erp-backend-erpArticle-vat').getNext('.cell-editable'),
                                'click'
                            );
                        }
                    }
                }).open();
            });
        },

        /**
         * event: on edit discount
         */
        $onEditDiscount: function () {
            var discount = this.getAttribute('discount');

            if (discount === '-' || discount === false || !discount) {
                discount = '';
            } else if (!discount.toString().match('%')) {
                discount = parseFloat(discount);
            }

            this.$createEditField(
                this.$Discount,
                discount
            ).then(function (value) {
                this.setDiscount(value);
            }.bind(this));
        },

        /**
         * event: on brutto edit discount
         */
        $onEditBruttoDiscount: function () {
            var self     = this,
                discount = this.$DiscountBrutto.get('data-value');

            if (discount === '-' || discount === false || !discount) {
                discount = '';
            } else if (!discount.toString().match('%')) {
                discount = parseFloat(discount);
            }

            this.$createEditField(
                this.$DiscountBrutto,
                discount
            ).then(function (value) {
                if (value.match('%')) {
                    return self.setDiscount(value);
                }

                if (parseFloat(value) === 0) {
                    return self.setDiscount(0);
                }

                return self.getNettoPrice(value).then(function (value) {
                    self.setDiscount(value);
                });
            });
        },

        /**
         * Edit custom fields
         */
        $onCustomFieldClick: function () {
            this.fireEvent('editCustomFields', [this]);
        },

        /**
         * Creates a input field to edt the product field value
         *
         * @param {HTMLDivElement} Container
         * @param {String} [value] - preselected value
         * @param {String} [type] - edit input type
         * @param {Object} [inputAttributes] - input attributes
         * @returns {Promise}
         */
        $createEditField: function (Container, value, type, inputAttributes) {
            var self = this;

            type = type || 'text';

            return new Promise(function (resolve) {
                var Edit = new Element('input', {
                    type  : type,
                    value : value,
                    styles: {
                        border    : 0,
                        left      : 0,
                        lineHeight: 20,
                        textAlign : 'right',
                        padding   : 5,
                        margin    : 5,
                        position  : 'absolute',
                        top       : 0,
                        width     : 'calc(100% - 10px)'
                    }
                }).inject(Container);

                if (Container === self.$Title) {
                    Edit.setStyle('top', -10);
                }

                if (type === 'number') {
                    Edit.set('step', 'any');
                }

                if (typeof inputAttributes !== 'undefined') {
                    Edit.set(inputAttributes);
                }

                Edit.focus();
                Edit.select();

                var onFinish = function () {
                    Edit.destroy();
                    resolve(Edit.value);
                };

                Edit.addEvents({
                    click: function (event) {
                        event.stop();
                    },

                    keydown: function (event) {
                        self.fireEvent('editKeyDown', [
                            self,
                            event
                        ]);

                        if (event.key === 'enter') {
                            self.$editNext(event);
                            return;
                        }

                        if (event.key === 'esc') {
                            onFinish();
                        }
                    },

                    blur: onFinish
                });
            });
        },

        /**
         * Opens the next edit field
         *
         * @param event
         */
        $editNext: function (event) {
            var Cell = event.target;

            if (!Cell.hasClass('cell')) {
                Cell = Cell.getParent('.cell');
            }

            if (!Cell) {
                return;
            }

            var Next, Article, NextArticle, PreviousArticle;

            if (event.shift) {
                Next = Cell.getPrevious('.cell-editable');

                if (Next && Next.getStyle('display') === 'none') {
                    event.target = Next;
                    this.$editNext(event);
                    return;
                }

                if (!Next) {
                    // previous row
                    Article = Cell.getParent('.article');
                    PreviousArticle = Article.getPrevious('.article');

                    if (!PreviousArticle) {
                        PreviousArticle = Cell.getParent('.quiqqer-erp-backend-erpItems-items')
                                              .getLast('.article');
                    }

                    Next = PreviousArticle.getLast('.cell-editable');
                }
            } else {
                if (Cell.hasClass('quiqqer-erp-backend-erpArticle-articleNo')) {
                    Next = Cell.getParent().getElement('.quiqqer-erp-backend-erpArticle-text-title');
                } else {
                    Next = Cell.getNext('.cell-editable');
                }

                if (Next && Next.getStyle('display') === 'none') {
                    event.target = Next;
                    this.$editNext(event);
                    return;
                }

                if (!Next) {
                    // next row
                    Article = Cell.getParent('.article');
                    NextArticle = Article.getNext('.article');

                    if (!NextArticle) {
                        NextArticle = Cell.getParent('.quiqqer-erp-backend-erpItems-items')
                                          .getElement('.article');
                    }

                    Next = NextArticle.getElement('.cell-editable');
                }
            }

            if (Next.hasClass('quiqqer-erp-backend-erpArticle-vat') ||
                Next.hasClass('quiqqer-erp-backend-erpArticle-quantityUnit')) {
                event.stop();
                Next.focus();
                return;
            }

            if (Next) {
                event.stop();
                QUIElements.simulateEvent(Next, 'click');
            }
        },

        /**
         *
         * @return {Promise}
         */
        $loadDefaultQuantityUnit: function () {
            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_erp_ajax_products_getQuantityUnitList', function (unitList) {
                    var i, title, entry;
                    var current = QUILocale.getCurrent();

                    for (i in unitList) {
                        if (!unitList.hasOwnProperty(i)) {
                            continue;
                        }

                        if (unitList[i].default) {
                            break;
                        }
                    }

                    entry = unitList[i];

                    if (typeof entry.title[current] !== 'undefined') {
                        title = entry.title[current];
                    } else {
                        title = entry.title[Object.keys(entry.title)[0]];
                    }

                    var result = {
                        id   : i,
                        title: title
                    };

                    self.setQuantityUnit(result);
                    resolve(result);
                }, {
                    'package': 'quiqqer/erp'
                });
            });
        },

        /**
         * Get netto price
         *
         * @param value
         * @param formatted
         * @return {Promise}
         */
        getNettoPrice: function (value, formatted) {
            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_erp_ajax_calcNettoPrice', resolve, {
                    'package': 'quiqqer/erp',
                    price    : value,
                    vat      : self.getAttribute('vat'),
                    formatted: formatted ? 1 : 0
                });
            });
        },

        /**
         * Get brutto price
         *
         * @param value
         * @param formatted
         * @return {Promise}
         */
        getBruttoPrice: function (value, formatted) {
            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_erp_ajax_calcBruttoPrice', resolve, {
                    'package': 'quiqqer/erp',
                    price    : value,
                    vat      : self.getAttribute('vat'),
                    formatted: formatted ? 1 : 0
                });
            });
        },

        /**
         * Filter article description HTML
         *
         * @param {String} description
         * @return {Promise}
         */
        $sanitizeArticleDescription: function (description) {
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_erp_ajax_utils_sanitizeArticleDescription', resolve, {
                    'package'  : 'quiqqer/erp',
                    description: description,
                    onError    : reject
                });
            });
        }
    });
});
