const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function fixture(configs = {}) {
    const calls = [];
    const events = {};
    const rendered = [];
    const attributes = {nettoinput: true};
    const classes = new Set();
    let ArticleList;
    vm.runInNewContext(readFileSync(join(__dirname, '../../bin/backend/controls/articles/ArticleList.js'), 'utf8'), {
        Class: function (definition) {
            function Control() {}
            Control.prototype = definition;
            return Control;
        },
        define(name, dependencies, factory) {
            const modules = {
                Locale: {get: (group, key) => key},
                Packages: {
                    getConfig(name) {
                        calls.push(name);
                        const value = configs[name];
                        return typeof value === 'function' ? value() : Promise.resolve(value);
                    }
                }
            };
            ArticleList = factory(...dependencies.map(name => modules[name] || {}));
        }
    });

    const List = Object.assign(Object.create(ArticleList.prototype), {
        parent() {},
        addEvents(values) { Object.assign(events, values); },
        getAttribute(key) { return attributes[key]; },
        setAttribute(key, value) { attributes[key] = value; }
    });
    List.initialize({});
    List.$Elm = {
        classList: {
            toggle(name, enabled) {
                if (enabled) classes.add(name);
                else classes.delete(name);
            }
        }
    };
    List.$PriceDisplay = {
        warning: '',
        setValue(value) { this.value = value; },
        setWarning(value) {
            this.warning = value;
            rendered.push(value);
        }
    };
    return {List, calls, events, rendered, classes};
}

function pureShop(isNetto, businessType = 'B2C') {
    return {
        'quiqqer/tax': {shop: {isNetto}},
        'quiqqer/erp': {general: {businessType}}
    };
}

test('net shop warns only for gross display and ignores customer calculation flags', async () => {
    const {List} = fixture(pureShop('1', 'B2B'));
    List.$calculations.calculations = {isNetto: false};
    await List.$loadShopPricing();
    assert.equal(List.$PriceDisplay.warning, '');
    assert.equal(List.$PriceDisplay.value, true);

    List.setAttribute('nettoinput', false);
    List.$refreshNettoBruttoDisplay();
    assert.equal(List.$PriceDisplay.warning, 'article.priceDisplay.mismatch');

    List.setAttribute('nettoinput', true);
    List.$refreshNettoBruttoDisplay();
    assert.equal(List.$PriceDisplay.warning, '');
});

test('gross shop warns only for net display, independently of the B2B label', async () => {
    const {List, classes} = fixture(pureShop(false, 'B2B'));
    List.$calculations.calculations = {isNetto: true};
    List.setAttribute('nettoinput', false);
    await List.$loadShopPricing();
    assert.equal(List.$PriceDisplay.warning, '');
    assert.ok(classes.has('brutto-view'));

    List.setAttribute('nettoinput', true);
    List.$refreshNettoBruttoDisplay();
    assert.equal(List.$PriceDisplay.warning, 'article.priceDisplay.mismatch');
    assert.ok(classes.has('netto-view'));
    assert.ok(!classes.has('brutto-view'));
});

for (const businessType of ['B2B-B2C', 'B2C-B2B']) {
    test('mixed shop ' + businessType + ' allows both price displays', async () => {
        const {List} = fixture(pureShop(1, businessType));
        await List.$loadShopPricing();
        List.$refreshNettoBruttoDisplay();
        assert.equal(List.$PriceDisplay.warning, '');

        List.setAttribute('nettoinput', false);
        List.$refreshNettoBruttoDisplay();
        assert.equal(List.$PriceDisplay.warning, '');
    });
}

test('pending configuration stays neutral, loads once and uses the latest selected display', async () => {
    let resolveTax;
    let resolveErp;
    const {List, calls} = fixture({
        'quiqqer/tax': () => new Promise(resolve => { resolveTax = resolve; }),
        'quiqqer/erp': () => new Promise(resolve => { resolveErp = resolve; })
    });
    List.$refreshNettoBruttoDisplay();
    assert.equal(List.$PriceDisplay.warning, '');
    const pending = List.$loadShopPricing();
    assert.equal(List.$loadShopPricing(), pending);
    await Promise.resolve();
    assert.deepEqual(calls, ['quiqqer/tax', 'quiqqer/erp']);

    List.setAttribute('nettoinput', false);
    List.$refreshNettoBruttoDisplay();
    assert.equal(List.$PriceDisplay.warning, '');
    resolveTax({shop: {isNetto: '1'}});
    resolveErp({general: {businessType: 'B2C'}});
    await pending;
    assert.equal(List.$PriceDisplay.warning, 'article.priceDisplay.mismatch');
    await List.$loadShopPricing();
    assert.equal(calls.length, 2);
});

test('configuration rejection stays neutral and does not retry on every display change', async () => {
    const {List, calls} = fixture({
        ...pureShop(0),
        'quiqqer/tax': () => Promise.reject(new Error('Permission denied'))
    });
    await List.$loadShopPricing();
    assert.equal(List.$PriceDisplay.warning, '');
    List.setAttribute('nettoinput', false);
    List.$refreshNettoBruttoDisplay();
    await List.$loadShopPricing();
    assert.equal(List.$PriceDisplay.warning, '');
    assert.equal(calls.length, 2);
});

for (const configs of [pureShop(undefined), pureShop('invalid'), pureShop(0, 'unknown'), {}]) {
    test('incomplete or invalid configuration stays neutral: ' + JSON.stringify(configs), async () => {
        const {List} = fixture(configs);
        await List.$loadShopPricing();
        assert.equal(List.$shopIsNetto, null);
        assert.equal(List.$PriceDisplay.warning, '');
    });
}

test('a late configuration response cannot update a destroyed list', async () => {
    let resolveTax;
    const {List, events, rendered} = fixture({
        ...pureShop(0),
        'quiqqer/tax': () => new Promise(resolve => { resolveTax = resolve; })
    });
    List.$refreshNettoBruttoDisplay();
    const pending = List.$loadShopPricing();
    await Promise.resolve();
    events.onDestroy();
    resolveTax({shop: {isNetto: 0}});
    await pending;
    assert.equal(rendered.length, 1);
    assert.equal(List.$shopIsNetto, null);
});
