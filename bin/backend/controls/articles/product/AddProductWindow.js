/**
 * @module package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'package/quiqqer/products/bin/classes/Product',
    'qui/utils/Form',
    'controls/grid/Grid',
    'package/quiqqer/productsearch/bin/controls/products/search/Window',
    'Ajax',
    'Locale',
    'Mustache',

    'text!package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow.ProductSettings.html',
    'css!package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow.css'

], function (QUI, QUIControl, QUIConfirm, Product, QUIFormUtils, Grid, ProductSearch, QUIAjax, QUILocale, Mustache,
             templateProductSettings) {
    "use strict";

    const lg = 'quiqqer/erp';

    const filterField = function (field) {
        return field.id === this;
    };

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
            const self = this;

            new ProductSearch({
                autoclose: false,
                events   : {
                    onOpen: function (Win) {
                        self.fireEvent('open', [
                            self,
                            Win
                        ]);
                    },

                    onLoad: function (Win) {
                        self.fireEvent('load', [
                            self,
                            Win
                        ]);
                    },

                    onSubmit: function (Win, products) {
                        let productId = products[0];

                        Win.Loader.show();

                        self.$isVariantParent(productId).then(function (isVariantParent) {
                            if (isVariantParent) {
                                return self.$openVariantChildren(productId).then(function (variantId) {
                                    productId = variantId;
                                    return self.$hasProductCustomFields(productId);
                                });
                            }

                            return self.$hasProductCustomFields(productId);
                        }).then(function (hasCustomFields) {
                            return Win.close().then(function () {
                                if (!hasCustomFields) {
                                    return Promise.resolve(false);
                                }
                                return self.openProductSettings(productId);
                            });
                        }).then(function (productSettings) {
                            return self.$parseProductToArticle(productId, productSettings);
                        }).then(function (article) {
                            self.fireEvent('submit', [
                                self,
                                article
                            ]);
                        }).catch(function (err) {
                            if (err === false) {
                                Win.Loader.hide();
                                return;
                            }

                            console.error(err);
                            Win.Loader.hide();
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
            const self = this;

            return new Promise(function (resolve) {
                new QUIConfirm({
                    title    : QUILocale.get('quiqqer/erp', 'window.products.add.title'),
                    icon     : 'fa fa-shopping-bag',
                    maxHeight: 500,
                    maxWidth : 700,
                    events   : {
                        onOpen: function (Win) {
                            Win.Loader.show();

                            const Content = Win.getContent();

                            Content.set('html', Mustache.render(templateProductSettings, {
                                labelAmount: QUILocale.get(lg, 'products.quantity'),
                                editAmount : self.getAttribute('editAmount')
                            }));

                            Content.addClass('quiqqer-erp-addProductWin');

                            const Form = Content.getElement('form');

                            Form.setStyles({
                                'float': 'left',
                                width  : '100%'
                            });

                            const Table = Form.getElement('table tbody');

                            const Row = new Element('tr', {
                                'class': 'quiqqer-erp-addProductWin-row',
                                html   : '<td><label class="field-container"></label></td>'
                            });

                            const fieldValues = self.getAttribute('fieldValues');

                            self.$getProductEdit(productId).then(function (result) {
                                const Ghost = new Element('div', {
                                    html: result
                                });

                                const Header = Ghost.getElement('header');
                                const styles = Ghost.getElements('style');

                                if (Header) {
                                    Header.getElements('.quiqqer-products-productEdit-header-image').destroy();
                                    Header.inject(Form, 'top');
                                }

                                styles.inject(Form);

                                Ghost.getElements('.quiqqer-products-productEdit-data-field').each(function (Field) {
                                    const RowClone = Row.clone();
                                    const Label    = RowClone.getElement('label');
                                    let fieldId    = Field.get('data-field-id');

                                    RowClone.set('data-field-id', fieldId);

                                    Label.set('html', Field.getElement('.quiqqer-product-field').get('html'));

                                    Label.getElement('.quiqqer-product-field-title')
                                        .addClass('field-container-item');

                                    const Value = Label.getElement('.quiqqer-product-field-value');
                                    const Input = Value.getElement('input,select,textarea');

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
                                        [
                                            productId,
                                            Win,
                                            self
                                        ]
                                    );

                                    // Parse field controls
                                    let controls = Content.getElements('[data-quiid]');

                                    for (let i = 0, len = controls.length; i < len; i++) {
                                        let ControlElm = controls[i];
                                        let Control    = QUI.Controls.getById(ControlElm.get('data-quiid'));
                                        let fieldName  = ControlElm.get('name');
                                        let fieldId    = ControlElm.getParent('.quiqqer-erp-addProductWin-row')
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
                            const Content = Win.getContent();
                            const Form    = Content.getElement('form');
                            let data      = QUIFormUtils.getFormData(Form);

                            // Parse field controls
                            let controls = Content.getElements('[data-quiid]');

                            for (let i = 0, len = controls.length; i < len; i++) {
                                let ControlElm = controls[i];
                                let Control    = QUI.Controls.getById(ControlElm.get('data-quiid'));
                                let fieldId    = ControlElm.get('name');

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
            const self = this;

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
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_erp_ajax_products_hasProductCustomFields', resolve, {
                    'package': 'quiqqer/erp',
                    productId: productId,
                    onError  : reject
                });
            });
        },

        /**
         * Is the product a variant parent?
         *
         * @param productId
         * @returns {Promise}
         */
        $isVariantParent: function (productId) {
            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_erp_ajax_products_isVariantParent', resolve, {
                    'package': 'quiqqer/erp',
                    productId: productId,
                    onError  : reject
                });
            });
        },

        $openVariantChildren: function (productId) {
            const self = this;

            return new Promise(function (resolve, reject) {
                new QUIConfirm({
                    title    : QUILocale.get('quiqqer/erp', 'window.products.variant.select.title'),
                    icon     : 'fa fa-shopping-bag',
                    maxHeight: 500,
                    maxWidth : 700,
                    autoclose: false,
                    events   : {
                        onOpen: function (Win) {
                            Win.Loader.show();
                            Win.getContent().set('html', '');

                            new Element('div', {
                                html: QUILocale.get('quiqqer/erp', 'window.products.variant.select.content')
                            }).inject(Win.getContent());

                            const GridContainer = new Element('div', {
                                styles: {
                                    marginTop: 20
                                }
                            }).inject(Win.getContent());

                            self.$getVariantColumns(productId).then(function (columns) {
                                Win.$VariantGrid = new Grid(GridContainer, {
                                    columnModel: columns,
                                    pagination : false,
                                    width      : Win.getContent().getSize().x - 40,
                                    height     : 300
                                });

                                Win.$VariantGrid.addEvents({
                                    onDblClick: function () {
                                        Win.submit();
                                    }
                                });

                                QUIAjax.get('package_quiqqer_erp_ajax_products_getVariantChildren', function (variants) {
                                    let i, n, len, nLen, entry, variant, needle, field, fieldId;
                                    let data = [];

                                    let needles = [
                                        'id',
                                        'title',
                                        'e_date',
                                        'c_date',
                                        'priority',
                                        'url',
                                        'price_netto_display'
                                    ];

                                    let fields = {
                                        'productNo'  : 3,
                                        'price_netto': 1,
                                        'priority'   : 18
                                    };

                                    // add variant fields to field object
                                    for (i = 0, len = columns.length; i < len; i++) {
                                        fields['field-' + columns[i].fieldId] = columns[i].fieldId;
                                    }

                                    const lang = QUILocale.getCurrent();

                                    const getAttributeListValueTitle = (FieldData) => {
                                        const valueId = FieldData.value;

                                        if (typeof FieldData.options.entries === 'undefined') {
                                            return false;
                                        }

                                        for (const Entry of Object.values(FieldData.options.entries)) {
                                            if (Entry.valueId != valueId) {
                                                continue;
                                            }

                                            if (typeof Entry.title[lang] !== 'undefined') {
                                                return Entry.title[lang];
                                            }

                                            break;
                                        }

                                        return false;
                                    };

                                    for (i = 0, len = variants.length; i < len; i++) {
                                        entry   = {};
                                        variant = variants[i];

                                        // status
                                        if (variant.active) {
                                            entry.status = new Element('span', {'class': 'fa fa-check'});
                                        } else {
                                            entry.status = new Element('span', {'class': 'fa fa-close'});
                                        }

                                        if (typeof variant.defaultVariant !== 'undefined' && variant.defaultVariant) {
                                            entry.defaultVariant =
                                                new Element('span', {'class': 'fa fa-check-circle-o'});
                                        } else {
                                            entry.defaultVariant = new Element('span', {
                                                html: '&nbsp;'
                                            });
                                        }
                                        // attributes + fields
                                        for (n = 0, nLen = needles.length; n < nLen; n++) {
                                            needle = needles[n];

                                            if (typeof variant[needle] === 'undefined' || !variant[needle]) {
                                                entry[needle] = '-';
                                            } else {
                                                entry[needle] = variant[needle];
                                            }
                                        }

                                        for (needle in fields) {
                                            if (!fields.hasOwnProperty(needle)) {
                                                continue;
                                            }

                                            fieldId = fields[needle];
                                            field   = variant.fields.filter(filterField.bind(fieldId));

                                            if (!field.length) {
                                                entry[needle] = '-';
                                            } else {
                                                const valueTitle = getAttributeListValueTitle(field[0]);
                                                const value = field[0].value;

                                                if (valueTitle) {
                                                    entry[needle] = '(' + value + ') ' + valueTitle;
                                                } else {
                                                    entry[needle] = value;
                                                }
                                            }
                                        }

                                        data.push(entry);
                                    }


                                    Win.$VariantGrid.setData({
                                        data: data
                                    });

                                    Win.Loader.hide();
                                }, {
                                    'package': 'quiqqer/erp',
                                    productId: productId
                                });
                            });
                        },

                        onSubmit: function (Win) {
                            let data = Win.$VariantGrid.getSelectedData();

                            if (!data.length) {
                                return;
                            }

                            resolve(Win.$VariantGrid.getSelectedData()[0].id);
                            Win.close();
                        },

                        onCancel: function () {
                            reject(false);
                        }
                    }
                }).open();
            });
        },

        $getVariantColumns: function (productId) {
            const VariantParent = new Product({
                id: productId
            });

            return VariantParent.getVariantFields().then(function (variantFields) {
                let columns = [
                    {
                        header   : QUILocale.get('quiqqer/products', 'products.product.panel.grid.defaultStatus'),
                        dataIndex: 'defaultVariant',
                        dataType : 'node',
                        width    : 60
                    },
                    {
                        header   : QUILocale.get('quiqqer/system', 'status'),
                        dataIndex: 'status',
                        dataType : 'node',
                        width    : 60
                    },
                    {
                        header   : QUILocale.get('quiqqer/system', 'id'),
                        dataIndex: 'id',
                        dataType : 'number',
                        width    : 50
                    },
                    {
                        header   : QUILocale.get('quiqqer/products', 'productNo'),
                        dataIndex: 'productNo',
                        dataType : 'text',
                        width    : 100,
                        sortable : false
                    },
                    {
                        header   : QUILocale.get('quiqqer/system', 'title'),
                        dataIndex: 'title',
                        dataType : 'text',
                        width    : 200,
                        sortable : false
                    },
                    {
                        header   : QUILocale.get('quiqqer/products', 'products.product.panel.grid.nettoprice'),
                        dataIndex: 'price_netto_display',
                        dataType : 'text',
                        width    : 100,
                        sortable : false,
                        className: 'grid-align-right'
                    }
                ];

                for (let i = 0, len = variantFields.length; i < len; i++) {
                    columns.push({
                        header   : variantFields[i].title,
                        dataIndex: 'field-' + variantFields[i].id,
                        fieldId  : variantFields[i].id,
                        dataType : 'text',
                        width    : 150,
                        sortable : false
                    });
                }

                // end colums
                columns = columns.concat([
                    {
                        header   : QUILocale.get('quiqqer/system', 'editdate'),
                        dataIndex: 'e_date',
                        dataType : 'text',
                        width    : 160
                    },
                    {
                        header   : QUILocale.get('quiqqer/system', 'createdate'),
                        dataIndex: 'c_date',
                        dataType : 'text',
                        width    : 160
                    },
                    {
                        header   : QUILocale.get('quiqqer/products', 'priority'),
                        dataIndex: 'priority',
                        dataType : 'number',
                        width    : 60,
                        sortable : false
                    }
                ]);

                return columns;

            });
        }
    });
});
