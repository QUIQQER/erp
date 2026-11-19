const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function fixture(mode) {
    const state = {mode};
    const calls = [];
    const events = {};
    const factors = [{title: 'Shipping', valueText: '2,50 €', nettoSum: 2.5}];
    const fields = {mode: {}, description: {}};
    const List = {
        getCalculationMode: () => state.mode,
        getPriceFactors: () => factors,
        removeEvent: (name, callback) => calls.push(['remove', name, callback])
    };
    class SimpleWindow {}
    let definition;
    const rendered = [];
    vm.runInNewContext(readFileSync(join(__dirname, '../../bin/backend/controls/articles/windows/PriceFactors.js'), 'utf8'), {
        Class: function(value) { return value; },
        define(name, names, factory) {
            definition = factory(...names.map(dependency => {
                if (dependency === 'qui/controls/windows/SimpleWindow') return SimpleWindow;
                if (dependency === 'Locale') return {get: (group, key) => key};
                if (dependency === 'Mustache') return {render(template, values) { rendered.push(values); return 'factors'; }};
                if (dependency === 'qui/QUI') return {fireEvent: name => calls.push(['global', name])};
                return {};
            }));
        }
    });
    const Window = Object.assign({}, definition, {
        getAttribute: () => List,
        getContent: () => ({querySelector: selector => fields[selector.includes('description') ? 'description' : 'mode']}),
        parent(options) { this.parentOptions = options; },
        addEvents: values => Object.assign(events, values),
        fireEvent: name => calls.push(['local', name]),
        Loader: {hide: () => calls.push(['hide'])}
    });
    Window.initialize({ArticleList: List});
    Window.$closed = false;
    Window.$Factors = {};
    return {Window, SimpleWindow, List, factors, state, fields, rendered, events, calls};
}

for (const mode of ['manual', 'automatic']) {
    test(`${mode} mode uses the shared SimpleWindow and renders price factors without changing their data`, () => {
        const {Window, SimpleWindow, factors, fields, rendered, calls} = fixture(mode);
        const before = JSON.stringify(factors);
        assert.equal(Window.Extends, SimpleWindow);
        Window.refresh();
        assert.equal(fields.mode.textContent, 'manual.' + mode);
        assert.equal(fields.description.textContent, mode === 'manual' ? 'manual.hint' : 'article.summary.totalsWindow.automaticHint');
        assert.equal(rendered.length, 1);
        assert.equal(rendered[0].priceFactors[0].index, 0);
        assert.equal(rendered[0].priceFactors[0].priority, 1);
        assert.equal(JSON.stringify(factors), before);
        assert.ok(calls.some(call => call[0] === 'global' && call[1] === 'quiqqerErpPriceFactorWindow'));
    });
}

test('updating a total or calculation mode retains factor controls and their extension buttons', () => {
    const {Window, state, fields, rendered} = fixture('manual');
    Window.refresh();
    state.mode = 'automatic';
    Window.refresh();
    assert.equal(fields.mode.textContent, 'manual.automatic');
    assert.equal(rendered.length, 1, 'do not recreate unrelated factor actions on every amount change');
});

test('closing releases the embedded editor and list listener; later refresh does nothing', () => {
    const {Window, events, calls, rendered} = fixture('manual');
    let destroyed = 0;
    Window.$Summary = {destroy() { destroyed++; }};
    events.onCloseBegin();
    Window.refresh();
    assert.equal(destroyed, 1);
    assert.equal(Window.$Summary, null);
    assert.ok(calls.some(call => call[0] === 'remove' && call[1] === 'onCalc' && call[2] === Window.refresh));
    assert.equal(rendered.length, 0);
});

test('each package language supplies the shared window labels once in administration context', () => {
    const keys = ['article.summary.totalsWindow.title', 'article.summary.totalsWindow.factors', 'article.summary.totalsWindow.automaticHint'];
    for (const language of ['de', 'en', 'es', 'pt', 'fr', 'it', 'pl']) {
        const catalog = readFileSync(join(__dirname, '../../locale', language + '.xml'), 'utf8');
        for (const key of keys) {
            const entries = catalog.match(new RegExp('<locale name="' + key.replaceAll('.', '\\.') + '"[^>]*>', 'g')) || [];
            assert.equal(entries.length, 1, language + '/' + key);
            assert.ok(entries[0].includes('context="administration"'));
        }
    }
});
