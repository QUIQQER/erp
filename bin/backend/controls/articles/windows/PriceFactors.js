define('package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors', [
    'qui/QUI',
    'qui/controls/windows/SimpleWindow',
    'qui/controls/windows/Confirm',
    'package/quiqqer/currency/bin/Currency',
    'Ajax',
    'Locale',
    'Mustache',
    'package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary',
    'text!package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors.html',
    'text!package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors.Add.html',
    'text!package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors.Factors.html',
    'css!package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors.css'
], function (QUI, SimpleWindow, QUIConfirm, Currency, QUIAjax, QUILocale, Mustache, ArticleSummary,
             template, templateAdd, templateFactors) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({
        Extends: SimpleWindow,
        Type: 'package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors',

        Binds: ['$onOpen', 'refresh'],

        options: {
            ArticleList: null
        },

        initialize: function (options) {
            this.parent(Object.assign({
                maxWidth: 680,
                maxHeight: 680,
                autoresize: true,
                resizable: false,
                'class': 'quiqqer-erp-totals-window'
            }, options));

            this.$Formatter = null;
            this.$Summary = null;
            this.$Factors = null;
            this.$factorSnapshot = null;
            this.$closed = true;
            const cleanup = () => {
                this.$closed = true;
                this.getArticleList()?.removeEvent('onCalc', this.refresh);
                this.$Summary?.destroy();
                this.$Summary = null;
            };
            this.addEvents({
                onOpen: this.$onOpen,
                onCloseBegin: cleanup,
                onDestroy: cleanup
            });
        },

        $onOpen: function () {
            const List = this.getArticleList();

            if (!List) {
                this.close();
                return;
            }

            this.$closed = false;
            this.$factorSnapshot = null;
            const title = QUILocale.get(lg, 'article.summary.totalsWindow.title');
            const Content = this.getContent();
            Content.innerHTML = Mustache.render(template, {
                title: title,
                labelFactors: QUILocale.get(lg, 'article.summary.totalsWindow.factors'),
                labelClose: QUILocale.get('quiqqer/core', 'close')
            });
            this.getElm().setAttribute('role', 'dialog');
            this.getElm().setAttribute('aria-modal', 'true');
            this.getElm().setAttribute('aria-label', title);
            const Description = Content.querySelector('[data-name="description"]');
            Description.id = 'erp-totals-description-' + this.getId();
            this.getElm().setAttribute('aria-describedby', Description.id);

            this.$Summary = new ArticleSummary({
                List: List,
                currency: List.getAttribute('currency'),
                showPosSummary: false,
                manualTotalsInline: true,
                openOnClick: false
            }).inject(Content.querySelector('[data-name="totals"]'));

            this.$Factors = Content.querySelector('[data-name="price-factors"]');
            this.$Factors.addEventListener('click', (event) => {
                const Button = event.target.closest('button[data-name]');

                if (!Button || !this.$Factors.contains(Button)) {
                    return;
                }

                if (Button.dataset.name === 'add-pricefactor') {
                    this.addPriceFactor();
                } else if (Button.dataset.name === 'edit-pricefactor') {
                    this.editPriceFactor(Number(Button.dataset.index));
                } else if (Button.dataset.name === 'remove-pricefactor') {
                    List.removePriceFactor(Number(Button.dataset.index));
                    this.refresh();
                    this.$Factors.querySelector('[data-name="add-pricefactor"]').focus();
                }
            });
            Content.querySelector('[data-name="close-totals"]').addEventListener('click', () => this.close());
            this.getElm().addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    this.close();
                } else if (event.key === 'Tab') {
                    const controls = Array.from(this.getElm().querySelectorAll('[data-name]'))
                        .filter((Element) => Element.matches('button, input, summary') &&
                            !Element.disabled && Element.getClientRects().length > 0);
                    const First = controls[0];
                    const Last = controls[controls.length - 1];

                    if (event.shiftKey && document.activeElement === First) {
                        event.preventDefault();
                        Last.focus();
                    } else if (!event.shiftKey && document.activeElement === Last) {
                        event.preventDefault();
                        First.focus();
                    }
                }
            });
            List.addEvent('onCalc', this.refresh);
            this.refresh();
            Content.querySelector('[data-name="close-totals"]').focus();
        },

        getArticleList: function () {
            return this.getAttribute('ArticleList');
        },

        refresh: function () {
            if (this.$closed || !this.$Factors) {
                return;
            }

            const List = this.getArticleList();
            const manual = List.getCalculationMode() === 'manual';
            const Content = this.getContent();
            Content.querySelector('[data-name="mode"]').textContent = QUILocale.get(lg,
                manual ? 'manual.manual' : 'manual.automatic');
            Content.querySelector('[data-name="description"]').textContent = QUILocale.get(lg,
                manual ? 'manual.hint' : 'article.summary.totalsWindow.automaticHint');

            const priceFactors = List.getPriceFactors();
            const snapshot = JSON.stringify(priceFactors);

            if (snapshot === this.$factorSnapshot) {
                return;
            }

            this.$factorSnapshot = snapshot;
            this.$Factors.innerHTML = Mustache.render(templateFactors, {
                textAddButton: QUILocale.get(lg, 'add.pricefactor.button'),
                textNoFactors: QUILocale.get(lg, 'message.pricefactor.empty'),
                labelEdit: QUILocale.get(lg, 'pricefactors.edit.window.title'),
                labelRemove: QUILocale.get('quiqqer/core', 'delete'),
                priceFactors: priceFactors.map((factor, index) => Object.assign({}, factor, {
                    index: index,
                    priority: index + 1
                }))
            });
            this.fireEvent('quiqqerErpPriceFactorWindow', [this]);
            QUI.fireEvent('quiqqerErpPriceFactorWindow', [this]);
            this.Loader.hide();
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
                            Form.elements.priority.value = index + 1;

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
                                sum              : data.nettoSum,
                                sumFormatted     : data.nettoSumFormatted,
                                title            : Form.elements.title.value,
                                value            : data.nettoSum,
                                valueText        : data.nettoSumFormatted,
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
