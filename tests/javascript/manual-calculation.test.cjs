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

test('manual serialization includes runtime amounts omitted by framework getAttributes', async () => {
    const {list, item, requests} = fixture();
    const getAttributes = item.getAttributes;
    // QUI DOM.getAttributes returns only options; runtime attributes live in QUI storage.
    item.getAttributes = () => {
        const options = getAttributes();
        delete options.calculated;
        return options;
    };
    list.setCalculationMode('manual');
    await item.setManualAmount('nettoSum', 8.395);
    await item.setManualAmount('vatSum', 1.5951);
    await item.setManualAmount('grossSum', 9.99);

    const data = JSON.parse(JSON.stringify(list.serialize()));
    assert.equal(data.calculationMode, 'manual');
    assert.equal(data.articles[0].calculated.sum, 8.395);
    assert.equal(data.articles[0].calculated.nettoSum, 8.395);
    assert.equal(data.articles[0].calculated.vatArray.sum, 1.5951);
    assert.equal(data.articles[0].calculated.bruttoCalculated.sum, 9.99);
    assert.equal(data.calculations.sum, 9.99);
    assert.equal(requests.length, 0);
});

test('automatic serialization keeps the existing attributes payload', () => {
    const {list, item} = fixture();
    const before = JSON.parse(JSON.stringify(list.save()));
    item.getCalculations = () => { throw new Error('Automatic save must keep the existing attributes path.'); };
    assert.deepEqual(JSON.parse(JSON.stringify(list.save())), before);
});

test('manual net unit entry fills the normal calculated unit price fields without recalculating amounts', async () => {
    const {list, item} = fixture();
    list.disableCalculations();
    const before = JSON.parse(JSON.stringify(list.serialize()));
    await item.setUnitPrice(8.39);
    const saved = list.save()[0];
    assert.equal(saved.unitPrice, 8.39);
    for (const field of ['price', 'basisPrice', 'nettoPrice', 'nettoBasisPrice', 'nettoPriceNotRounded']) {
        assert.equal(saved.calculated[field], 8.39, field);
    }
    assert.equal(saved.calculated.nettoSum, before.articles[0].calculated.nettoSum);
    assert.equal(saved.calculated.nettoSubSum, before.articles[0].calculated.nettoSubSum);
    assert.equal(saved.calculated.sum, before.articles[0].calculated.sum);
    assert.deepEqual(saved.calculated.vatArray, before.articles[0].calculated.vatArray);
    assert.deepEqual(JSON.parse(JSON.stringify(list.getCalculations().calculations)), before.calculations);
});

test('manual gross unit entry fills the same calculated price fields in gross context', async () => {
    const {list, item} = fixture();
    list.disableCalculations();
    item.getCalculations().isNetto = false;
    const before = JSON.parse(JSON.stringify(list.serialize()));
    await item.setManualAmount('grossUnitPrice', 9.99);
    const saved = list.save()[0];
    assert.equal(saved.calculated.price, 9.99);
    assert.equal(saved.calculated.basisPrice, 9.99);
    assert.equal(saved.calculated.bruttoCalculated.unitPrice, 9.99);
    assert.equal(saved.calculated.bruttoCalculated.calculated.price, 9.99);
    assert.equal(saved.unitPrice, before.articles[0].unitPrice);
    assert.equal(saved.calculated.nettoPrice, before.articles[0].calculated.nettoPrice);
    assert.equal(saved.calculated.sum, before.articles[0].calculated.sum);
    assert.deepEqual(JSON.parse(JSON.stringify(list.getCalculations().calculations)), before.calculations);
});

test('new manual positions use the document pricing context and fill standard unit fields', async () => {
    const {list, article} = fixture();
    list.disableCalculations();
    list.getCalculations().calculations.isNetto = false;
    const item = article({position: 2, unitPrice: 8.39, quantity: 1, vat: 19});
    item.$bruttoCalc = {};
    list.addArticle(item);
    assert.equal(item.getCalculations().isNetto, false);
    assert.equal(item.getCalculations().price, 0, 'gross is not derived from the entered net price');
    assert.equal(item.getCalculations().nettoPrice, 8.39);
    await item.setManualAmount('grossUnitPrice', 9.99);
    assert.equal(item.getCalculations().price, 9.99);
    assert.equal(item.getCalculations().basisPrice, 9.99);
    assert.equal(item.getCalculations().sum, 0, 'unit entry does not calculate a position total');
});

test('standalone manual positions initialize the same net unit fields without a list', () => {
    const {article} = fixture();
    const item = article({unitPrice: 8.39, quantity: 1, vat: 19, List: false});
    item.$bruttoCalc = {};
    item.disableCalculations();
    const amounts = item.getCalculations();
    for (const field of ['price', 'basisPrice', 'nettoPrice', 'nettoBasisPrice', 'nettoPriceNotRounded']) {
        assert.equal(amounts[field], 8.39, field);
    }
    assert.equal(amounts.isNetto, true);
    assert.equal(amounts.sum, 0);
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

test('explicit totals transfer retains independent Drillisch amounts and changes only document totals', async () => {
    const {list, item, Summary, requests} = fixture();
    list.disableCalculations();
    await item.setManualAmount('grossSum', 9.99);
    list.getCalculations().calculations.sum = 42;
    list.getCalculations().calculations.vatArray = {7: {vat: 7, sum: 3, text: '7%'}};
    list.getCalculations().calculations.customData = {reference: 'keep'};
    list.$priceFactors = [{title: 'keep', sum: 0.1234}];
    const before = JSON.parse(JSON.stringify(list.save()));
    assert.equal(list.getCalculations().calculations.sum, 42, 'editing a position alone does not copy totals');

    Summary.prototype.$setTotalsFromArticles.call({getAttribute: () => list});
    const totals = list.getCalculations().calculations;
    assert.equal(totals.nettoSum, 8.395);
    assert.equal(totals.nettoSubSum, 8.395);
    assert.equal(totals.subSum, 8.395);
    assert.equal(totals.sum, 9.99);
    assert.equal(totals.grandSubSum, 9.99);
    assert.equal(totals.vatSum, 1.5951);
    assert.equal(totals.vatArray[19].sum, 1.5951);
    assert.equal(totals.vatArray[7], undefined, 'old document tax rows are replaced');
    assert.deepEqual(totals.customData, {reference: 'keep'});
    assert.deepEqual(list.getPriceFactors(), [{title: 'keep', sum: 0.1234}]);
    assert.deepEqual(JSON.parse(JSON.stringify(list.save())), before, 'positions are not recalculated or changed');
    assert.equal(list.getCalculationMode(), 'manual');
    assert.equal(requests.length, 0);
});

test('totals transfer groups existing tax amounts by rate and supports negative positions', async () => {
    const {list, item, article, Summary} = fixture();
    list.disableCalculations();
    await item.setManualAmount('grossSum', 9.99);
    const second = article();
    list.addArticle(second);
    await second.setManualAmount('nettoSum', -0.395);
    await second.setManualAmount('vatSum', -0.0951);
    await second.setManualAmount('grossSum', -0.49);
    const reduced = article();
    list.addArticle(reduced);
    reduced.getCalculations().vatArray.vat = 7;
    reduced.getCalculations().vatArray.text = '7%';
    await reduced.setManualAmount('nettoSum', 2.123456);
    await reduced.setManualAmount('vatSum', 0.14864192);
    await reduced.setManualAmount('grossSum', 2.27);

    Summary.prototype.$setTotalsFromArticles.call({getAttribute: () => list});
    const totals = list.getCalculations().calculations;
    assert.equal(totals.nettoSum, 10.123456);
    assert.equal(totals.sum, 11.77);
    assert.equal(totals.vatArray[19].sum, 1.5);
    assert.equal(totals.vatArray[7].sum, 0.14864192);
    assert.equal(totals.vatSum, 1.64864192);
    assert.equal(totals.vatText[7], '7%');
});

test('totals transfer adds entered decimals without binary floating point tails', async () => {
    const {list, item, article, Summary} = fixture();
    list.disableCalculations();
    const second = article();
    list.addArticle(second);
    for (const [position, net, tax, gross] of [[item, 0.1, 0.019, 0.119], [second, 0.2, 0.038, 0.238]]) {
        await position.setManualAmount('nettoSum', net);
        await position.setManualAmount('vatSum', tax);
        await position.setManualAmount('grossSum', gross);
    }
    Summary.prototype.$setTotalsFromArticles.call({getAttribute: () => list});
    const totals = list.getCalculations().calculations;
    assert.equal(totals.nettoSum, 0.3);
    assert.equal(totals.sum, 0.357);
    assert.equal(totals.vatArray[19].sum, 0.057);
    assert.equal(totals.vatSum, 0.057);
});

test('gross-context totals use existing gross values while keeping the precise net amounts', async () => {
    const {list, item, Summary} = fixture();
    list.disableCalculations();
    list.getCalculations().calculations.isNetto = false;
    await item.setManualAmount('grossSum', 9.99);
    Summary.prototype.$setTotalsFromArticles.call({getAttribute: () => list});
    const totals = list.getCalculations().calculations;
    assert.equal(totals.subSum, 9.99);
    assert.equal(totals.nettoSum, 8.395);
    assert.equal(totals.isNetto, false);
});

test('totals transfer leaves automatic calculation untouched', () => {
    const {list, Summary} = fixture();
    const before = JSON.stringify(list.getCalculations());
    list.getArticles = () => { throw new Error('Automatic totals must use the existing calculator.'); };
    Summary.prototype.$setTotalsFromArticles.call({getAttribute: () => list});
    assert.equal(JSON.stringify(list.getCalculations()), before);
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
        assert.equal(keys.length, 16, language);
        assert.equal(new Set(keys.map(match => match[1])).size, 16, language);
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
