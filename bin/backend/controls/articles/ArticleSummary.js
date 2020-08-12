/**
 * @module package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary
 * @author www.pcsg.de (Henning Leutz)
 *
 * Displays a article list
 */
define('package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary', [

    'qui/QUI',
    'qui/controls/Control',
    'Mustache',
    'Locale',

    'text!package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary.html',
    'css!package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary.css'

], function (QUI, QUIControl, Mustache, QUILocale, template) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary',

        options: {
            List    : null,
            styles  : false,
            currency: 'EUR'
        },

        Binds: [
            '$onInject',
            '$refreshArticleSelect',
            'openSummary'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$NettoSum  = null;
            this.$BruttoSum = null;

            this.$Formatter = QUILocale.getNumberFormatter({
                style                : 'currency',
                currency             : this.getAttribute('currency'),
                minimumFractionDigits: 2
            });

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
            this.$Elm = new Element('div', {
                'class': 'quiqqer-erp-backend-temporaryErp-summary',
                html   : Mustache.render(template)
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

            this.$ArticleNettoSum = this.$Elm.getElement(
                '.quiqqer-erp-backend-temporaryErp-summary-pos .netto-value'
            );

            this.$ArticleBruttoSum = this.$Elm.getElement(
                '.quiqqer-erp-backend-temporaryErp-summary-pos .brutto-value'
            );

            if (this.getAttribute('styles')) {
                this.setStyles(this.getAttribute('styles'));
            }

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var List = this.getAttribute('List');

            if (!List) {
                return;
            }

            var self = this;

            List.addEvent('onCalc', function (List) {
                var data = List.getCalculation();

                self.$NettoSum.set('html', self.$Formatter.format(data.nettoSum));
                self.$BruttoSum.set('html', self.$Formatter.format(data.sum));

                if (typeOf(data.vatArray) === 'array' && !data.vatArray.length) {
                    self.$VAT.set('html', '---');
                    return;
                }

                var key, Entry;
                var vatText = '';

                for (key in data.vatArray) {
                    if (!data.vatArray.hasOwnProperty(key)) {
                        continue;
                    }

                    Entry = data.vatArray[key];

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

                    vatText = vatText + Entry.text + ' (' + self.$Formatter.format(Entry.sum) + ')<br />';
                }

                self.$VAT.set('html', vatText);
            });

            List.addEvent('onArticleSelect', this.$refreshArticleSelect);
        },

        /**
         * Open the summary with price factors
         */
        openSummary: function () {
            if (!this.getAttribute('List')) {
                return;
            }

            var self = this;

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
            var self = this;

            return new Promise(function (resolve) {
                var Content      = Win.getContent();
                var List         = self.getAttribute('List');
                var priceFactors = List.getPriceFactors();
                var calculations = List.getCalculation();

                for (var i = 0, len = priceFactors.length; i < len; i++) {
                    priceFactors[i].index = i;
                }

                Content.set('html', '');

                require([
                    'text!package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary.Window.html'
                ], function (template) {
                    if (typeof calculations.vatArray === 'undefined') {
                        calculations.vatArray = {};
                    }

                    Content.set('html', Mustache.render(template, {
                        priceFactors: priceFactors,
                        vatArray    : Object.values(calculations.vatArray)
                    }));

                    var Total = Content.getElement('.quiqqer-erp-backend-temporaryErp-summaryWin-total');

                    Total.getElement('.netto-value').set('html', calculations.nettoSum);
                    Total.getElement('.brutto-value').set('html', calculations.sum);

                    Content.getElements(
                        '.quiqqer-erp-backend-temporaryErp-summaryWin-priceFactors'
                    ).addEvent('click', function (event) {
                        var index = event.target.getParent('tr').get('data-index');

                        List.removePriceFactor(index);
                        self.$refreshSummaryContent(Win);
                    });

                    resolve();
                });
            });
        },

        /**
         * event: onArticleSelect
         *
         * @param List
         * @param Article
         */
        $refreshArticleSelect: function (List, Article) {
            var self = this;

            require(['Ajax'], function (QUIAjax) {
                QUIAjax.get('package_quiqqer_erp_ajax_products_summary', function (result) {
                    console.warn('##################');
                    console.warn(result);

                    self.$ArticleNettoSum.set(
                        'html',
                        self.$Formatter.format(result.calculated.nettoSum)
                    );

                    self.$ArticleBruttoSum.set(
                        'html',
                        self.$Formatter.format(result.calculated.sum)
                    );
                }, {
                    'package': 'quiqqer/erp',
                    article  : JSON.encode(Article.getAttributes())
                });
            });
        }
    });
});
