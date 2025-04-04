/* jshint proto: true */

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

], function (
    QUI,
    QUIControl,
    QUIButton,
    QUIConfirm,
    QUIElements,
    DiscountUtils,
    MoneyUtils,
    Currency,
    Mustache,
    QUILocale,
    QUIAjax,
    Editors,
    template
) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/articles/Article',

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
            id: false,
            articleNo: '',
            description: '',
            discount: '-',
            position: 0,
            price: 0,
            quantity: 1,
            quantityUnit: '',
            title: '',
            unitPrice: 0,
            vat: '',
            'class': 'QUI\\ERP\\Accounting\\Article',
            params: false, // mixed value for API Articles
            currency: false,
            productSetParentUuid: null,
            uuid: null,

            showSelectCheckbox: false,  // select this article via checkbox instead of click

            // Determine article fields that can be edited
            editFields: {
                articleNo: true,
                titleAndDescription: true,
                quantity: true,
                quantityUnit: true,
                unitPrice: true,
                vat: true,
                discount: true
            },

            customFields: {},   // Custom fields (=fields where user can select/input a value)

            User: false,        // special user object (see this.addUser)

            deletable: true,     // show "delete" button
            replaceable: true,   // show "replace" button
            calcByList: true     // calculate article prices by the associated ArticleList
        },

        initialize: function (options) {
            this.setAttributes(this.__proto__.options); // set the default values
            this.parent(options);

            this.$user = {};
            this.$calculate = true; // calculation is running or not
            this.$calculations = {};
            this.$bruttoCalc = {};

            if (typeof options !== 'undefined' && typeof options.calculated !== 'undefined') {
                this.$calculations = options.calculated;

                if (typeof this.$calculations.bruttoCalculated !== 'undefined') {
                    this.$bruttoCalc = this.$calculations.bruttoCalculated;
                }
            }

            if (typeof options !== 'undefined' && typeof options.calculate !== 'undefined') {
                this.$calculate = options.calculate;
            }

            if (typeof this.$calculations.nettoPriceNotRounded !== 'undefined' && this.$calculations.nettoPriceNotRounded) {
                this.setAttribute('unitPrice', this.$calculations.nettoPriceNotRounded);
            }

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
            if (options && 'discount' in options) {
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
            const self = this;

            this.$Elm = this.parent();
            this.$Elm.addClass('quiqqer-erp-backend-erpArticle');

            const showSelectCheckbox = this.getAttribute('showSelectCheckbox');

            const nl2br = (str, is_xhtml) => {
                const breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br ' + '/>' : '<br>'; // Adjust comment to avoid issue on phpjs.org display
                return str.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/gm, '$1' + breakTag + '$2');
            };

            const CustomFields = this.getAttribute('customFields');
            const CustomFieldValues = {};

            for (let [fieldId, FieldData] of Object.entries(CustomFields)) {
                let FieldDataClone = Object.clone(FieldData);

                CustomFieldValues[fieldId] = {
                    title: FieldDataClone.title,
                    valueText: nl2br(FieldDataClone.valueText)
                };
            }

            this.$Elm.set({
                html: Mustache.render(template, {
                    showSelectCheckbox: showSelectCheckbox,
                    customFields: Object.values(CustomFieldValues),
                    buttonReplace: QUILocale.get(lg, 'articleList.article.button.replace'),
                    buttonDelete: QUILocale.get(lg, 'articleList.article.button.delete')
                }),
                'tabindex': -1,
                styles: {
                    outline: 'none'
                }
            });

            // Make custom fields clickable
            this.$Elm.getElements('.quiqqer-erp-backend-erpArticle-customFields-entry').addEvent(
                'click',
                this.$onCustomFieldClick
            );

            const EditFields = this.getAttribute('editFields');

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

            this.$ButtonReplace = this.$Elm.getElement('button[name="replace"]');
            this.$ButtonDelete = this.$Elm.getElement('button[name="delete"]');

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
                'class': 'quiqqer-erp-backend-erpArticle-unitPrice-brutto cell cell-editable'
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
                'class': 'quiqqer-erp-backend-erpArticle-discount-brutto cell cell-editable'
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
                html: '<span class="fa fa-spinner fa-spin"></span>',
                styles: {
                    background: '#fff',
                    display: 'none',
                    left: 0,
                    padding: 10,
                    position: 'absolute',
                    top: 0,
                    width: '100%'
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
                    title: QUILocale.get(lg, 'erp.articleList.article.button.editor'),
                    icon: 'fa fa-edit',
                    events: {
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
            this.setUnitPrice(this.getAttribute('unitPrice'));

            this.setQuantity(this.getAttribute('quantity'));
            this.setDiscount(this.getAttribute('discount'));
            this.setQuantityUnit(this.getAttribute('quantityUnit'));

            if (!this.getAttribute('quantityUnit')) {
                this.$loadDefaultQuantityUnit().catch(function (err) {
                    console.error(err);
                });
            }

            // edit buttons
            if (this.getAttribute('replaceable')) {
                this.$ButtonReplace.addEvent('click', this.$onReplaceClick);
            } else {
                this.$ButtonReplace.setStyle('display', 'none');
            }

            if (this.getAttribute('deletable')) {
                this.$ButtonDelete.addEvent('click', this.openDeleteDialog);
            } else {
                this.$ButtonDelete.setStyle('display', 'none');
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
            if (!this.$created) {
                return Promise.resolve();
            }

            this.showLoader();

            return this.getCurrencyFormatter().then((Formatter) => {
                return Promise.all([
                    this.$calc(),
                    Formatter
                ]);
            }).then((result) => {
                const product = result[0];
                const Formatter = result[1];

                const unitPrice = Formatter.format(product.unitPrice);
                const price = Formatter.format(product.calculated.nettoSubSum);
                const total = Formatter.format(product.calculated.nettoSum);

                const setElement = function (Node, text) {
                    const isInEditMode = Node.getElement('input');

                    if (isInEditMode) {
                        Node.set({
                            title: text
                        });
                        return;
                    }

                    Node.set({
                        html: text,
                        title: text
                    });
                };

                setElement(this.$Total, total);
                setElement(this.$UnitPrice, unitPrice);
                setElement(this.$Price, price);
                setElement(this.$VAT, product.vat + '%');

                if (typeof this.$bruttoCalc !== 'undefined' &&
                    typeof this.$bruttoCalc.display_discount !== 'undefined') {
                    this.$DiscountBrutto.set('html', this.$bruttoCalc.display_discount);
                    this.$DiscountBrutto.set('data-value', this.$bruttoCalc.discount);
                } else {
                    this.$DiscountBrutto.set('html', '-');
                    this.$DiscountBrutto.set('data-value', '-');
                }

                if (typeof this.$bruttoCalc !== 'undefined' &&
                    typeof this.$bruttoCalc.display_sum !== 'undefined') {
                    this.$TotalBrutto.set('html', this.$bruttoCalc.display_sum);
                    this.$TotalBrutto.set('data-value', this.$bruttoCalc.sum);
                }

                if (typeof this.$bruttoCalc !== 'undefined' &&
                    typeof this.$bruttoCalc.display_unitPrice !== 'undefined') {
                    this.$UnitPriceBrutto.set('html', this.$bruttoCalc.display_unitPrice);
                    //this.$UnitPriceBrutto.set('data-value', this.$bruttoCalc.unitPrice);
                    this.$UnitPriceBrutto.set('data-value', this.$bruttoCalc.calculated.basisPrice);
                }

                if (typeof this.$bruttoCalc !== 'undefined' &&
                    typeof this.$bruttoCalc.display_quantity_sum !== 'undefined') {
                    this.$PriceBrutto.set('html', this.$bruttoCalc.display_quantity_sum);
                    this.$PriceBrutto.set('data-value', this.$bruttoCalc.quantity_sum);
                }

                this.hideLoader();

                if (!this.$calculate) {
                    this.fireEvent('calc', [this]);
                }

                return product;
            });
        },

        /**
         * Calculate the current article
         *
         * @return {Promise}
         */
        $calc: function () {
            if (!this.$calculate) {
                return Promise.resolve({
                    vat: this.getAttribute('vat'),
                    unitPrice: this.getAttribute('unitPrice'),

                    id: this.getAttribute('id'),
                    articleNo: this.getAttribute('articleNo'),
                    description: this.getAttribute('description'),
                    discount: this.getAttribute('discount'),
                    position: this.getAttribute('position'),
                    price: this.getAttribute('price'),
                    quantity: this.getAttribute('quantity'),
                    quantityUnit: this.getAttribute('quantityUnit'),
                    title: this.getAttribute('title'),

                    calculated: this.$calculations
                });
            }

            let Calc;

            const attr = this.getAttributes(),
                pos = parseInt(attr.position);

            let calcByList = false;

            if (this.getAttribute('calcByList') && this.getAttribute('List')) {
                Calc = this.getAttribute('List').$executeCalculation();
                calcByList = true;
            } else {
                Calc = new Promise((resolve, reject) => {
                    QUIAjax.get('package_quiqqer_erp_ajax_products_calc', resolve, {
                        'package': 'quiqqer/erp',
                        onError: reject,
                        articles: JSON.encode({
                            articles: [attr]
                        }),
                        user: JSON.encode(this.$user),
                        currency: this.getAttribute('currency')
                    });
                });
            }

            return Calc.then((result) => {
                let articleList;
                let brutto;
                let articles = [];

                if (!result || typeof result.articles === 'undefined') {
                    brutto = !this.getAttribute('List').$Switch.getStatus();
                } else {
                    articles = result.articles;

                    if (!calcByList) {
                        articleList = articles[0];
                        brutto = result.brutto.articles[0];
                    } else {
                        articleList = articles.filter(function (article) {
                            return parseInt(article.position) === pos;
                        })[0];

                        brutto = result.brutto.articles.filter(function (article) {
                            return parseInt(article.position) === pos;
                        })[0];
                    }
                }

                this.$calculations = articleList;
                this.$bruttoCalc = brutto;
                this.fireEvent('calc', [
                    this,
                    result,
                    articleList
                ]);

                return articleList;
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

        setCalculations: function (calculations) {
            this.$calculations = calculations;
        },

        disableCalculations: function () {
            this.$calculate = false;
            this.$calculations.calculate = 0;
        },

        enableCalculations: function () {
            this.$calculate = true;

            if (typeof this.$calculations.calculate !== 'undefined') {
                delete this.$calculations.calculate;
            }
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
            return new Promise((resolve) => {
                let currency = null;

                if (this.getAttribute('currency')) {
                    currency = this.getAttribute('currency');
                }

                Currency.getCurrency(currency).then((currencyResult) => {
                    this.$Formatter = QUILocale.getNumberFormatter({
                        style: 'currency',
                        currency: currencyResult.code,
                        minimumFractionDigits: currencyResult.precision,
                        maximumFractionDigits: currencyResult.precision
                    });

                    resolve(this.$Formatter);
                }).catch((err) => {
                    console.error(err);

                    this.$Formatter = QUILocale.getNumberFormatter({
                        style: 'currency',
                        currency: 'EUR',
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });

                    resolve(this.$Formatter);
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
            const self = this;
            let value = '',
                type = '';

            if (discount === '' || !discount) {
                discount = '-';
            }

            if (typeOf(discount) === 'string' && discount.indexOf('%') !== -1) {
                type = '%';
            }

            let Prom;

            if (discount && type === '%') {
                Prom = Promise.resolve(discount);
            } else {
                if (discount) {
                    Prom = MoneyUtils.validatePrice(discount);
                } else {
                    Prom = Promise.resolve('-');
                }
            }

            return Prom.then(function (discountResult) {
                if (discountResult && type === '%') {
                    discountResult = (discountResult).toString().replace(/\%/g, '') + type;
                    value = discountResult;
                } else {
                    if (discountResult) {
                        value = self.$Formatter.format(discountResult) + type;
                    } else {
                        value = '-';
                    }
                }

                self.fireEvent('setDiscount', [self]);

                self.setAttribute('discount', discountResult);
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
                icon: 'fa fa-trash',
                texticon: 'fa fa-trash',
                title: QUILocale.get(lg, 'dialog.delete.article.title'),
                information: QUILocale.get(lg, 'dialog.delete.article.information'),
                text: QUILocale.get(lg, 'dialog.delete.article.text'),
                maxHeight: 400,
                maxWidth: 600,
                events: {
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

            const self = this;

            new QUIConfirm({
                title: QUILocale.get(lg, 'dialog.edit.description.title', {
                    articleNo: this.getAttribute('articleNo'),
                    articleTitle: this.getAttribute('title')
                }),
                icon: 'fa fa-edit',
                maxHeight: 600,
                maxWidth: 800,
                events: {
                    onOpen: function (Win) {
                        Win.Loader.show();

                        const Content = Win.getContent();

                        Content.addClass(
                            'quiqqer-erp-dialog-edit-article-description'
                        );

                        Content.set({
                            html: '' +
                                '<label><input type="text" name="title" /></label>' +
                                '<div class="quiqqer-erp-dialog-edit-article-description-editor"></div>'
                        });

                        const Title = Content.getElement('[name="title"]');
                        const EditorContainer = Content.getElement(
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
                                                type: 'button',
                                                button: 'Source'
                                            },
                                            {
                                                type: 'separator'
                                            },
                                            {
                                                type: 'button',
                                                button: 'Bold'
                                            },
                                            {
                                                type: 'button',
                                                button: 'Italic'
                                            },
                                            {
                                                type: 'button',
                                                button: 'Underline'
                                            },
                                            {
                                                type: 'separator'
                                            },
                                            {
                                                type: 'button',
                                                button: 'FontSize'
                                            },
                                            {
                                                type: 'separator'
                                            },
                                            {
                                                type: 'button',
                                                button: 'RemoveFormat'
                                            },
                                            {
                                                type: 'separator'
                                            },
                                            {
                                                type: 'button',
                                                button: 'NumberedList'
                                            },
                                            {
                                                type: 'button',
                                                button: 'BulletedList'
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
                        const description = self.$Editor.getContent();

                        Win.Loader.show();

                        self.$sanitizeArticleDescription(description).then((sanitizedDescription) => {
                            self.setDescription(sanitizedDescription);
                            self.setTitle(Win.getContent().getElement('[name="title"]').value);

                            const NextEditCell = self.$Text.getNext('.cell-editable');

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
            const self = this;

            require([
                'package/quiqqer/erp/bin/backend/controls/articles/product/QuantityUnitWindow'
            ], function (QuantityUnitWindow) {
                new QuantityUnitWindow({
                    events: {
                        onSubmit: function (Win, value, title) {
                            self.setQuantityUnit({
                                id: value,
                                title: title
                            });

                            QUIElements.simulateEvent(
                                self.$Elm.getElement('.quiqqer-erp-backend-erpArticle-quantityUnit').getNext(
                                    '.cell-editable'),
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
            const self = this;

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
            const self = this;

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
            const self = this;

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
            let discount = this.getAttribute('discount');

            if (discount === '-' || discount === false || !discount) {
                discount = '';
            } else {
                if (!discount.toString().match('%')) {
                    discount = parseFloat(discount);
                }
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
            const self = this;
            let discount = this.$DiscountBrutto.get('data-value');

            if (discount === '-' || discount === false || !discount) {
                discount = '';
            } else {
                if (!discount.toString().match('%')) {
                    discount = parseFloat(discount);
                }
            }

            this.$createEditField(this.$DiscountBrutto, discount).then(function (value) {
                if (value.match('%')) {
                    return self.setDiscount(value);
                }

                if (parseFloat(value) === 0) {
                    return self.setDiscount(0);
                }

                return self.getNettoPrice(value).then(function (nettoValue) {
                    self.setDiscount(nettoValue);
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
            const self = this;

            type = type || 'text';

            return new Promise(function (resolve) {
                const Edit = new Element('input', {
                    type: type,
                    value: value,
                    styles: {
                        border: 0,
                        left: 0,
                        lineHeight: 20,
                        textAlign: 'right',
                        padding: 5,
                        margin: 5,
                        position: 'absolute',
                        top: 0,
                        width: 'calc(100% - 10px)'
                    }
                }).inject(Container);

                if (typeOf(self) === 'package/quiqqer/erp/bin/backend/controls/articles/Text') {
                    Edit.setStyle('textAlign', 'left');
                }

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

                const onFinish = function () {
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
            let Cell = event.target;

            if (!Cell.hasClass('cell')) {
                Cell = Cell.getParent('.cell');
            }

            if (!Cell) {
                return;
            }

            let Next, Article, NextArticle, PreviousArticle;

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
                        PreviousArticle = Cell.getParent('.quiqqer-erp-backend-erpItems-items').getLast('.article');
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
                        NextArticle = Cell.getParent('.quiqqer-erp-backend-erpItems-items').getElement('.article');
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
            const self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_erp_ajax_products_getQuantityUnitList', function (unitList) {
                    let i, title, entry;
                    let current = QUILocale.getCurrent();

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

                    const result = {
                        id: i,
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
            const self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_erp_ajax_calcNettoPrice', resolve, {
                    'package': 'quiqqer/erp',
                    price: value,
                    vat: self.getAttribute('vat'),
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
            const self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_erp_ajax_calcBruttoPrice', resolve, {
                    'package': 'quiqqer/erp',
                    price: value,
                    vat: self.getAttribute('vat'),
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
                    'package': 'quiqqer/erp',
                    description: description,
                    onError: reject
                });
            });
        }
    });
});
