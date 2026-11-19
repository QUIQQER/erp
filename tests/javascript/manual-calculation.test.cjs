const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function fixture() {
    const requests = [];
    let articleFactory;
    const dependencies = {
        'qui/controls/contextmenu/Item': class {
            constructor(options) { this.options = options; }
            getAttribute(key) { return this.options[key]; }
            setAttribute(key, value) { this.options[key] = value; }
        },
        Ajax: {get(name, resolve, params) { requests.push({name, resolve, params}); }},
        Locale: {get: (group, key) => key},
        Mustache: {
            render(template, values) {
                return template.replace(/{{(\w+)}}/g, (match, key) => {
                    return String(values[key]).replace(/[&<>"']/g, character => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    })[character]);
                });
            }
        },
        'text!package/quiqqer/erp/bin/backend/controls/articles/CalculationMode.html': readFileSync(
            join(__dirname, '../../bin/backend/controls/articles/CalculationMode.html'),
            'utf8'
        ),
        'package/quiqqer/erp/bin/backend/utils/Money': {
            validatePrice: value => Promise.resolve(Number(value))
        }
    };
    const context = vm.createContext({
        console,
        URL_OPT_DIR: "/opt/",
        document: {activeElement: null},
        setTimeout,
        clearTimeout,
        Class: function (definition) {
            function Control(options) {
                if (definition.Type.endsWith('/Article') && articleFactory) return articleFactory(options);
            }
            Control.prototype = definition;
            return Control;
        },
        typeOf: value => Array.isArray(value) ? 'array' : typeof value,
        require(names, callback) { callback(...names.map(name => dependencies[name])); },
        define(name, names, factory) {
            dependencies[name] = factory(...names.map(name => dependencies[name] || {}));
        }
    });
    vm.runInContext('Object.merge = Object.assign; JSON.encode = JSON.stringify; Array.prototype.unique = function () { return [...new Set(this)]; };', context);

    for (const name of ['Article', 'ArticleList', 'ArticleSummary', 'CalculationMode']) {
        vm.runInContext(readFileSync(join(__dirname, '../../bin/backend/controls/articles', name + '.js'), 'utf8'), context);
    }

    const Article = dependencies['package/quiqqer/erp/bin/backend/controls/articles/Article'];
    const ArticleList = dependencies['package/quiqqer/erp/bin/backend/controls/articles/ArticleList'];
    const Summary = dependencies['package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary'];
    const snapshot = {
        position: 1,
        title: 'Drillisch',
        unitPrice: 8.395,
        quantity: 1,
        vat: 19,
        calculated: {
            price: 8.395,
            basisPrice: 8.395,
            nettoPrice: 8.395,
            nettoBasisPrice: 8.395,
            nettoPriceNotRounded: 8.395,
            nettoSubSum: 8.395,
            nettoSum: 8.395,
            sum: 8.395,
            isNetto: true,
            isEuVat: false,
            vatArray: {vat: 19, sum: 1.5951, text: '19%'}
        }
    };

    function article(data = structuredClone(snapshot)) {
        const attributes = {...data};
        const events = {};
        return Object.assign(Object.create(Article.prototype), {
            $calculationMode: 'automatic',
            $calculationVersion: 0,
            $created: false,
            $calculations: {calculated: attributes.calculated || {}},
            $bruttoCalc: {sum: 9.9901, unitPrice: 9.9901, quantity_sum: 9.9901, calculated: {basisPrice: 9.9901}},
            getAttribute: key => attributes[key],
            setAttribute: (key, value) => attributes[key] = value,
            getAttributes: () => ({...attributes}),
            getType: () => Article.prototype.Type,
            setCurrency() {},
            setPosition(value) { attributes.position = value; },
            addEvents(values) { Object.assign(events, values); },
            fireEvent(name, args) { events['on' + name[0].toUpperCase() + name.slice(1)]?.(...args); },
            getElm: () => ({addClass() {}}),
            calc() {
                if (this.getCalculationMode() === 'manual') this.fireEvent('calc', [this]);
                return Promise.resolve();
            }
        });
    }

    articleFactory = article;

    const events = {};
    const listAttributes = {currency: 'EUR', nettoinput: true};
    const list = Object.assign(Object.create(ArticleList.prototype), {
        $calculationMode: 'automatic',
        $calculationVersion: 0,
        $articles: [],
        $priceFactors: [],
        $user: {},
        $Container: null,
        $calculations: {
            articles: [],
            calculations: {
                sum: 9.99,
                subSum: 8.395,
                grandSubSum: 9.9901,
                nettoSubSum: 8.395,
                nettoSum: 8.395,
                vatArray: {19: {vat: 19, sum: 1.5951, text: '19%'}},
                vatText: {19: '19%'}
            }
        },
        getAttribute: key => listAttributes[key],
        setAttribute: (key, value) => listAttributes[key] = value,
        fireEvent(name, args) {
            for (const callback of events['on' + name[0].toUpperCase() + name.slice(1)] || []) callback(...args);
        },
        addEvent(name, callback) { (events[name] ||= []).push(callback); },
        removeEvent(name, callback) { events[name] = events[name].filter(entry => entry !== callback); }
    });
    list.$onArticleCalc = list.$onArticleCalc.bind(list);
    const item = article();
    list.addArticle(item);

    return {
        Article,
        ArticleList,
        Summary,
        CalculationMode: dependencies['package/quiqqer/erp/bin/backend/controls/articles/CalculationMode'],
        list,
        item,
        article,
        requests,
        context
    };
}

test('manual mode preserves existing position, gross and document amounts without requesting calculation', async () => {
    const {list, item, requests} = fixture();
    list.setCalculationMode('manual');
    await item.$calc();
    await list.$executeCalculation();
    assert.equal(requests.length, 0);
    assert.equal(item.getCalculationMode(), 'manual');
    assert.equal(item.getCalculations().nettoSum, 8.395);
    assert.equal(item.getBruttoCalc().sum, 9.9901);
    assert.equal(list.getCalculations().calculations.sum, 9.99);
});

test('manual edits preserve precision and change only the selected amount', async () => {
    const {list, item, requests} = fixture();
    list.setCalculationMode('manual');
    await item.setManualAmount('nettoSum', 8.394567);
    await item.setManualAmount('vatSum', 1.595123);
    await item.setManualAmount('grossSum', 12.345678);
    const [saved] = list.save();
    assert.equal(saved.calculated.nettoSum, 8.394567);
    assert.equal(saved.calculated.vatArray.sum, 1.595123);
    assert.equal(saved.calculated.bruttoCalculated.sum, 12.345678);
    assert.equal(saved.quantity, 1);
    assert.equal(saved.unitPrice, 8.395);
    assert.equal(list.getCalculations().articles[0].calculated.nettoSum, 8.394567);
    assert.equal(list.getCalculations().calculations.sum, 9.99);
    assert.equal(requests.length, 0);
});

test('gross unit entry in manual mode does not convert or replace the net unit price', async () => {
    const {list, item} = fixture();
    list.setCalculationMode('manual');
    item.$createEditField = () => Promise.resolve(9.990123);
    item.$UnitPriceBrutto = {get: () => 9.9901};
    item.getNettoPrice = () => { throw new Error('Manual entry must not convert gross to net'); };
    await item.$onEditBruttoPrice();
    assert.equal(item.getBruttoCalc().unitPrice, 9.990123);
    assert.equal(item.getBruttoCalc().calculated.basisPrice, 9.990123);
    assert.equal(item.getAttribute('unitPrice'), 8.395);
});

test('new positions inherit manual mode and re-enabling enables every position', () => {
    const {list, item, article} = fixture();
    list.setCalculationMode('manual');
    const added = article();
    list.addArticle(added);
    assert.equal(added.getCalculationMode(), 'manual');
    list.enableCalculations();
    assert.equal(list.getCalculationMode(), 'automatic');
    assert.equal(item.getCalculationMode(), 'automatic');
    assert.equal(added.getCalculationMode(), 'automatic');
});

test('late list calculation responses cannot overwrite manual values', async () => {
    const {list, item, requests} = fixture();
    const pending = list.$executeCalculation();
    list.disableCalculations();
    await item.setManualAmount('nettoSum', 8.123456);
    requests[0].resolve({articles: [], priceFactors: [], calculations: {sum: 500}});
    await pending;
    assert.equal(list.getCalculations().calculations.sum, 9.99);
    assert.equal(item.getCalculations().nettoSum, 8.123456);
});

test('late article calculation responses cannot restore pre-manual values', async () => {
    const {list, item, requests} = fixture();
    const pending = item.$calc();
    list.disableCalculations();
    await item.setManualAmount('vatSum', 1.234567);
    requests[0].resolve({articles: [], priceFactors: [], calculations: {sum: 500}});
    const result = await pending;
    assert.equal(result.calculated.vatArray.sum, 1.234567);
    assert.equal(item.getCalculations().vatArray.sum, 1.234567);
});

test('summary fields accept contradictory user values without changing the remaining totals', () => {
    const {list, Summary} = fixture();
    list.disableCalculations();
    const input = {checkValidity: () => true, valueAsNumber: -3.456789};
    Summary.prototype.$setManualTotal.call({getAttribute: () => list}, 'sum', input);
    assert.equal(list.getCalculations().calculations.sum, -3.456789);
    assert.equal(list.getCalculations().calculations.nettoSum, 8.395);
    assert.equal(list.getCalculations().calculations.vatArray[19].sum, 1.5951);
});

test('serialized state keeps the mode, position amounts and independent document totals', async () => {
    const {list, item} = fixture();
    list.disableCalculations();
    await item.setManualAmount('nettoSum', 8.123456);
    const saved = JSON.parse(JSON.stringify(list.serialize()));
    assert.equal(saved.calculationMode, 'manual');
    assert.equal(saved.articles[0].calculated.nettoSum, 8.123456);
    assert.equal(saved.calculations.sum, 9.99);
    assert.equal(saved.articles[0].calculated.bruttoCalculated.sum, 9.9901);
});

test('reopening serialized JSON restores manual values before positions can calculate', async () => {
    const original = fixture();
    original.list.disableCalculations();
    await original.item.setManualAmount('nettoSum', 8.123456);
    const reopened = fixture();
    await reopened.list.unserialize(JSON.stringify(original.list.serialize()));
    assert.equal(reopened.list.getCalculationMode(), 'manual');
    assert.equal(reopened.list.getArticles()[0].getCalculationMode(), 'manual');
    assert.equal(reopened.list.getArticles()[0].getCalculations().nettoSum, 8.123456);
    assert.equal(reopened.list.getCalculations().calculations.sum, 9.99);
    assert.equal(reopened.requests.length, 0);
});

test('all package languages provide each manual-entry text once in administration context', () => {
    const manifest = readFileSync(join(__dirname, '../../locale.xml'), 'utf8');
    const languages = [...manifest.matchAll(/file="\/locale\/([^"/]+)\.xml"/g)].map(match => match[1]);
    for (const language of languages) {
        const catalog = readFileSync(join(__dirname, '../../locale', language + '.xml'), 'utf8');
        const keys = [...catalog.matchAll(/<locale name="(manual\.[^"]+)"[^>]*>/g)];
        assert.equal(keys.length, 12, language);
        assert.equal(new Set(keys.map(match => match[1])).size, 12, language);
        assert.ok(keys.every(match => match[0].includes('context="administration"')), language);
    }
});


test('calculation dropdown shows the selected mode, synchronizes changes and releases its listener', async () => {
    const {CalculationMode, list, context} = fixture();
    const attributes = {ArticleList: list};
    const events = {};
    const timers = [];
    let tooltipShown = 0;
    let tooltipDestroyed = 0;
    let tooltipContent = 'manual.calculation';
    let tooltipProps = {};
    context.setTimeout = callback => {
        timers.push(callback);
        return timers.length;
    };
    context.clearTimeout = () => {};
    const Button = Object.assign(Object.create(CalculationMode.prototype), {
        parent() {},
        getAttribute: key => attributes[key],
        setAttribute: (key, value) => attributes[key] = value,
        appendChild() {},
        addEvent: (key, callback) => events[key] = callback,
        $Menu: {getElm: () => ({style: {zIndex: '100003'}})},
        $Tooltip: {
            setProps(props) { tooltipProps = {...tooltipProps, ...props}; },
            setContent(content) { tooltipContent = content; },
            show() { tooltipShown++; },
            hide() {},
            destroy() { tooltipDestroyed++; }
        }
    });
    Button.$refreshMode = Button.$refreshMode.bind(Button);
    Button.initialize({ArticleList: list});
    assert.match(attributes.text, /data-name="mode-label">manual\.automatic<\/span>/);
    assert.equal(Button.$modeItems[0].getAttribute('icon'), 'fa fa-check');
    assert.equal(Button.$modeItems[0].getAttribute('text'), 'manual.calculation: manual.automatic');
    assert.equal(Button.$modeItems[1].getAttribute('text'), 'manual.calculation: manual.manual');

    Button.$modeItems[1].options.events.onClick();
    await Promise.resolve();
    assert.equal(list.getCalculationMode(), 'manual');
    assert.match(attributes.text, /data-name="mode-label">manual\.manual<\/span>/);
    assert.equal(Button.$modeItems[1].getAttribute('icon'), 'fa fa-check');
    assert.equal(Button.$modeItems[0].getAttribute('icon'), 'fa fa-fw');
    assert.equal(tooltipShown, 1);
    assert.equal(timers.length, 1);
    assert.equal(tooltipContent, 'manual.hint');
    assert.equal(tooltipProps.trigger, 'manual');
    assert.equal(tooltipProps.hideOnClick, false);

    Button.$modeItems[1].options.events.onClick();
    assert.equal(tooltipShown, 1, 'selecting the current mode does not show the hint again');
    timers[0]();
    assert.equal(tooltipContent, 'manual.calculation');
    assert.equal(tooltipProps.trigger, 'mouseenter focus');
    assert.equal(tooltipProps.hideOnClick, true);
    assert.equal(Button.$hintTimer, null);
    list.setCalculationMode('automatic');
    assert.match(attributes.text, /data-name="mode-label">manual\.automatic<\/span>/);

    events.onDestroy();
    list.setCalculationMode('manual');
    assert.match(attributes.text, /data-name="mode-label">manual\.automatic<\/span>/);
    assert.equal(tooltipDestroyed, 1);
});

test('manual mode reserves the translated automatic label before the button is attached', () => {
    const {CalculationMode, list} = fixture();
    list.setCalculationMode('manual');
    const attributes = {ArticleList: list};
    const events = {};
    const Button = Object.assign(Object.create(CalculationMode.prototype), {
        parent() {},
        getAttribute: key => attributes[key],
        setAttribute: (key, value) => attributes[key] = value,
        appendChild() {},
        addEvent: (key, callback) => events[key] = callback,
        $Elm: {
            style: {},
            removeAttribute() {},
            getBoundingClientRect() { throw new Error('Hidden button must not be measured'); }
        }
    });
    Button.$refreshMode = Button.$refreshMode.bind(Button);
    Button.initialize({ArticleList: list});
    const initialMarkup = attributes.text;
    assert.match(initialMarkup, /aria-hidden="true">manual\.automatic<\/span>/);
    assert.match(initialMarkup, /data-name="mode-label">manual\.manual<\/span>/);
    assert.equal(Button.$Elm.style.width, undefined);
    events.onInject?.();

    list.setCalculationMode('automatic');
    assert.match(attributes.text, /aria-hidden="true">manual\.automatic<\/span>/);
    assert.match(attributes.text, /data-name="mode-label">manual\.automatic<\/span>/);
    assert.equal(Button.$Elm.style.width, undefined);
});
