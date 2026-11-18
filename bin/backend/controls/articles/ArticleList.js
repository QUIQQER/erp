/**
 *
 * Article list (Produkte Positionen)
 *
 * @event onCalc [self, {Object} calculation]
 * @event onArticleSelect [self, {Object} Article]
 * @event onArticleUnSelect [self, {Object} Article]
 * @event onArticleReplaceClick [self, {Object} Article]
 */
define('package/quiqqer/erp/bin/backend/controls/articles/ArticleList', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/erp/bin/backend/controls/articles/PriceDisplay',
    'qui/controls/loader/Loader',
    'Mustache',
    'Ajax',
    'Locale',
    'Packages',

    'package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow',
    'package/quiqqer/erp/bin/backend/controls/articles/Article',
    'package/quiqqer/erp/bin/backend/controls/articles/CalculationMode',
    'package/quiqqer/erp/bin/backend/classes/Sortable',

    'text!package/quiqqer/erp/bin/backend/controls/articles/ArticleList.html',
    'text!package/quiqqer/erp/bin/backend/controls/articles/ArticleList.sortablePlaceholder.html',
    'css!package/quiqqer/erp/bin/backend/controls/articles/ArticleList.css'

], function (QUI, QUIControl, PriceDisplay, QUILoader, Mustache,
             QUIAjax, QUILocale, Packages, AddProductWindow, Article, CalculationMode, Sortables, template, templateSortablePlaceholder
) {
    'use strict';

    const lg = 'quiqqer/erp';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/erp/bin/backend/controls/articles/ArticleList',

        Binds: [
            '$onArticleDelete',
            '$onArticleSelect',
            '$onArticleUnSelect',
            '$onArticleReplace',
            '$onArticleCalc',
            '$calc',
            '$onInject',
            '$executeCalculation',
            '$refreshNettoBruttoDisplay',
            '$getArticleDataForCalculation',
            '$onArticleEditCustomFields'
        ],

        options: {
            currency: false, // bool || string -> EUR, USD ...
            nettoinput: true
        },

        initialize: function (options) {
            this.parent(options);

            this.$articles = [];
            this.$user = {};
            this.$sorting = false;

            this.$calculationTimer = null;
            this.$isIncalculationFrame = false;

            this.$calculationMode = 'automatic';
            this.$calculationVersion = 0;

            this.$calculations = {
                currencyData: {},
                isEuVat: 0,
                isNetto: true,
                nettoSubSum: 0,
                nettoSum: 0,
                subSum: 0,
                sum: 0,
                vatArray: [],
                vatText: []
            };

            this.$Container = null;
            this.$Sortables = null;
            this.$priceFactors = [];
            this.$PriceDisplay = null;
            this.$shopIsNetto = null;
            this.$shopPricingRequest = null;
            this.$destroyed = false;

            this.addEvents({
                onInject: this.$onInject,
                onDestroy: () => {
                    this.$destroyed = true;
                }
            });
        },

        /**
         * Create the DOMNode element
         *
         * @returns {HTMLDivElement}
         */
        create: function () {
            const self = this;

            this.$Elm = this.parent();
            this.$Elm.addClass('quiqqer-erp-backend-erpItems');
            this.$Elm.set('data-qui', 'package/quiqqer/erp/bin/backend/controls/articles/ArticleList');

            this.$Elm.innerHTML = template;

            this.$Loader = new QUILoader().inject(this.$Elm);

            if (this.getAttribute('styles')) {
                this.setStyles(this.getAttribute('styles'));
            }

            this.$Container = this.$Elm.querySelector('[data-name="items"]');
            this.$PriceDisplay = new PriceDisplay({
                netto: !!this.getAttribute('nettoinput'),
                events: {
                    onChange: (Control, netto) => {
                        this.$Loader.show();
                        this.setAttribute('nettoinput', netto);
                        this.$refreshNettoBruttoDisplay();
                        this.$calc().finally(() => this.$Loader.hide()).catch(console.error);
                    }
                }
            }).inject(this.$Elm.querySelector('[data-name="price-display"]'));
            this.addEvent('onDestroy', () => this.$PriceDisplay.destroy());

            this.$refreshNettoBruttoDisplay();
            this.$loadShopPricing();

            this.$Mode = new CalculationMode({
                ArticleList: this
            }).inject(this.$Elm.querySelector('[data-name="calculation-mode"]'));
            this.addEvent('onDestroy', () => this.$Mode.destroy());

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            (function () {
                if (this.$articles.length) {
                    this.$articles[0].select();
                }
            }).delay(500, this);
        },

        /**
         * Serialize the list
         *
         * @returns {Object}
         */
        serialize: function () {
            return {
                articles: this.save(),
                calculationMode: this.getCalculationMode(),
                calculations: this.$calculations.calculations,
                priceFactors: this.$priceFactors
            };
        },

        getArticles: function () {
            return this.$getArticles();
        },

        /**
         * Get internal article list
         *
         * @return {[]}
         */
        $getArticles: function () {
            return this.$articles;
        },

        clear: function () {
            this.$articles = [];
            this.$priceFactors = [];
            this.$Container.innerHTML = '';
        },

        /**
         * Return the articles count
         *
         * @returns {number}
         */
        count: function () {
            return this.$articles.length;
        },

        /**
         * Unserialize the list
         *
         * load the serialized list into this list
         * current articles would be deleted
         *
         * @param {Object|String} list
         * @return {Promise}
         */
        unserialize: function (list) {
            const self = this;
            let data = {};

            if (typeOf(list) === 'string') {
                try {
                    data = JSON.parse(list);
                } catch (e) {
                }
            } else {
                data = list;
            }

            if ('priceFactors' in data) {
                this.$priceFactors = data.priceFactors;
            }

            if ('calculations' in data) {
                this.$calculations.calculations = data.calculations;

                if (typeof data.calculations.isNetto !== 'undefined' && data.calculations.isNetto === false) {
                    this.setAttribute('nettoinput', false);
                    this.$refreshNettoBruttoDisplay();
                }
            }

            this.setCalculationMode(data.calculationMode || 'automatic');

            if (!('articles' in data)) {
                return Promise.resolve();
            }

            this.$articles = [];
            let selectedPosition = null;

            if (this.$Container) {
                if (this.$selectedArticle) {
                    selectedPosition = this.$selectedArticle.getAttribute('position');
                }

                this.$Container.set('html', '');
            }

            const controls = data.articles.map(function (ArticleInstance) {
                if (typeof ArticleInstance.control !== 'undefined' && ArticleInstance.control !== '') {
                    return ArticleInstance.control;
                }

                return 'package/quiqqer/erp/bin/backend/controls/articles/Article';
            }).unique();

            return new Promise((resolve) => {
                require(controls, function () { // dont use () => {
                    let i, len, article, index;

                    for (i = 0, len = data.articles.length; i < len; i++) {
                        article = data.articles[i];
                        index = controls.indexOf(article.control);

                        if (index === -1) {
                            self.addArticle(new Article(article));
                            continue;
                        }

                        try {
                            self.addArticle(new arguments[index](article));
                        } catch (e) {
                            console.log(e);
                        }
                    }

                    if (selectedPosition && typeof self.$articles[selectedPosition - 1] !== 'undefined') {
                        self.$articles[selectedPosition - 1].select();
                    }

                    self.fireEvent('calc', [
                        self,
                        self.$calculations
                    ]);
                    if (self.getCalculationMode() === 'manual') {
                        self.$syncManualArticles();
                    }
                    resolve();
                });
            });
        },

        /**
         * Set user details to the list
         *
         * @param {Object} user
         */
        setUser: function (user) {
            this.$user = user;

            this.$articles.each(function (ArticleInstance) {
                ArticleInstance.setUser(this.$user);
            }.bind(this));
        },

        /**
         * Return the user details
         *
         * @return {Object|*|{}}
         */
        getUser: function () {
            return this.$user;
        },

        /**
         * Add a product to the list
         * The product must be an instance of Article
         *
         * @param {Object} Child
         */
        addArticle: function (Child) {
            if (typeof Child !== 'object') {
                return;
            }

            if (!(Child instanceof Article)) {
                return;
            }

            if (this.getAttribute('currency')) {
                Child.setCurrency(this.getAttribute('currency'));
            }

            Child.setCalculationMode(this.getCalculationMode());

            this.$articles.push(Child);

            Child.setUser(this.$user);
            Child.setPosition(this.$articles.length);
            Child.setAttribute('List', this);

            Child.addEvents({
                onDelete: this.$onArticleDelete,
                onSelect: this.$onArticleSelect,
                onUnSelect: this.$onArticleUnSelect,
                onReplace: this.$onArticleReplace,
                onEditCustomFields: this.$onArticleEditCustomFields,
                onCalc: this.$onArticleCalc
            });

            if (this.$Container) {
                Child.inject(this.$Container);
            }

            Child.getElm().addClass('article');
        },

        /**
         * Replace an article with another
         *
         * @param {Object} NewArticle
         * @param {Number} position
         */
        replaceArticle: function (NewArticle, position) {
            if (typeof NewArticle !== 'object') {
                return;
            }

            if (!(NewArticle instanceof Article)) {
                return;
            }

            const Wanted = this.$articles.find(function (ArticleInstance) {
                return ArticleInstance.getAttribute('position') === position;
            });

            this.addArticle(NewArticle);

            if (Wanted) {
                NewArticle.getElm().inject(Wanted.getElm(), 'after');
                Wanted.remove();
            }

            NewArticle.setPosition(position);

            this.$recalculatePositions();

            return this.$calc();
        },

        /**
         * Insert a new empty product
         */
        insertNewProduct: function () {
            this.addArticle(new Article());
        },

        /**
         * Return the articles as an array
         *
         * @return {Array}
         */
        save: function () {
            return this.$articles.map(function (ArticleInstance) {
                const data = Object.assign({}, ArticleInstance.getAttributes(), {
                    control: ArticleInstance.getType()
                });
                delete data.List;
                delete data.User;
                return data;
            });
        },

        /**
         * Calculate the list
         */
        $calc: function () {
            if (this.getCalculationMode() === 'manual') {
                return Promise.resolve(this.$calculations);
            }

            return new Promise((resolve) => {
                if (this.$calculationTimer) {
                    clearTimeout(this.$calculationTimer);
                    this.$calculationTimer = null;
                }

                const self = this;

                this.$calculationTimer = (function () {
                    self.$executeCalculation().then(resolve);
                }).delay(500);
            });
        },

        /**
         * Calc
         */

        /**
         * Execute a new calculation
         *
         * @returns {Promise}
         */
        $executeCalculation: function () {
            if (this.getCalculationMode() === 'manual') {
                return Promise.resolve(this.$calculations);
            }

            const self = this;
            const version = this.$calculationVersion;

            if (this.$isIncalculationFrame) {
                this.fireEvent('calc', [
                    this,
                    this.$calculations
                ]);

                return Promise.resolve(this.$calculations);
            }

            if (this.$calculationRunning) {
                return new Promise((resolve) => {
                    const trigger = () => {
                        resolve(this.$calculations);
                        this.removeEvent('onCalc', trigger);
                    };

                    this.addEvent('onCalc', trigger);
                });
            }

            this.$calculationRunning = true;

            return new Promise((resolve, reject) => {
                const articles = this.$getArticleDataForCalculation();

                QUIAjax.get('package_quiqqer_erp_ajax_products_calc', (result) => {
                    if (this.getCalculationMode() === 'manual' || version !== this.$calculationVersion) {
                        resolve(this.$calculations);
                        return;
                    }

                    this.$calculations = result;
                    this.$priceFactors = result.priceFactors;

                    this.$isIncalculationFrame = true;
                    this.$calculationRunning = false;

                    // performance double request -> quiqqer/invoice#104
                    setTimeout(() => {
                        if (version === self.$calculationVersion) {
                            self.$isIncalculationFrame = false;
                        }
                    }, 100);

                    this.fireEvent('calc', [
                        this,
                        result
                    ]);

                    resolve(result);
                }, {
                    'package': 'quiqqer/erp',
                    articles: JSON.encode({articles: articles}),
                    priceFactors: JSON.encode(this.getPriceFactors()),
                    user: JSON.encode(this.$user),
                    currency: this.getAttribute('currency'),
                    nettoInput: this.getAttribute('nettoinput') ? 1 : 0,
                    onError: (err) => {
                        if (version !== this.$calculationVersion) {
                            resolve(this.$calculations);
                            return;
                        }

                        this.$calculationRunning = false;
                        console.error(err);
                        reject();
                    }
                });
            });
        },

        /**
         * Get article data used for calculation
         *
         * @return {Array}
         */
        $getArticleDataForCalculation: function () {
            return this.save().map((data) => {
                delete data.calculated;
                return data;
            });
        },

        /**
         * @deprecated use getCalculations()
         */
        getCalculation: function () {
            return this.getCalculations();
        },

        /**
         * Return the current calculations
         *
         * @returns {{currencyData: {}, isEuVat: number, isNetto: boolean, nettoSubSum: number, nettoSum: number, subSum: number, sum: number, vatArray: Array, vatText: Array}|*}
         */
        getCalculations: function () {
            return this.$calculations;
        },

        setCalculations: function (calculations) {
            this.$calculations = calculations;

            this.fireEvent('calc', [
                this,
                this.$calculations
            ]);
        },

        //region Calculation mode

        getCalculationMode: function () {
            return this.$calculationMode || 'automatic';
        },

        changeCalculationMode: function (mode) {
            this.setCalculationMode(mode);
            const calculated = mode === 'automatic' ? this.$executeCalculation() : Promise.resolve();

            return calculated.then(() => Promise.all(this.$articles.map((Article) => Article.calc())));
        },

        setCalculationMode: function (mode) {
            const calculationMode = mode === 'manual' ? 'manual' : 'automatic';

            if (this.getCalculationMode() !== calculationMode) {
                this.$calculationVersion = (this.$calculationVersion || 0) + 1;
                this.$calculationRunning = false;
                this.$isIncalculationFrame = false;
            }

            this.$calculationMode = calculationMode;
            this.$calculations.calculationMode = this.getCalculationMode();

            if (this.$Elm) {
                const manual = calculationMode === 'manual';
                this.$Elm.classList.toggle('quiqqer-erp-backend-erpItems-manual', manual);
            }

            if (calculationMode === 'automatic') {
                delete this.$calculations.calculate;
            } else {
                this.$calculations.calculate = 0;
                this.$calculations.calculations = this.$calculations.calculations || {
                    sum: 0,
                    subSum: 0,
                    grandSubSum: 0,
                    nettoSum: 0,
                    nettoSubSum: 0,
                    vatArray: {},
                    vatText: {},
                    isNetto: true,
                    isEuVat: false,
                    currencyData: {code: this.getAttribute('currency') || 'EUR'}
                };
            }

            this.$articles.forEach((Article) => {
                if (calculationMode === 'manual') {
                    const data = (this.$calculations.articles || []).find((entry) => {
                        return Number(entry.position) === Number(Article.getAttribute('position'));
                    });
                    const gross = (this.$calculations.brutto?.articles || []).find((entry) => {
                        return Number(entry.position) === Number(Article.getAttribute('position'));
                    });

                    if (data) Article.setCalculations(data);
                    if (gross) Article.$bruttoCalc = gross;
                }

                Article.setCalculationMode(this.getCalculationMode());
            });

            if (calculationMode === 'manual') {
                this.$syncManualArticles();
            }

            this.fireEvent('calc', [this, this.$calculations]);
        },

        disableCalculations: function () {
            this.setCalculationMode('manual');
        },

        enableCalculations: function () {
            this.setCalculationMode('automatic');
        },

        $syncManualArticles: function () {
            this.$calculations.articles = this.save();
        },

        $onArticleCalc: function () {
            if (this.getCalculationMode() === 'manual') {
                this.$syncManualArticles();
                this.fireEvent('calc', [this, this.$calculations]);
                return Promise.resolve(this.$calculations);
            }

            return this.$executeCalculation();
        },

        //endregion

        /**
         * Return the first / main vat of the list
         *
         * @returns {number}
         */
        getVat: function () {
            const calculations = this.getCalculation();
            const articles = calculations.articles;
            const calc = calculations.calculations;

            let vat = 0;

            if (typeof calc !== 'undefined' && typeof calc.vatArray === 'object') {
                let vats = Object.keys(calc.vatArray);
                return parseFloat(vats[0]);
            }

            if (typeof articles !== 'undefined' && typeof articles.length === 'number' && articles.length) {
                return articles[0].vat;
            }

            return vat;
        },

        /**
         * Return price factors
         *
         * @return {[]}
         */
        getPriceFactors: function () {
            return this.$priceFactors;
        },

        /**
         * Remove a price factor
         *
         * @param no
         */
        removePriceFactor: function (no) {
            let newList = [];

            for (let i = 0, len = this.$priceFactors.length; i < len; i++) {
                if (i !== parseInt(no)) {
                    newList.push(this.$priceFactors[i]);
                }
            }

            this.$priceFactors = newList;
        },

        /**
         * add a price factor
         *
         * {
         *      calculation      : 2,
         *      calculation_basis: 2,
         *      description      : Form.elements.title.value,
         *      identifier       : "",
         *      index            : priority,
         *      nettoSum         : data.nettoSum,
         *      nettoSumFormatted: data.nettoSumFormatted,
         *      sum              : data.sum,
         *      sumFormatted     : data.sumFormatted,
         *      title            : Form.elements.title.value,
         *      value            : data.sum,
         *      valueText        : data.sumFormatted,
         *      vat              : Form.elements.vat.value,
         *      visible          : 1
         * }
         * @param priceFactor
         */
        addPriceFactor: function (priceFactor) {
            const prio = priceFactor.index;

            if (prio === this.$priceFactors.length) {
                this.$priceFactors.push(priceFactor);
                return;
            }

            this.$priceFactors.splice(prio, 0, priceFactor);
        },

        /**
         * edit a price factor
         *
         * {
         *      calculation      : 2,
         *      calculation_basis: 2,
         *      description      : Form.elements.title.value,
         *      identifier       : "",
         *      index            : priority,
         *      nettoSum         : data.nettoSum,
         *      nettoSumFormatted: data.nettoSumFormatted,
         *      sum              : data.sum,
         *      sumFormatted     : data.sumFormatted,
         *      title            : Form.elements.title.value,
         *      value            : data.sum,
         *      valueText        : data.sumFormatted,
         *      vat              : Form.elements.vat.value,
         *      visible          : 1
         * }
         *
         * @param index
         * @param priceFactor
         */
        editPriceFactor: function (index, priceFactor) {
            for (let k in priceFactor) {
                if (priceFactor.hasOwnProperty(k)) {
                    this.$priceFactors[index][k] = priceFactor[k];
                }
            }
        },

        /**
         * Return the articles count
         *
         * @returns {number}
         */
        countPriceFactors: function () {
            return this.$priceFactors.length;
        },

        /**
         * Sorting
         */

        /**
         * Toggles the sorting
         */
        toggleSorting: function () {
            if (this.$sorting) {
                this.disableSorting();
                return;
            }

            this.enableSorting();
        },

        /**
         * Enables the sorting
         * Articles can be sorted by drag and drop
         */
        enableSorting: function () {
            const self = this;

            const Elm = this.getElm(),
                elements = Elm.getElements('.article');

            elements.each(function (Node) {
                const ArticleInstance = QUI.Controls.getById(Node.get('data-quiid'));
                const attributes = ArticleInstance.getAttributes();

                ArticleInstance.addEvents({
                    onSetPosition: self.$onArticleSetPosition
                });

                new Element('div', {
                    'class': 'quiqqer-erp-sortableClone-placeholder',
                    html: Mustache.render(templateSortablePlaceholder, Object.assign({}, attributes, {
                        hasAmounts: ArticleInstance.getType() !== 'package/quiqqer/erp/bin/backend/controls/articles/Text',
                        labelArticleNo: QUILocale.get(lg, 'products.articleNo'),
                        labelDescription: QUILocale.get(lg, 'products.description'),
                        labelQuantity: QUILocale.get(lg, 'products.quantity'),
                        labelUnitPrice: QUILocale.get(lg, 'products.unitPrice'),
                        labelPrice: QUILocale.get(lg, 'products.price')
                    }))
                }).inject(Node);
            });


            this.$Sortables = new Sortables(this.$Container, {
                revert: {
                    duration: 500,
                    transition: 'elastic:out'
                },

                clone: function (event) {
                    let Target = event.target;

                    if (!Target.hasClass('article')) {
                        Target = Target.getParent('.article');
                    }

                    const size = Target.getSize(),
                        pos = Target.getPosition(self.$Container);

                    return new Element('div', {
                        styles: {
                            background: 'rgba(0,0,0,0.5)',
                            height: size.y,
                            position: 'absolute',
                            top: pos.y,
                            width: size.x,
                            zIndex: 1000
                        }
                    });
                },

                onStart: function (element) {
                    element.addClass('quiqqer-erp-sortableClone');

                    self.$Container.setStyles({
                        height: self.$Container.getSize().y,
                        overflow: 'hidden',
                        width: self.$Container.getSize().x
                    });
                },

                onComplete: function (element) {
                    element.removeClass('quiqqer-erp-sortableClone');

                    self.$Container.setStyles({
                        height: null,
                        overflow: null,
                        width: null
                    });

                    self.$recalculatePositions();
                }
            });

            this.$sorting = true;
        },

        /**
         * Disables the sorting
         * Articles can not be sorted
         */
        disableSorting: function () {
            this.$sorting = false;

            const self = this,
                Elm = this.getElm(),
                elements = Elm.getElements('.article');

            Elm.getElements('.quiqqer-erp-sortableClone-placeholder').destroy();

            elements.each(function (Node) {
                const ArticleInstance = QUI.Controls.getById(Node.get('data-quiid'));

                ArticleInstance.removeEvents({
                    onSetPosition: self.$onArticleSetPosition
                });
            });

            this.$Sortables.detach();
            this.$Sortables = null;

            this.$articles.sort(function (A, B) {
                return A.getAttribute('position') - B.getAttribute('position');
            });
        },

        /**
         * Is the sorting enabled?
         *
         * @return {boolean}
         */
        isSortingEnabled: function () {
            return this.$sorting;
        },

        /**
         * event: on set position at article
         *
         * @param Article
         */
        $onArticleSetPosition: function (ArticleInstance) {
            ArticleInstance.getElm().getElement('.quiqqer-erp-backend-erpArticlePlaceholder-pos').set(
                'html',
                ArticleInstance.getAttribute('position')
            );
        },

        /**
         * Recalculate the Position of all Articles
         */
        $recalculatePositions: function () {
            let i, len, ArticleInstance;

            const Elm = this.getElm(),
                elements = Elm.getElements('.article');

            for (i = 0, len = elements.length; i < len; i++) {
                ArticleInstance = QUI.Controls.getById(elements[i].get('data-quiid'));
                ArticleInstance.setPosition(i + 1);
            }
        },

        /**
         * Events
         */

        /**
         * event : on article delete
         *
         * @param {Object} ArticleInstance
         */
        $onArticleDelete: function (ArticleInstance) {
            if (this.$selectedArticle) {
                this.$selectedArticle.unselect();
            }

            let i, len, Current;

            let self = this,
                articles = [],
                position = 1;

            for (i = 0, len = this.$articles.length; i < len; i++) {
                if (this.$articles[i].getAttribute('position') === ArticleInstance.getAttribute('position')) {
                    continue;
                }

                Current = this.$articles[i];
                Current.setPosition(position);
                articles.push(Current);

                position++;
            }

            this.$articles = articles;

            if (this.getCalculationMode() === 'manual') {
                this.$onArticleCalc();
            }

            this.$executeCalculation().then(function () {
                if (self.$articles.length) {
                    self.$articles[0].select();
                }
            });
        },

        /**
         * event : on article delete
         *
         * @param {Object} Article
         */
        $onArticleSelect: function (ArticleInstance) {
            if (this.$selectedArticle &&
                this.$selectedArticle !== ArticleInstance) {
                this.$selectedArticle.unselect();
            }

            this.$selectedArticle = ArticleInstance;
            this.fireEvent('articleSelect', [
                this,
                this.$selectedArticle
            ]);
        },

        /**
         * event : on article delete
         *
         * @param Article
         */
        $onArticleUnSelect: function (ArticleInstance) {
            if (this.$selectedArticle === ArticleInstance) {
                this.$selectedArticle = null;
                this.fireEvent('articleUnSelect', [
                    this,
                    this.$selectedArticle
                ]);
            }
        },

        /**
         * event : on article replace click
         *
         * @param Article
         */
        $onArticleReplace: function (ArticleInstance) {
            this.fireEvent('articleReplaceClick', [
                this,
                ArticleInstance
            ]);
        },

        /**
         * event: on article edit custom fields clikc
         *
         * @param {Object} EditArticle - package/quiqqer/erp/bin/backend/controls/articles/Article
         */
        $onArticleEditCustomFields: function (EditArticle) {
            const ArticleCustomFields = EditArticle.getAttribute('customFields');
            const FieldValues = {};

            for (const [fieldId, FieldData] of Object.entries(ArticleCustomFields)) {
                FieldValues[fieldId] = FieldData.value;
            }

            const AddProductControl = new AddProductWindow({
                fieldValues: FieldValues,
                editAmount: false
            });

            const productId = EditArticle.getAttribute('id');

            AddProductControl.openProductSettings(productId).then((ArticleData) => {
                if (!ArticleData) {
                    return false;
                }

                return AddProductControl.$parseProductToArticle(productId, ArticleData);
            }).then((NewArticleData) => {
                if (!NewArticleData) {
                    return;
                }

                const NewArticleCustomFields = NewArticleData.customFields;

                for (const [fieldId, FieldData] of Object.entries(ArticleCustomFields)) {
                    if (!(fieldId in NewArticleCustomFields)) {
                        NewArticleCustomFields[fieldId] = FieldData;
                    }
                }

                EditArticle.setAttribute('customFields', NewArticleCustomFields);

                const NewArticle = new Article(EditArticle.getAttributes());

                this.replaceArticle(NewArticle, EditArticle.getAttribute('position'));
            });
        },

        /**
         * Return the current selected Article
         *
         * @returns {null|Object}
         */
        getSelectedArticle: function () {
            return this.$selectedArticle;
        },

        /**
         * Load the shop reference once; mixed shops allow both price displays.
         */
        $loadShopPricing: function () {
            if (this.$shopPricingRequest || this.$destroyed) {
                return this.$shopPricingRequest || Promise.resolve();
            }

            this.$shopPricingRequest = Promise.resolve().then(() => Promise.all([
                Packages.getConfig('quiqqer/tax'),
                Packages.getConfig('quiqqer/erp')
            ])).then(([taxConfig, erpConfig]) => {
                if (this.$destroyed) {
                    return;
                }

                const businessType = erpConfig?.general?.businessType;
                const shopIsNetto = taxConfig?.shop?.isNetto;

                if ((businessType === 'B2B' || businessType === 'B2C') &&
                    [true, false, 0, 1, '0', '1'].includes(shopIsNetto)) {
                    this.$shopIsNetto = shopIsNetto === true || shopIsNetto === 1 || shopIsNetto === '1';
                }

                this.$refreshNettoBruttoDisplay();
            }).catch(() => {
                // A missing or inaccessible shop configuration must not produce a warning.
            });

            return this.$shopPricingRequest;
        },

        /**
         * refresh the brutto / netto switch display
         */
        $refreshNettoBruttoDisplay: function () {
            const netto = !!this.getAttribute('nettoinput');
            this.$PriceDisplay.setValue(netto);
            this.$Elm.classList.toggle('netto-view', netto);
            this.$Elm.classList.toggle('brutto-view', !netto);

            let warning = '';

            if (typeof this.$shopIsNetto === 'boolean' && netto !== this.$shopIsNetto) {
                warning = QUILocale.get(lg, 'article.priceDisplay.mismatch');
            }

            this.$PriceDisplay.setWarning(warning);
        }

    });
});
