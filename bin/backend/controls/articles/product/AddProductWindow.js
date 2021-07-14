/**
 * @module package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'qui/utils/Form',
    'package/quiqqer/productsearch/bin/controls/products/search/Window',
    'Ajax',
    'Locale',
    'Mustache',

    'text!package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow.ProductSettings.html',
    'css!package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow.css'

], function (QUI, QUIControl, QUIConfirm, QUIFormUtils, ProductSearch, QUIAjax, QUILocale, Mustache,
             templateProductSettings) {
    "use strict";

    var lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow',

        options: {
            user       : false,
            fields     : false, // field ids that should be delivered additionally (onSubmit)
            fieldValues: {},    // field id mapped to a value - the edit form is pre-filled with the given values

            editAmount: true
        },

        initialize: function (options) {
            this.parent(options);

            this.$user = {};

            if (typeof options.user !== 'undefined') {
                this.$user = options.user;
            }
        },

        /**
         * open and start the product selection
         */
        open: function () {
            var self = this;

            new ProductSearch({
                autoclose: false,
                events   : {
                    onOpen: function (Win) {
                        self.fireEvent('open', [self, Win]);
                    },

                    onLoad: function (Win) {
                        self.fireEvent('load', [self, Win]);
                    },

                    onSubmit: function (Win, products) {
                        var productId = products[0];

                        Win.Loader.show();

                        self.$hasProductCustomFields(productId).then(function (hasCustomFields) {
                            return Win.close().then(function () {
                                if (!hasCustomFields) {
                                    return Promise.resolve(false);
                                }
                                return self.openProductSettings(productId);
                            });
                        }).then(function (productSettings) {
                            return self.$parseProductToArticle(productId, productSettings);
                        }).then(function (article) {
                            self.fireEvent('submit', [self, article]);
                        }).catch(function (err) {
                            console.error(err);
                        });
                    }
                }
            }).open();
        },

        /**
         * Opens the product settings for a product
         *
         * @param productId
         * @returns {Promise}
         */
        openProductSettings: function (productId) {
            var self = this;

            return new Promise(function (resolve) {
                new QUIConfirm({
                    title    : QUILocale.get('quiqqer/erp', 'window.products.add.title'),
                    icon     : 'fa fa-shopping-bag',
                    maxHeight: 500,
                    maxWidth : 700,
                    events   : {
                        onOpen: function (Win) {
                            Win.Loader.show();

                            var Content = Win.getContent();

                            Content.set('html', Mustache.render(templateProductSettings, {
                                labelAmount: QUILocale.get(lg, 'products.quantity'),
                                editAmount : self.getAttribute('editAmount')
                            }));

                            Content.addClass('quiqqer-erp-addProductWin');

                            var Form = Content.getElement('form');

                            Form.setStyles({
                                'float': 'left',
                                width  : '100%'
                            });

                            var Table = Form.getElement('table tbody');

                            var Row = new Element('tr', {
                                'class': 'quiqqer-erp-addProductWin-row',
                                html   : '<td><label class="field-container"></label></td>'
                            });

                            var fieldValues = self.getAttribute('fieldValues');

                            self.$getProductEdit(productId).then(function (result) {
                                var Ghost = new Element('div', {
                                    html: result
                                });

                                var Header = Ghost.getElement('header');
                                var styles = Ghost.getElements('style');

                                if (Header) {
                                    Header.getElements('.quiqqer-products-productEdit-header-image').destroy();
                                    Header.inject(Form, 'top');
                                }

                                styles.inject(Form);

                                Ghost.getElements('.quiqqer-products-productEdit-data-field').each(function (Field) {
                                    var RowClone = Row.clone();
                                    var Label    = RowClone.getElement('label');
                                    var fieldId  = Field.get('data-field-id');

                                    RowClone.set('data-field-id', fieldId);

                                    Label.set('html', Field.getElement('.quiqqer-product-field').get('html'));

                                    Label.getElement('.quiqqer-product-field-title')
                                        .addClass('field-container-item');

                                    var Value = Label.getElement('.quiqqer-product-field-value');
                                    var Input = Value.getElement('input,select,textarea');

                                    if (Input) {
                                        Input.replaces(Value);
                                        Input.addClass('field-container-field');

                                        if (fieldId in fieldValues) {
                                            Input.value = fieldValues[fieldId];
                                        }
                                    } else {
                                        Label.getElement('.quiqqer-product-field-value')
                                            .addClass('field-container-field');
                                    }

                                    RowClone.inject(Table);
                                });

                                QUI.parse(Form).then(function () {
                                    QUI.fireEvent(
                                        'quiqqerErpAddProductWindowProductSettingsOpen',
                                        [productId, Win, self]
                                    );

                                    // Parse field controls
                                    var controls = Content.getElements('[data-quiid]');

                                    for (var i = 0, len = controls.length; i < len; i++) {
                                        var ControlElm = controls[i];
                                        var Control    = QUI.Controls.getById(ControlElm.get('data-quiid'));
                                        var fieldName  = ControlElm.get('name');
                                        var fieldId    = ControlElm
                                            .getParent('.quiqqer-erp-addProductWin-row')
                                            .get('data-field-id');

                                        if (!fieldName) {
                                            continue;
                                        }

                                        if (!(fieldId in fieldValues)) {
                                            continue;
                                        }

                                        if ('setValue' in Control) {
                                            Control.setValue(fieldValues[fieldId]);
                                        }
                                    }

                                    return Win.Loader.hide();
                                });
                            }).catch(function (err) {
                                console.error(err);
                            });
                        },

                        onSubmit: function (Win) {
                            var Content = Win.getContent();
                            var Form    = Content.getElement('form');
                            var data    = QUIFormUtils.getFormData(Form);

                            // Parse field controls
                            var controls = Content.getElements('[data-quiid]');

                            for (var i = 0, len = controls.length; i < len; i++) {
                                var ControlElm = controls[i];
                                var Control    = QUI.Controls.getById(ControlElm.get('data-quiid'));
                                var fieldId    = ControlElm.get('name');

                                if (!fieldId) {
                                    continue;
                                }

                                if ('getValue' in Control) {
                                    data[fieldId] = Control.getValue();
                                }
                            }

                            resolve(data);
                        },

                        onCancel: function () {
                            resolve(false);
                        }
                    }
                }).open();
            });
        },

        /**
         * Return the user data
         *
         * @returns {{}|*}
         */
        getUserData: function () {
            return this.$user;
        },

        /**
         * Return the data of a product for an ERP article
         *
         * @param {String|Number} productId
         * @param {Object} [attributes] - fields, quantity and so on
         * @returns {Promise}
         */
        $parseProductToArticle: function (productId, attributes) {
            var self = this;

            attributes = attributes || {};

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_products_parseProductToArticle', resolve, {
                    'package' : 'quiqqer/erp',
                    productId : productId,
                    attributes: JSON.encode(attributes),
                    user      : JSON.encode(self.getUserData()),
                    fields    : JSON.encode(self.getAttribute('fields')),
                    onError   : reject
                });
            });
        },

        /**
         * Return the product edit
         *
         * @param {String|Number} productId
         * @returns {Promise}
         */
        $getProductEdit: function (productId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_products_getProductEdit', resolve, {
                    'package': 'quiqqer/erp',
                    productId: productId,
                    user     : JSON.encode(this.getUserData()),
                    onError  : reject
                });
            }.bind(this));
        },

        /**
         * Has the product custom fields
         *
         * @param productId
         * @returns {Promise}
         */
        $hasProductCustomFields: function (productId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_erp_ajax_products_hasProductCustomFields', resolve, {
                    'package': 'quiqqer/erp',
                    productId: productId,
                    onError  : reject
                });
            }.bind(this));
        }
    });
});
