/**
 * @module package/quiqqer/erp/bin/backend/controls/elements/PriceCalcInput
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/elements/PriceCalcInput', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'Ajax',
    'Locale',

    'css!package/quiqqer/erp/bin/backend/controls/elements/PriceCalcInput.css'

], function (QUI, QUIControl, QUIConfirm, QUIAjax, QUILocale) {
    "use strict";

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/elements/PriceCalcInput',

        Binds: [
            '$onImport',
            '$onChange',
            '$keyDown',
            'switchToBrutto',
            'switchToNetto',
            'openVatWindow'
        ],

        options: {
            vat: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$loaded = false;

            this.$Input = null;
            this.$NettoContainer = null;
            this.$BruttoContainer = null;
            this.$VatButton = null;

            this.$BruttoEdit = null;
            this.$NettoEdit = null;

            this.$LoaderNetto = null;
            this.$LoaderBrutto = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function () {
            this.$Input = this.getElm();
            this.$Input.type = 'hidden';

            this.$Elm = new Element('div', {
                'class': 'quiqqer-erp-priceCalcInput field-container-field-no-padding'
            }).wraps(this.$Input);

            if (this.$Input.hasClass('field-container-field')) {
                this.$Elm.addClass('field-container-field');
            }

            // netto
            this.$NettoContainer = new Element('div', {
                'class': 'quiqqer-erp-priceCalcInput-netto',
                html   : '<span class="quiqqer-erp-priceCalcInput-label">' +
                         QUILocale.get(lg, 'priceCalcInput.netto') +
                         '</span>' +
                         '<input type="text" name="netto-price" autocomplete="off" />'
            }).inject(this.$Elm);

            this.$NettoEdit = new Element('div', {
                'class': 'quiqqer-erp-priceCalcInput-netto-edit',
                html   : '<span class="fa fa-edit"></span>',
                styles : {
                    display: 'none'
                },
                events : {
                    click: this.switchToNetto
                }
            }).inject(this.$NettoContainer);


            this.$LoaderNetto = new Element('div', {
                'class': 'quiqqer-erp-priceCalcInput-netto-loader',
                html   : '<span class="fa fa-circle-o-notch fa-spin"></span>'
            }).inject(this.$NettoContainer);

            this.$LoaderNetto.setStyle('display', 'none');

            // brutto
            this.$BruttoContainer = new Element('div', {
                'class': 'quiqqer-erp-priceCalcInput-brutto',
                html   : '<span class="quiqqer-erp-priceCalcInput-label">' +
                         QUILocale.get(lg, 'priceCalcInput.brutto') +
                         '</span>' +
                         '<input type="text" name="netto-price" autocomplete="off" />'
            }).inject(this.$Elm);

            this.$BruttoEdit = new Element('button', {
                'class': 'quiqqer-erp-priceCalcInput-brutto-edit',
                html   : '<span class="fa fa-edit"></span>',
                styles : {
                    display: 'none'
                },
                events : {
                    click: this.switchToBrutto
                }
            }).inject(this.$BruttoContainer);

            this.$LoaderBrutto = new Element('div', {
                'class': 'quiqqer-erp-priceCalcInput-brutto-loader',
                html   : '<span class="fa fa-circle-o-notch fa-spin"></span>'
            }).inject(this.$BruttoContainer);

            this.$LoaderBrutto.setStyle('display', 'none');


            // vat
            this.$VatButton = new Element('button', {
                'class': 'quiqqer-erp-priceCalcInput-vat qui-button--no-icon qui-button qui-utils-noselect',
                events : {
                    click: this.openVatWindow
                }
            }).inject(this.$Elm);

            const Brutto = this.$BruttoContainer.getElement('input');
            const Netto = this.$NettoContainer.getElement('input');

            Brutto.disabled = true;
            Brutto.addEvent('blur', this.$onChange);
            Brutto.addEvent('keydown', this.$keyDown);
            Netto.addEvent('blur', this.$onChange);
            Netto.addEvent('keydown', this.$keyDown);

            if (this.getAttribute('vat')) {
                let vat = this.getAttribute('vat');

                if (typeof vat !== 'number') {
                    this.setAttribute('vat', parseFloat(this.getAttribute(vat)));
                }

                this.$refreshVat();
                this.setNetto(this.$Input.value);
                this.switchToNetto();
                this.$loaded = true;
            } else {
                //set default vat
                this.getDefaultVat().then((vat) => {
                    if (typeof vat !== 'number') {
                        vat = parseFloat(this.getAttribute(vat));
                    }

                    this.setAttribute('vat', vat);
                    this.$refreshVat();

                    this.setNetto(this.$Input.value);
                    this.switchToNetto();
                    this.$loaded = true;
                });
            }
        },

        $keyDown: function (e) {
            if (e.key === 'enter') {
                Promise.all([
                    this.isNetto() ? this.fetchBrutto() : this.fetchNetto()
                ]).catch(() => {
                });

                e.stop();
            }
        },

        switchToNetto: function () {
            this.$NettoContainer.removeClass('quiqqer-erp-priceCalcInput--disabled');
            this.$BruttoContainer.addClass('quiqqer-erp-priceCalcInput--disabled');

            this.$NettoContainer.getElement('input').disabled = false;
            this.$BruttoContainer.getElement('input').disabled = true;

            if (this.$loaded) {
                this.$NettoContainer.getElement('input').focus();
            }

            this.$NettoEdit.setStyle('display', 'none');
            this.$BruttoEdit.setStyle('display', null);
        },

        switchToBrutto: function () {
            this.$NettoContainer.addClass('quiqqer-erp-priceCalcInput--disabled');
            this.$BruttoContainer.removeClass('quiqqer-erp-priceCalcInput--disabled');

            this.$NettoContainer.getElement('input').disabled = true;
            this.$BruttoContainer.getElement('input').disabled = false;

            if (this.$loaded) {
                this.$BruttoContainer.getElement('input').focus();
            }

            this.$NettoEdit.setStyle('display', null);
            this.$BruttoEdit.setStyle('display', 'none');
        },

        isNetto: function () {
            return this.$BruttoContainer.hasClass('quiqqer-erp-priceCalcInput--disabled');
        },

        $onChange: function () {
            const Netto = this.$NettoContainer.getElement('input');
            const Brutto = this.$BruttoContainer.getElement('input');

            if (Brutto.disabled) {
                this.setNetto(Netto.value);
            } else {
                this.setBrutto(Brutto.value);
            }
        },

        setNetto: function (value) {
            if (!value || value === '') {
                return;
            }

            if (this.$Input.value === value) {
                return;
            }

            const Netto = this.$NettoContainer.getElement('input');

            this.$LoaderBrutto.setStyle('display', null);

            this.validatePrice(value).then((floatPrice) => {
                this.$Input.value = floatPrice;
                Netto.value = floatPrice;

                return this.fetchBrutto();
            });
        },

        setBrutto: function (value) {
            if (!value || value === '') {
                return;
            }

            this.validatePrice(value).then((floatPrice) => {
                this.$BruttoContainer.getElement('input').value = floatPrice;
                return this.fetchNetto();
            }).then(() => {
                this.$Input.value = this.$NettoContainer.getElement('input').value;
            });
        },

        validatePrice: function (value) {
            return new Promise((resolve) => {
                QUIAjax.get('package_quiqqer_erp_ajax_money_validatePrice', resolve, {
                    'package': 'quiqqer/erp',
                    value    : value
                });
            });
        },

        getDefaultVat: function () {
            return new Promise((resolve) => {
                QUIAjax.get('package_quiqqer_erp_ajax_vat_getDefault', resolve, {
                    'package': 'quiqqer/erp'
                });
            });
        },

        fetchBrutto: function () {
            return new Promise((resolve) => {
                const Netto = this.$NettoContainer.getElement('input');
                const Brutto = this.$BruttoContainer.getElement('input');

                const value = Netto.value;

                if (value === '') {
                    return resolve();
                }

                this.$LoaderBrutto.setStyle('display', null);

                QUIAjax.get('package_quiqqer_erp_ajax_calcBruttoPrice', (result) => {
                    Brutto.value = result;
                    this.$LoaderBrutto.setStyle('display', 'none');
                    resolve();
                }, {
                    'package': 'quiqqer/erp',
                    price    : value,
                    formatted: 0,
                    vat      : this.getAttribute('vat')
                });
            });
        },

        fetchNetto: function () {
            return new Promise((resolve) => {
                const Netto = this.$NettoContainer.getElement('input');
                const Brutto = this.$BruttoContainer.getElement('input');

                const value = Brutto.value;

                if (value === '') {
                    return resolve();
                }

                this.$LoaderNetto.setStyle('display', null);

                QUIAjax.get('package_quiqqer_erp_ajax_calcNettoPrice', (result) => {
                    Netto.value = result;
                    this.$LoaderNetto.setStyle('display', 'none');
                    resolve();
                }, {
                    'package': 'quiqqer/erp',
                    price    : value,
                    formatted: 0,
                    vat      : this.getAttribute('vat')
                });
            });
        },

        $refreshVat: function () {
            this.$VatButton.set('html', this.getAttribute('vat') + '%');
        },

        openVatWindow: function () {
            new QUIConfirm({
                maxHeight: 300,
                maxWidth : 400,
                autoclose: false,
                title    : QUILocale.get(lg, 'priceCalcInput.window.vat.title'),
                icon     : 'fa fa-percent',
                events   : {
                    onOpen  : (Win) => {
                        Win.Loader.show();
                        const Content = Win.getContent();
                        Content.setStyle('textAlign', 'center');
                        Content.set('html', QUILocale.get(lg, 'priceCalcInput.window.vat.message'));

                        const Select = new Element('select', {
                            styles: {
                                marginTop: 20,
                                width    : 150
                            }
                        }).inject(Content);

                        QUIAjax.get('package_quiqqer_tax_ajax_getAvailableTax', (taxList) => {
                            let vatList = [];
                            let i, len;

                            for (i in taxList) {
                                if (taxList[i].vat && vatList.indexOf(taxList[i].vat) === -1) {
                                    if (typeof taxList[i].vat !== 'number') {
                                        taxList[i].vat = parseFloat(taxList[i].vat);
                                    }

                                    vatList.push(taxList[i].vat);
                                }
                            }

                            vatList.sort(function (a, b) {
                                return a - b;
                            });


                            for (i = 0, len = vatList.length; i < len; i++) {
                                new Element('option', {
                                    html : vatList[i] + '%',
                                    value: vatList[i]
                                }).inject(Select);
                            }

                            Select.value = this.getAttribute('vat');
                            Win.Loader.hide();
                        }, {
                            'package': 'quiqqer/tax'
                        });

                    },
                    onSubmit: (Win) => {
                        const Content = Win.getContent();
                        const Select = Content.getElement('select');

                        Win.Loader.show();

                        this.setAttribute('vat', Select.value);
                        this.$refreshVat();

                        Promise.all([
                            this.isNetto() ? this.fetchBrutto() : this.fetchNetto()
                        ]).then(function () {
                            Win.close();
                        });
                    }
                }
            }).open();
        }
    });
});
