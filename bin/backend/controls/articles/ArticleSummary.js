/**
 * @module package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary
 * @author www.pcsg.de (Henning Leutz)
 *
 * Displays an article list
 */
define('package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/erp/bin/backend/controls/articles/Article',
    'package/quiqqer/currency/bin/Currency',
    'Mustache',
    'Locale',

    'text!package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary.html',
    'css!package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary.css'

], function (QUI, QUIControl, Article, Currency, Mustache, QUILocale, template) {
    "use strict";

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary',

        options: {
            List    : null,
            styles  : false,
            currency: 'EUR',

            showPosSummary: true // show summary for selected article
        },

        Binds: [
            '$onInject',
            '$refreshArticleSelect',
            'openSummary'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$NettoSum = null;
            this.$BruttoSum = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Create the domnode element
         *
         * @returns {HTMLDivElement}
         */
        create: function () {
            const showPosSummary = this.getAttribute('showPosSummary');

            this.$Elm = new Element('div', {
                'class': 'quiqqer-erp-backend-temporaryErp-summary',
                html   : Mustache.render(template, {
                    showPosSummary: showPosSummary,
                    labelPosInfo  : QUILocale.get(lg, 'article.summary.tpl.labelPosInfo'),
                    labelNet      : QUILocale.get(lg, 'article.summary.tpl.labelNet'),
                    labelGross    : QUILocale.get(lg, 'article.summary.tpl.labelGross'),
                    labelSums     : QUILocale.get(lg, 'article.summary.tpl.labelSums'),
                    labelVat      : QUILocale.get(lg, 'article.summary.tpl.labelVat'),
                })
            });

            this.$Elm.addEvent('click', this.openSummary);

            this.$NettoSum = this.$Elm.getElement(
                '.quiqqer-erp-backend-temporaryErp-summary-total .netto-value'
            );

            this.$BruttoSum = this.$Elm.getElement(
                '.quiqqer-erp-backend-temporaryErp-summary-total .brutto-value'
            );

            this.$VAT = this.$Elm.getElement(
                '.quiqqer-erp-backend-temporaryErp-summary-total-vat .vat-value'
            );

            if (showPosSummary) {
                this.$ArticleNettoSum = this.$Elm.getElement(
                    '.quiqqer-erp-backend-temporaryErp-summary-pos .netto-value'
                );

                this.$ArticleBruttoSum = this.$Elm.getElement(
                    '.quiqqer-erp-backend-temporaryErp-summary-pos .brutto-value'
                );
            }

            if (this.getAttribute('styles')) {
                this.setStyles(this.getAttribute('styles'));
            }

            return this.$Elm;
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
         * event: on inject
         */
        $onInject: function () {
            const List = this.getAttribute('List');

            if (!List) {
                return;
            }

            List.addEvent('onCalc', this.$refreshArticleSelect);
            List.addEvent('onArticleSelect', this.$refreshArticleSelect);
        },

        /**
         * Open the summary with price factors
         */
        openSummary: function () {
            if (!this.getAttribute('List')) {
                return;
            }

            const self = this;

            require(['qui/controls/windows/Popup'], function (Popup) {
                new Popup({
                    title    : QUILocale.get('quiqqer/erp', 'article.summary.window.title'),
                    buttons  : false,
                    maxHeight: 600,
                    maxWidth : 600,
                    events   : {
                        onCreate: function (Win) {
                            Win.Loader.show();

                            self.$refreshSummaryContent(Win).then(function () {
                                Win.Loader.hide();
                            });
                        }
                    }
                }).open();
            });
        },

        $refreshSummaryContent: function (Win) {
            const self = this;

            return new Promise((resolve) => {
                this.getCurrencyFormatter().then(function (Formatter) {
                    const Content = Win.getContent();
                    const List = self.getAttribute('List');

                    let priceFactors = List.getPriceFactors();
                    let calculations = List.getCalculation();

                    for (let i = 0, len = priceFactors.length; i < len; i++) {
                        priceFactors[i].index = i;
                    }

                    Content.set('html', '');

                    require([
                        'text!package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary.Window.html'
                    ], (template) => {
                        if (typeof calculations.vatArray === 'undefined') {
                            calculations.vatArray = {};
                        }

                        Content.set('html', Mustache.render(template, {
                            priceFactors: priceFactors,
                            vatArray    : Object.values(calculations.vatArray)
                        }));

                        const Total = Content.getElement('.quiqqer-erp-backend-temporaryErp-summaryWin-total');
                        const calc = calculations.calculations;

                        Total.getElement('.netto-value').set('html', Formatter.format(calc.nettoSum));
                        Total.getElement('.brutto-value').set('html', Formatter.format(calc.sum));

                        Content.getElements(
                            '.quiqqer-erp-backend-temporaryErp-summaryWin-priceFactors'
                        ).addEvent('click', function (event) {
                            let index = event.target.getParent('tr').get('data-index');

                            List.removePriceFactor(index);
                            self.$refreshSummaryContent(Win);
                        });

                        resolve();
                    });
                });
            });
        },

        /**
         * event: onArticleSelect
         *
         * @param List
         * @param ArticleInstance
         */
        $refreshArticleSelect: function (List, ArticleInstance) {
            let calculated = List.getCalculation();

            if (typeof calculated.calculations === 'undefined') {
                return;
            }

            let calc = calculated.calculations;

            this.getCurrencyFormatter().then((Formatter) => {

                if (this.getAttribute('showPosSummary')) {
                    if (!(ArticleInstance instanceof Article)) {
                        ArticleInstance = List.getSelectedArticle();
                    }

                    if (ArticleInstance instanceof Article) {
                        let articleCalc = ArticleInstance.getCalculations();
                        let bruttoCalc = ArticleInstance.getBruttoCalc();

                        if (articleCalc && typeof articleCalc.nettoSum !== 'undefined') {
                            this.$ArticleNettoSum.set('html', Formatter.format(articleCalc.nettoSum));
                        } else {
                            this.$ArticleNettoSum.set('html', '---');
                        }

                        if (bruttoCalc && typeof bruttoCalc.sum !== 'undefined') {
                            this.$ArticleBruttoSum.set('html', Formatter.format(bruttoCalc.sum));
                        } else {
                            this.$ArticleBruttoSum.set('html', '---');
                        }
                    }
                }

                this.$NettoSum.set('html', Formatter.format(calc.nettoSum));
                this.$BruttoSum.set('html', Formatter.format(calc.sum));

                // vat display
                if (typeOf(calc.vatArray) === 'array' && !calc.vatArray.length) {
                    this.$VAT.set('html', '---');
                } else {
                    let key, Entry;
                    let vatText = '';

                    for (key in calc.vatArray) {
                        if (!calc.vatArray.hasOwnProperty(key)) {
                            continue;
                        }

                        Entry = calc.vatArray[key];

                        if (typeof Entry.sum === 'undefined') {
                            Entry.sum = 0;
                        }

                        if (typeof Entry.text === 'undefined') {
                            Entry.text = '';
                        }

                        if (Entry.text === '') {
                            Entry.text = '';
                        }

                        Entry.sum = parseFloat(Entry.sum);
                        vatText = vatText + Entry.text + ' (' + Formatter.format(Entry.sum) + ')<br />';
                    }

                    this.$VAT.set('html', vatText);
                }
            });
        }
    });
});
