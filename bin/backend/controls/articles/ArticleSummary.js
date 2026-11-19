/**
 *
 * Displays an article list
 */
define('package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/erp/bin/backend/controls/articles/Article',
    'package/quiqqer/erp/bin/backend/controls/articles/TaxAmount',
    'package/quiqqer/currency/bin/Currency',
    'Mustache',
    'Locale',

    'text!package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary.html',
    'text!package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary.PriceFactors.html',
    'css!package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary.css'

], function (QUI, QUIControl, Article, TaxAmount, Currency, Mustache, QUILocale, template, templatePriceFactor) {
    "use strict";

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary',

        options: {
            List: null,
            styles: false,
            currency: 'EUR',
            manualTotalsInline: false,
            openOnClick: true,

            showPosSummary: true // show summary for selected article
        },

        Binds: [
            '$onInject',
            '$refreshArticleSelect',
            'openSummary',
            'showPriceFactors',
            'hidePriceFactors'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$NettoSum = null;
            this.$BruttoSum = null;
            this.$PriceFactors = null;
            this.$PFFX = null;
            this.$destroyed = false;
            this.$List = null;
            this.$listObserver = null;
            this.$visible = false;
            this.$visibilityInitialized = false;
            this.$visibilityAnimation = null;
            this.$priceFactorVersion = 0;
            this.$taxControls = new Map();
            this.$SummaryWindow = null;
            this.$summaryWindowLoading = false;
            this.addEvent('onDestroy', () => {
                this.$destroyed = true;
                this.$SummaryWindow?.close();
                this.$priceFactorVersion++;
                this.$List?.removeEvent('onCalc', this.$refreshArticleSelect);
                this.$List?.removeEvent('onArticleSelect', this.$refreshArticleSelect);
                this.$List = null;
                this.$listObserver?.disconnect();
                this.$visibilityAnimation?.cancel();
                this.$PFFX?.stop?.();
                this.$taxControls.forEach((Tax) => Tax.destroy());
                this.$taxControls.clear();
            });

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Create the dom-node element
         *
         * @returns {HTMLDivElement}
         */
        create: function () {
            const showPosSummary = this.getAttribute('showPosSummary');

            this.$Elm = document.createElement('div');
            this.$Elm.dataset.qui = this.Type;
            this.$Elm.className = 'quiqqer-erp-backend-temporaryErp-summary';
            this.$Elm.classList.toggle('quiqqer-erp-summary-display', !this.getAttribute('manualTotalsInline'));
            this.$Elm.hidden = true;
            this.$Elm.innerHTML = Mustache.render(template, {
                showPosSummary: showPosSummary,
                labelPosInfo: QUILocale.get(lg, 'article.summary.tpl.labelPosInfo'),
                labelNet: QUILocale.get(lg, 'article.summary.tpl.labelNet'),
                labelGross: QUILocale.get(lg, 'article.summary.tpl.labelGross'),
                labelSums: QUILocale.get(lg, 'article.summary.tpl.labelSums'),
                labelVat: QUILocale.get(lg, 'article.summary.tpl.labelVat'),
                labelAddTax: QUILocale.get(lg, 'manual.addTax'),
                labelFromArticles: QUILocale.get(lg, 'manual.totalsFromArticles'),
                hintFromArticles: QUILocale.get(lg, 'manual.totalsFromArticlesHint'),
                labelTotalsActions: QUILocale.get(lg, 'manual.totalsActions'),
                labelOtherTotals: QUILocale.get(lg, 'manual.otherTotals'),
                labelNetBefore: QUILocale.get(lg, 'manual.netBeforeFactors'),
                labelSubtotal: QUILocale.get(lg, 'article.list.articles.subtotal'),
                labelGrossBefore: QUILocale.get(lg, 'manual.grossBeforeFactors'),
                labelOpenTotals: QUILocale.get(lg, 'article.summary.totalsWindow.title')
            });
            this.$Elm.addEventListener('mouseenter', this.showPriceFactors);
            this.$Elm.addEventListener('mouseleave', this.hidePriceFactors);

            this.$Elm.addEventListener('click', (event) => {
                if (!event.target.closest('input, select, button, details, label')) {
                    this.openSummary();
                }
            });

            this.$Elm.querySelectorAll('[data-name="manual-total"]').forEach((Input) => {
                Input.addEventListener('change', () => this.$setManualTotal(Input.dataset.field, Input));
            });

            this.$Elm.querySelector('[data-name="open-totals"]').addEventListener('click', this.openSummary);

            this.$Elm.querySelector('[data-name="add-manual-tax"]').addEventListener('click', () => {
                const List = this.getAttribute('List');
                const data = List.getCalculations();

                if (!Object.hasOwn(data.calculations.vatArray, '0')) {
                    data.calculations.vatArray['0'] = {vat: 0, sum: 0, text: '0%'};
                    List.setCalculations(data);
                }

                this.$taxControls.get('0')?.focusRate();
            });
            this.$Elm.querySelector('[data-name="totals-from-articles"]').addEventListener('click', () => {
                this.$setTotalsFromArticles();
            });
            this.$PriceFactors = document.createElement('div');
            this.$PriceFactors.className = 'quiqqer-erp-backend-temporaryErp-priceFactors';
            this.$Elm.appendChild(this.$PriceFactors);

            this.$PFFX = moofx(this.$PriceFactors);

            this.$NettoSum = this.$Elm.querySelector('[data-name="net-total"]');
            this.$BruttoSum = this.$Elm.querySelector('[data-name="gross-total"]');
            this.$VAT = this.$Elm.querySelector('[data-name="tax-totals"]');

            if (showPosSummary) {
                this.$ArticleNettoSum = this.$Elm.querySelector('[data-name="position-net-total"]');
                this.$ArticleBruttoSum = this.$Elm.querySelector('[data-name="position-gross-total"]');
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
                    const Formatter = QUILocale.getNumberFormatter({
                        style: 'currency',
                        currency: currency.code,
                        minimumFractionDigits: currency.precision,
                        maximumFractionDigits: currency.precision
                    });

                    if (!this.$destroyed) {
                        this.$Formatter = Formatter;
                    }

                    resolve(Formatter);
                });
            });
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            const List = this.getAttribute('List');

            if (this.$destroyed || !List) {
                return;
            }

            this.$List?.removeEvent('onCalc', this.$refreshArticleSelect);
            this.$List?.removeEvent('onArticleSelect', this.$refreshArticleSelect);
            this.$listObserver?.disconnect();
            this.$List = List;
            List.addEvent('onCalc', this.$refreshArticleSelect);
            List.addEvent('onArticleSelect', this.$refreshArticleSelect);

            // Text positions and clear() do not trigger a calculation event.
            const Items = List.getElm().querySelector('[data-name="items"]');

            if (Items) {
                this.$listObserver = new MutationObserver(() => {
                    if (!this.$destroyed && (List.count() > 0) !== this.$visible) {
                        this.$refreshArticleSelect(List);
                    }
                });
                this.$listObserver.observe(Items, {childList: true});
            }

            this.$refreshArticleSelect(List);
        },

        //region Visibility

        $refreshVisibility: function (List) {
            const visible = List.count() > 0;
            const initialized = this.$visibilityInitialized;
            const changed = visible !== this.$visible;
            this.$visibilityInitialized = true;
            this.$visible = visible;
            this.$Elm.hidden = !visible;

            if (!changed) {
                return visible;
            }

            this.$visibilityAnimation?.cancel();
            this.$visibilityAnimation = null;

            // Existing panels animate their initial opacity/bottom themselves.
            if (visible && initialized && this.$Elm.style.opacity !== '0' &&
                !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                const Animation = this.$Elm.animate([
                    {transform: 'translateY(20px)', opacity: 0},
                    {transform: 'translateY(0)', opacity: 1}
                ], {duration: 180, easing: 'ease-out'});

                this.$visibilityAnimation = Animation;
                Animation.addEventListener('finish', () => {
                    if (this.$visibilityAnimation === Animation) {
                        this.$visibilityAnimation = null;
                    }
                }, {once: true});
            }

            return visible;
        },

        //endregion

        // region summary

        /**
         * Open the summary with price factors
         */
        openSummary: function () {
            const List = this.getAttribute('List');

            if (this.$destroyed || !List || this.getAttribute('openOnClick') === false ||
                this.$SummaryWindow || this.$summaryWindowLoading) {
                return;
            }

            if (List.getCalculationMode() === 'manual' && this.getAttribute('manualTotalsInline')) {
                return;
            }

            this.$summaryWindowLoading = true;
            const ReturnFocus = document.activeElement;

            require([
                'package/quiqqer/erp/bin/backend/controls/articles/windows/PriceFactors'
            ], (TotalsWindow) => {
                this.$summaryWindowLoading = false;

                if (this.$destroyed || List !== this.getAttribute('List') || List.count() === 0) {
                    return;
                }

                this.$SummaryWindow = new TotalsWindow({
                    ArticleList: List,
                    events: {
                        onCloseBegin: (Win) => {
                            if (this.$SummaryWindow === Win) {
                                this.$SummaryWindow = null;
                            }
                        },
                        onClose: () => {
                            if (!this.$destroyed && !this.$SummaryWindow && ReturnFocus?.isConnected) {
                                ReturnFocus.focus();
                            }
                        }
                    }
                });
                this.$SummaryWindow.open();
            });
        },

        $refreshSummaryContent: function (Win) {
            if (this.$destroyed) {
                return Promise.resolve();
            }

            const self = this;

            return new Promise((resolve) => {
                this.getCurrencyFormatter().then(function (Formatter) {
                    if (self.$destroyed) {
                        resolve();
                        return;
                    }

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
                        if (self.$destroyed) {
                            resolve();
                            return;
                        }

                        if (typeof calculations.vatArray === 'undefined') {
                            calculations.vatArray = {};
                        }

                        Content.set('html', Mustache.render(template, {
                            priceFactors: priceFactors,
                            vatArray: Object.values(calculations.vatArray)
                        }));

                        const Total = Content.getElement('.quiqqer-erp-backend-temporaryErp-summaryWin-total');
                        const calc = calculations.calculations;

                        Total.getElement('.netto-value').set('html', Formatter.format(calc.nettoSum));
                        Total.getElement('.brutto-value').set('html', Formatter.format(calc.sum));

                        Content.getElements(
                            '.quiqqer-invoice-backend-temporaryInvoice-summaryWin-priceFactors button'
                        ).addEvent('click', function (event) {
                            if (self.$destroyed) {
                                return;
                            }

                            let index = event.target.getParent('tr').get('data-index');

                            List.removePriceFactor(index);
                            self.$refreshSummaryContent(Win);
                        });

                        resolve();
                    });
                });
            });
        },

        //endregion

        /**
         * event: onArticleSelect
         *
         * @param List
         * @param ArticleInstance
         */
        $refreshArticleSelect: function (List, ArticleInstance) {
            if (this.$destroyed || !this.$Elm) {
                return;
            }

            const manual = List.getCalculationMode() === 'manual';

            if (this.$SummaryWindow && List.count() === 0) {
                this.$SummaryWindow.close();
            }

            if (!this.$refreshVisibility(List)) {
                return;
            }

            const calculationSnapshot = List.getCalculations().calculations;
            const calc = calculationSnapshot || {nettoSum: 0, sum: 0, vatArray: {}};

            const inline = manual && this.getAttribute('manualTotalsInline');
            this.$Elm.classList.toggle('quiqqer-erp-summary-manual', inline);
            this.$Elm.classList.toggle('quiqqer-erp-summary-editable', manual && !inline);
            this.$Elm.querySelector('[data-name="manual-extra"]').hidden = !inline;
            this.$Elm.querySelector('[data-name="manual-actions"]').hidden = !inline;
            const OpenTotals = this.$Elm.querySelector('[data-name="open-totals"]');
            const label = QUILocale.get(lg, manual ? 'manual.editTotals' : 'article.summary.totalsWindow.title');
            OpenTotals.hidden = this.getAttribute('manualTotalsInline') || this.getAttribute('openOnClick') === false;
            OpenTotals.title = label;
            OpenTotals.setAttribute('aria-label', label);
            this.$Elm.querySelector('[data-name="totals-icon"]').className = manual ? 'fa fa-edit' : 'fa fa-calculator';

            if (inline) {
                this.$renderManualTotals(List, calc);
                return;
            }

            this.$manualVatKeys = null;
            this.$taxControls.forEach((Tax) => Tax.destroy());
            this.$taxControls.clear();

            this.getCurrencyFormatter().then((Formatter) => {
                if (this.$destroyed || !this.$Elm || !this.$visible ||
                    (List.getCalculationMode() === 'manual') !== manual ||
                    (manual && this.getAttribute('manualTotalsInline')) ||
                    List.getCalculations().calculations !== calculationSnapshot) {
                    return;
                }

                if (this.getAttribute('showPosSummary')) {
                    if (!(ArticleInstance instanceof Article)) {
                        ArticleInstance = List.getSelectedArticle();
                    }

                    this.$Elm.querySelector('[data-name="position-summary"]').hidden = !(ArticleInstance instanceof Article);

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

                if (manual) {
                    this.$VAT.replaceChildren();
                    const taxes = Object.values(calc.vatArray || {});

                    if (taxes.length === 0) {
                        this.$VAT.textContent = '---';
                    }

                    taxes.forEach((tax) => {
                        const Row = document.createElement('div');
                        Row.textContent = (tax.text || '') + ' (' + Formatter.format(tax.sum ?? 0) + ')';
                        this.$VAT.appendChild(Row);
                    });
                    return;
                }

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
        },

        //region Manual amounts

        $setManualTotal: function (field, Input) {
            if (!Input.checkValidity() || !Number.isFinite(Input.valueAsNumber)) {
                Input.reportValidity();
                return;
            }

            const List = this.getAttribute('List');
            const data = List.getCalculations();
            data.calculations[field] = Input.valueAsNumber;
            List.setCalculations(data);
        },

        $setTotalsFromArticles: function () {
            const List = this.getAttribute('List');

            if (List.getCalculationMode() !== 'manual') {
                return;
            }

            // Add the entered decimal amounts without introducing floating-point tails.
            const sumAmounts = (values) => {
                let total = 0n;
                let scale = 0;

                values.forEach((value) => {
                    const [coefficient, exponent = '0'] = String(value).split('e');
                    const decimals = (coefficient.split('.')[1] || '').length - Number(exponent);
                    const nextScale = Math.max(scale, decimals, 0);
                    const amount = BigInt(coefficient.replace('.', ''));
                    total = total * 10n ** BigInt(nextScale - scale) +
                        amount * 10n ** BigInt(nextScale - decimals);
                    scale = nextScale;
                });

                const sign = total < 0n ? '-' : '';
                const digits = (total < 0n ? -total : total).toString().padStart(scale + 1, '0');

                return scale ? Number(sign + digits.slice(0, -scale) + '.' + digits.slice(-scale)) : Number(total);
            };
            const netAmounts = [];
            const grossAmounts = [];
            const taxAmounts = new Map();
            const vatArray = {};
            const vatText = {};

            List.getArticles().forEach((Article) => {
                const amounts = Article.getCalculations();
                const gross = Article.getBruttoCalc();
                const tax = amounts.vatArray;
                const rate = String(tax.vat);
                netAmounts.push(amounts.nettoSum);
                grossAmounts.push(gross.sum);

                if (!taxAmounts.has(rate)) {
                    taxAmounts.set(rate, []);
                    vatArray[rate] = Object.assign({}, tax);
                    vatText[rate] = tax.text || rate + '%';
                    vatArray[rate].text = vatText[rate];
                }

                taxAmounts.get(rate).push(tax.sum);
            });

            taxAmounts.forEach((amounts, rate) => {
                vatArray[rate].sum = sumAmounts(amounts);
            });

            const data = List.getCalculations();
            const calc = data.calculations;
            calc.nettoSum = sumAmounts(netAmounts);
            calc.nettoSubSum = calc.nettoSum;
            calc.sum = sumAmounts(grossAmounts);
            calc.grandSubSum = calc.sum;
            calc.subSum = calc.isNetto === false ? calc.sum : calc.nettoSum;
            calc.vatArray = vatArray;
            calc.vatText = vatText;
            calc.vatSum = sumAmounts(Object.values(vatArray).map((tax) => tax.sum));
            List.setCalculations(data);
        },

        $renderManualTotals: function (List, calc) {
            const totals = [[this.$NettoSum, 'nettoSum'], [this.$BruttoSum, 'sum']];

            totals.forEach(([Container, field]) => {
                let Input = Container.querySelector('[data-name="manual-total"]');

                if (!Input) {
                    Container.replaceChildren();
                    Input = document.createElement('input');
                    Input.type = 'number';
                    Input.step = 'any';
                    Input.required = true;
                    Input.dataset.name = 'manual-total';
                    Input.dataset.field = field;
                    Input.setAttribute('aria-label', QUILocale.get(lg, field === 'sum' ?
                        'article.summary.tpl.labelGross' : 'article.summary.tpl.labelNet'));
                    Input.addEventListener('change', () => this.$setManualTotal(field, Input));
                    Container.appendChild(Input);
                }

                if (Input !== document.activeElement) {
                    Input.value = calc[field];
                }
            });

            this.$Elm.querySelectorAll('[data-name="manual-total"]').forEach((Input) => {
                if (Input !== document.activeElement) {
                    Input.value = calc[Input.dataset.field] ?? 0;
                }
            });

            const keys = Object.keys(calc.vatArray || {});

            if (this.$manualVatKeys !== JSON.stringify(keys) || this.$manualTaxData !== calc) {
                this.$taxControls.forEach((Tax) => Tax.destroy());
                this.$taxControls.clear();
                this.$VAT.replaceChildren();

                keys.forEach((rate) => {
                    const Tax = new TaxAmount({
                        rate: Number(rate),
                        amount: calc.vatArray[rate].sum,
                        validateRate: (value) => {
                            if (String(value) !== rate && Object.hasOwn(calc.vatArray, String(value))) {
                                return QUILocale.get(lg, 'manual.taxRateExists');
                            }

                            return '';
                        },
                        events: {
                            onRateChange: (Control, value) => {
                                const next = String(value);
                                calc.vatArray[next] = Object.assign({}, calc.vatArray[rate], {
                                    vat: value,
                                    text: next + '%'
                                });

                                if (next !== rate) {
                                    delete calc.vatArray[rate];
                                    delete calc.vatText[rate];
                                }

                                calc.vatText[next] = next + '%';
                                List.setCalculations(List.getCalculations());
                            },
                            onAmountChange: (Control, value) => {
                                calc.vatArray[rate].sum = value;
                                List.setCalculations(List.getCalculations());
                            },
                            onRemove: () => {
                                delete calc.vatArray[rate];
                                delete calc.vatText[rate];
                                List.setCalculations(List.getCalculations());

                                const Tax = this.$taxControls.values().next().value;

                                if (Tax) {
                                    Tax.focusRate();
                                } else {
                                    this.$Elm.querySelector('[data-name="add-manual-tax"]').focus();
                                }
                            }
                        }
                    }).inject(this.$VAT);

                    this.$taxControls.set(rate, Tax);
                });

                this.$manualVatKeys = JSON.stringify(keys);
                this.$manualTaxData = calc;
            }

            this.$taxControls.forEach((Tax, rate) => Tax.setAmount(calc.vatArray[rate].sum));
        },

        //endregion

        showPriceFactors: function () {
            if (this.$destroyed || !this.$Elm) {
                return;
            }

            const ArticleList = this.getAttribute('List');

            if (!ArticleList || ArticleList.getCalculationMode() === 'manual' ||
                this.getAttribute('openOnClick') === false) {
                return;
            }
            const version = ++this.$priceFactorVersion;
            let priceFactors = ArticleList.getPriceFactors();
            let calculated = ArticleList.getCalculations();

            if (typeof calculated.calculations === 'undefined') {
                calculated.calculations = {};
            }

            if (!calculated.calculations.vatArray ||
                typeOf(calculated.calculations.vatArray) !== 'object') {
                calculated.calculations.vatArray = {};
            }

            this.getCurrencyFormatter().then(() => {
                if (this.$destroyed || !this.$Elm || version !== this.$priceFactorVersion) {
                    return;
                }

                const vat = Object.entries(calculated.calculations.vatArray).map((val) => {
                    return {
                        text: val[1].text,
                        sum: this.$Formatter.format(val[1].sum)
                    };
                });

                this.$PriceFactors.innerHTML = Mustache.render(templatePriceFactor, {
                    valueSubSum: calculated.calculations.display_subSum,
                    valueSum: calculated.calculations.display_sum,
                    vat: vat,
                    textSubSum: QUILocale.get(lg, 'article.list.articles.subtotal'),
                    textSum: QUILocale.get(lg, 'article.list.articles.sumtotal'),
                    priceFactors: priceFactors
                });

                this.$PriceFactors.style.opacity = '0';
                this.$PriceFactors.style.display = 'block';
                this.$PriceFactors.style.bottom = '70px';

                this.$PFFX.animate({
                    bottom: 80,
                    opacity: 1
                }, {
                    duration: 300
                });
            });
        },

        hidePriceFactors: function () {
            const version = ++this.$priceFactorVersion;

            if (this.$destroyed || !this.$Elm) {
                return;
            }

            this.$PFFX.animate({
                bottom: 70,
                opacity: 0
            }, {
                duration: 300,
                callback: () => {
                    if (!this.$destroyed && this.$Elm && version === this.$priceFactorVersion) {
                        this.$PriceFactors.style.display = 'none';
                    }
                }
            });
        }
    });
});
