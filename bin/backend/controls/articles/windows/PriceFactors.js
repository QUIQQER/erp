/**
 * @module package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'qui/controls/windows/Confirm',
    'package/quiqqer/currency/bin/Currency',
    'Ajax',
    'Locale',
    'Mustache',

    "text!package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors.html",
    "text!package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors.Add.html",
    "css!package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors.css"

], function (QUI, QUIWindow, QUIConfirm, Currency, QUIAjax, QUILocale, Mustache, template, templateAdd) {
    "use strict";

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIWindow,
        Type   : 'package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors',

        Binds: [
            '$onOpen'
        ],

        options: {
            ArticleList: null
        },

        initialize: function (option) {
            this.setAttributes({
                title    : QUILocale.get(lg, 'pricefactors.summary.window.title'),
                buttons  : false,
                maxHeight: 600,
                maxWidth : 600,
            });

            this.parent(option);

            this.$Formatter = null;

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        $onOpen: function () {
            this.refresh();
        },

        /**
         * Return the article list
         *
         * @returns {*}
         */
        getArticleList: function () {
            return this.getAttribute('ArticleList');
        },

        refresh: function () {
            if (!this.getAttribute('ArticleList')) {
                this.close();
                return;
            }

            this.Loader.show();

            const ArticleList = this.getAttribute('ArticleList');
            const Content = this.getContent();

            let priceFactors = ArticleList.getPriceFactors();
            let calculations = ArticleList.getCalculation();

            if (typeof calculations.vatArray === 'undefined') {
                calculations.vatArray = {};
            }

            for (let i = 0, len = priceFactors.length; i < len; i++) {
                priceFactors[i].index = i;
                priceFactors[i].priority = i + 1;
            }

            Content.set({
                html: Mustache.render(template, {
                    textAddButton: QUILocale.get(lg, 'add.pricefactor.button'),
                    textNoFactors: QUILocale.get(lg, 'message.pricefactor.empty'),
                    textNetto    : QUILocale.get(lg, 'article.summary.tpl.labelNet'),
                    textBrutto   : QUILocale.get(lg, 'article.summary.tpl.labelGross'),
                    priceFactors : priceFactors,
                    vatArray     : Object.values(calculations.vatArray)
                })
            });

            const PriceFactorButton = Content.getElement('[name="add-pricefactor"]');

            this.getCurrencyFormatter().then((Formatter) => {
                const Total = Content.getElement('.quiqqer-erp-backend-temporaryErp-summaryWin-total');
                let calc = calculations.calculations;

                if (!calc) {
                    calc = {
                        nettoSum: 0,
                        sum     : 0
                    };
                }

                Total.getElement('.netto-value').set('html', Formatter.format(calc.nettoSum));
                Total.getElement('.brutto-value').set('html', Formatter.format(calc.sum));

                Content.getElements('.delete').addEvent('click', (e) => {
                    e.stop();
                    let index = e.target.getParent('tr').get('data-index');

                    ArticleList.removePriceFactor(index);
                    this.refresh();
                });

                Content.getElements('.edit').addEvent('click', (e) => {
                    e.stop();
                    let index = e.target.getParent('tr').get('data-index');

                    this.editPriceFactor(index);
                });

                PriceFactorButton.addEvent('click', (e) => {
                    e.stop();
                    this.addPriceFactor();
                });

                PriceFactorButton.disabled = false;

                this.fireEvent('quiqqerErpPriceFactorWindow', [this]);
                QUI.fireEvent('quiqqerErpPriceFactorWindow', [this]);

                this.Loader.hide();
            });
        },

        /**
         * opens the add price factor window
         */
        addPriceFactor: function () {
            const ArticleList = this.getAttribute('ArticleList');
            let calculations = ArticleList.getCalculation();

            if (typeof calculations.vatArray === 'undefined') {
                calculations.vatArray = {};
            }

            new QUIConfirm({
                icon     : 'fa fa-plus',
                title    : QUILocale.get(lg, 'pricefactors.summary.window.title'),
                maxHeight: 400,
                maxWidth : 580,
                autoclose: false,
                events   : {
                    onOpen: (Win) => {
                        Win.Loader.show();

                        Win.getContent().set({
                            html: Mustache.render(templateAdd, {
                                titlePrice   : QUILocale.get(lg, 'title.price'),
                                titleTitle   : QUILocale.get(lg, 'title.title'),
                                titlePriority: QUILocale.get(lg, 'title.priority'),
                                titleVat     : QUILocale.get(lg, 'title.vat'),

                                calculationBasis          : QUILocale.get(lg, 'calculationBasis'),
                                calculationBasisNetto     : QUILocale.get(lg, 'calculationBasis.netto'),
                                calculationBasisCalcPrice : QUILocale.get(lg, 'calculationBasis.calculationBasisCalcPrice'),
                                calculationBasisCalcBrutto: QUILocale.get(lg, 'calculationBasis.calculationBasisCalcBrutto'),
                            })
                        });

                        require([
                            'package/quiqqer/erp/bin/backend/controls/articles/BruttoCalcButton'
                        ], (Calc) => {
                            new Calc({
                                Price: Win.getContent().getElement('[name="price"]')
                            }).inject(
                                Win.getContent().getElement('[name="price"]'),
                                'after'
                            );

                            Win.getContent()
                               .getElement('[name="priority"]')
                               .set('value', ArticleList.countPriceFactors() + 1);

                            // vat
                            const VatSelect = Win.getContent().getElement('[name="vat"]');

                            for (let vat in calculations.calculations.vatArray) {
                                new Element('option', {
                                    html : vat + '%',
                                    value: vat
                                }).inject(VatSelect);
                            }


                            Win.Loader.hide();
                        });
                    },

                    onSubmit: (Win) => {
                        const Form = Win.getContent().getElement('form');
                        const price = Form.elements.price.value;
                        const currency = ArticleList.getAttribute('currency');

                        if (!price || price === '') {
                            return;
                        }

                        Win.Loader.show();

                        this.getPriceFactorData(
                            price,
                            Form.elements.vat.value,
                            currency
                        ).then((data) => {
                            let priority = Form.elements.priority.value;

                            if (priority === '') {
                                priority = 1;
                            }

                            let priceFactor = {
                                calculation      : 2,
                                calculation_basis: 2,
                                description      : Form.elements.title.value,
                                identifier       : "",
                                index            : priority - 1,
                                nettoSum         : data.nettoSum,
                                nettoSumFormatted: data.nettoSumFormatted,
                                sum              : data.sum,
                                sumFormatted     : data.sumFormatted,
                                title            : Form.elements.title.value,
                                value            : data.sum,
                                valueText        : data.valueText,
                                vat              : Form.elements.vat.value,
                                visible          : 1
                            };

                            ArticleList.addPriceFactor(priceFactor);
                            Win.close();
                            this.refresh();
                        });
                    }
                }
            }).open();
        },

        /**
         * edit a price factor window
         */
        editPriceFactor: function (index) {
            const ArticleList = this.getAttribute('ArticleList');
            let priceFactors = ArticleList.getPriceFactors();
            let calculations = ArticleList.getCalculation();

            if (!priceFactors.length) {
                return;
            }

            if (typeof calculations.vatArray === 'undefined') {
                calculations.vatArray = {};
            }

            const factor = priceFactors[index];

            new QUIConfirm({
                icon     : 'fa fa-edit',
                title    : QUILocale.get(lg, 'pricefactors.edit.window.title'),
                maxHeight: 400,
                maxWidth : 580,
                autoclose: false,
                events   : {
                    onOpen: (Win) => {
                        const Content = Win.getContent();

                        Win.Loader.show();

                        Content.set({
                            html: Mustache.render(templateAdd, {
                                titlePrice   : QUILocale.get(lg, 'title.price'),
                                titleTitle   : QUILocale.get(lg, 'title.title'),
                                titlePriority: QUILocale.get(lg, 'title.priority'),
                                titleVat     : QUILocale.get(lg, 'title.vat'),

                                calculationBasis          : QUILocale.get(lg, 'calculationBasis'),
                                calculationBasisNetto     : QUILocale.get(lg, 'calculationBasis.netto'),
                                calculationBasisCalcPrice : QUILocale.get(lg, 'calculationBasis.calculationBasisCalcPrice'),
                                calculationBasisCalcBrutto: QUILocale.get(lg, 'calculationBasis.calculationBasisCalcBrutto'),
                            })
                        });

                        require([
                            'package/quiqqer/erp/bin/backend/controls/articles/BruttoCalcButton'
                        ], (Calc) => {
                            new Calc({
                                Price: Content.getElement('[name="price"]')
                            }).inject(
                                Content.getElement('[name="price"]'),
                                'after'
                            );

                            Content.getElement('[name="priority"]')
                                   .set('value', ArticleList.countPriceFactors() + 1);

                            // vat
                            const VatSelect = Content.getElement('[name="vat"]');

                            for (let vat in calculations.calculations.vatArray) {
                                new Element('option', {
                                    html : vat + '%',
                                    value: vat
                                }).inject(VatSelect);
                            }

                            const Form = Content.getElement('form');

                            Form.elements.price.value = factor.nettoSum;
                            Form.elements.title.value = factor.title;
                            Form.elements.vat.value = factor.vat;
                            Form.elements.priority.value = factor.priority;

                            Win.Loader.hide();
                        });
                    },

                    onSubmit: (Win) => {
                        const Form = Win.getContent().getElement('form');
                        const price = Form.elements.price.value;
                        const currency = ArticleList.getAttribute('currency');

                        if (!price || price === '') {
                            return;
                        }

                        Win.Loader.show();

                        this.getPriceFactorData(
                            price,
                            Form.elements.vat.value,
                            currency
                        ).then((data) => {
                            let priority = Form.elements.priority.value;

                            if (priority === '') {
                                priority = 1;
                            }

                            ArticleList.editPriceFactor(index, {
                                calculation      : 2,
                                calculation_basis: 2,
                                description      : Form.elements.title.value,
                                identifier       : "",
                                index            : priority - 1,
                                nettoSum         : data.nettoSum,
                                nettoSumFormatted: data.nettoSumFormatted,
                                sum              : data.sum,
                                sumFormatted     : data.sumFormatted,
                                title            : Form.elements.title.value,
                                value            : data.sum,
                                valueText        : data.valueText,
                                vat              : Form.elements.vat.value,
                                visible          : 1
                            });
                            
                            Win.close();
                            this.refresh();
                        });
                    }
                }
            }).open();
        },

        /**
         * returns the current currency formatter of the article list
         *
         * @returns {*}
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

                Currency.getCurrency(currency).then((currency) => {
                    this.$Formatter = QUILocale.getNumberFormatter({
                        style                : 'currency',
                        currency             : currency.code,
                        minimumFractionDigits: currency.precision,
                        maximumFractionDigits: currency.precision
                    });

                    resolve(this.$Formatter);
                });
            });
        },

        /**
         * Return the data for the price factor
         *
         * @param price
         * @param vat
         * @param currency
         * @returns {*}
         */
        getPriceFactorData: function (price, vat, currency) {
            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_erp_ajax_calcPriceFactor', resolve, {
                    'package': 'quiqqer/erp',
                    price    : price,
                    vat      : vat,
                    currency : currency
                });
            });
        }
    });
});
