const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function fixture({count = 1, reducedMotion = false, mode = 'automatic', inline = false} = {}) {
    const pending = {};
    const callbacks = {};
    const listeners = new Map();
    const writes = [];
    const requires = [];
    const animations = [];
    const visibilityAnimations = [];
    const observers = [];
    const state = {count, mode};
    const formatter = {format: value => String(value)};
    const currencyPromise = new Promise(resolve => pending.currency = resolve);
    const data = {calculations: {nettoSum: 8.395, sum: 9.99, vatArray: {19: {sum: 1.5951, text: '19%'}}}};
    const List = {
        count: () => state.count,
        getElm: () => ({querySelector: () => ({})}),
        getCalculations: () => data,
        getCalculationMode: () => state.mode,
        getPriceFactors: () => [],
        addEvent(name, callback) {
            if (!listeners.has(name)) listeners.set(name, new Set());
            listeners.get(name).add(callback);
        },
        removeEvent(name, callback) { listeners.get(name)?.delete(callback); },
        emit(name) { for (const callback of [...listeners.get(name) || []]) callback(List); }
    };
    let Summary;
    vm.runInNewContext(readFileSync(join(__dirname, '../../bin/backend/controls/articles/ArticleSummary.js'), 'utf8'), {
        Class: function (definition) {
            function Control() {}
            Control.prototype = definition;
            return Control;
        },
        define(name, dependencies, factory) {
            Summary = factory({}, {}, function Article() {}, {}, {
                getCurrency: () => currencyPromise
            }, {
                render: () => 'rendered'
            }, {
                get: (group, key) => key,
                getNumberFormatter: () => formatter
            });
        },
        require(names, callback) { requires.push(callback); },
        window: {matchMedia: () => ({matches: reducedMotion})},
        document: {createElement: () => ({})},
        MutationObserver: class {
            constructor(callback) {
                this.callback = callback;
                this.disconnected = false;
                observers.push(this);
            }
            observe() {}
            disconnect() { this.disconnected = true; }
        },
        typeOf: value => Array.isArray(value) ? 'array' : typeof value
    });
    const attributes = {List, currency: 'EUR', showPosSummary: false, manualTotalsInline: inline};
    const Control = Object.assign(Object.create(Summary.prototype), {
        parent() {},
        getAttribute: name => attributes[name],
        addEvent: (name, callback) => callbacks[name] = callback,
        addEvents(values) { Object.assign(callbacks, values); }
    });
    Control.initialize();
    Control.$Elm = {
        hidden: true,
        style: {},
        animate(keyframes, options) {
            const Animation = {
                keyframes, options,
                cancelled: false,
                cancel() { this.cancelled = true; },
                addEventListener() {}
            };
            visibilityAnimations.push(Animation);
            return Animation;
        },
        classList: {toggle: (...values) => writes.push(values)},
        querySelector: selector => ({
            set hidden(value) { writes.push(['hidden', value]); },
            set className(value) { writes.push(['icon', selector, value]); },
            setAttribute(name, value) { writes.push(['attribute', selector, name, value]); }
        })
    };
    Control.$NettoSum = {set: (...values) => writes.push(values)};
    Control.$BruttoSum = {set: (...values) => writes.push(values)};
    Control.$VAT = {
        rows: [],
        set: (...values) => writes.push(values),
        replaceChildren() { this.rows = []; },
        appendChild(Row) { this.rows.push(Row); }
    };
    Control.$PriceFactors = {style: {}};
    Control.$PFFX = {
        animate(values, options) { animations.push({values, options}); },
        stop() { animations.push('stop'); }
    };
    Control.$refreshArticleSelect = Control.$refreshArticleSelect.bind(Control);
    function destroy() {
        callbacks.onDestroy();
        Control.$Elm = null;
    }
    return {
        Control, List, data, formatter, pending, requires, animations, visibilityAnimations,
        observers, state, listeners, writes, destroy
    };
}

async function flush() {
    await Promise.resolve();
    await Promise.resolve();
}

test('destroy releases list listeners and ignores already queued calculation callbacks', async () => {
    const {Control, List, pending, listeners, writes, destroy} = fixture();
    Control.$onInject();
    assert.equal(listeners.get('onCalc').size, 1);
    assert.equal(listeners.get('onArticleSelect').size, 1);
    const oldListener = Control.$refreshArticleSelect;
    const before = writes.length;
    destroy();
    assert.equal(listeners.get('onCalc').size, 0);
    assert.equal(listeners.get('onArticleSelect').size, 0);
    assert.doesNotThrow(() => List.emit('onCalc'));
    assert.doesNotThrow(() => oldListener(List));
    pending.currency({code: 'EUR', precision: 2});
    await flush();
    assert.equal(writes.length, before);
    assert.equal(Control.$Formatter, undefined);
});

test('a late price-factor hover and an old hide animation cannot access a destroyed summary', async () => {
    const {Control, pending, animations, destroy} = fixture();
    Control.showPriceFactors();
    Control.hidePriceFactors();
    const hideCallback = animations[0].options.callback;
    destroy();
    const before = animations.length;
    pending.currency({code: 'EUR', precision: 2});
    await flush();
    assert.doesNotThrow(hideCallback);
    assert.equal(animations.length, before);
    assert.equal(Control.$PriceFactors.innerHTML, undefined);
});

test('a delayed module load does not open the price-factor window after destruction', () => {
    const {Control, requires, destroy} = fixture();
    let opened = 0;
    Control.openSummary();
    assert.equal(requires.length, 1);
    destroy();
    requires[0](function Window() { opened++; });
    assert.equal(opened, 0);
});

test('legacy summary refresh resolves without touching a window when formatting completes after destroy', async () => {
    const {Control, pending, destroy} = fixture();
    let reads = 0;
    const finished = Control.$refreshSummaryContent({getContent() { reads++; }});
    destroy();
    pending.currency({code: 'EUR', precision: 2});
    await finished;
    assert.equal(reads, 0);
});

test('empty lists stay hidden; first text position slides in and clearing hides and cancels it', async () => {
    const {Control, data, state, observers, visibilityAnimations, pending, writes} = fixture({count: 0});
    delete data.calculations;
    Control.$onInject();
    assert.equal(Control.$Elm.hidden, true);
    assert.equal(visibilityAnimations.length, 0);

    state.count = 1;
    observers[0].callback();
    assert.equal(Control.$Elm.hidden, false);
    assert.equal(visibilityAnimations.length, 1);
    assert.equal(visibilityAnimations[0].keyframes[0].transform, 'translateY(20px)');
    pending.currency({code: 'EUR', precision: 2});
    await flush();
    assert.ok(writes.some(value => value[0] === 'html' && value[1] === '0'));
    assert.equal(data.calculations, undefined, 'displaying text positions does not create model amounts');

    observers[0].callback();
    assert.equal(visibilityAnimations.length, 1, 'unchanged count does not restart the animation');
    state.count = 0;
    observers[0].callback();
    assert.equal(Control.$Elm.hidden, true);
    assert.equal(visibilityAnimations[0].cancelled, true);
});

test('a populated list is visible immediately without competing with panel opening animations', () => {
    const {Control, visibilityAnimations} = fixture();
    Control.$Elm.style.opacity = '0';
    Control.$Elm.style.bottom = '-20px';
    Control.$onInject();
    assert.equal(Control.$Elm.hidden, false);
    assert.equal(visibilityAnimations.length, 0);
    assert.equal(Control.$Elm.style.opacity, '0');
    assert.equal(Control.$Elm.style.bottom, '-20px');
});

test('reduced motion displays the first position without animation', () => {
    const {Control, state, observers, visibilityAnimations} = fixture({count: 0, reducedMotion: true});
    Control.$onInject();
    state.count = 1;
    observers[0].callback();
    assert.equal(Control.$Elm.hidden, false);
    assert.equal(visibilityAnimations.length, 0);
});

test('destroy disconnects position observation and cancels an active entrance animation', () => {
    const {Control, state, observers, visibilityAnimations, destroy} = fixture({count: 0});
    Control.$onInject();
    state.count = 1;
    observers[0].callback();
    destroy();
    assert.equal(observers[0].disconnected, true);
    assert.equal(visibilityAnimations[0].cancelled, true);
    state.count = 0;
    assert.doesNotThrow(() => observers[0].callback());
});

test('removing the last position blocks an outstanding totals render', async () => {
    const {Control, List, state, pending, writes} = fixture();
    Control.$onInject();
    state.count = 0;
    List.emit('onCalc');
    const before = writes.length;
    pending.currency({code: 'EUR', precision: 2});
    await flush();
    assert.equal(Control.$Elm.hidden, true);
    assert.equal(writes.length, before);
});

test('reinjection replaces observation and retains one listener per list event', () => {
    const {Control, observers, listeners} = fixture();
    Control.$onInject();
    Control.$onInject();
    assert.equal(observers[0].disconnected, true);
    assert.equal(observers[1].disconnected, false);
    assert.equal(listeners.get('onCalc').size, 1);
    assert.equal(listeners.get('onArticleSelect').size, 1);
});

test('manual summaries display formatted totals without editing or mutating stored amounts', async () => {
    const {Control, List, data, pending, writes} = fixture({mode: 'manual'});
    const before = JSON.stringify(data);
    Control.$renderManualTotals = () => assert.fail('The compact summary must not create inputs.');
    Control.$refreshArticleSelect(List);
    pending.currency({code: 'EUR', precision: 2});
    await flush();

    assert.ok(writes.some(entry => entry[0] === 'html' && entry[1] === '8.395'));
    assert.ok(writes.some(entry => entry[0] === 'html' && entry[1] === '9.99'));
    assert.ok(writes.some(entry => entry[0] === 'quiqqer-erp-summary-editable' && entry[1] === true));
    assert.equal(Control.$VAT.rows[0].textContent, '19% (1.5951)');
    assert.equal(JSON.stringify(data), before);
});

test('inline manual summaries retain their existing editor for quick entry and the popup', () => {
    const {Control, List, data, writes} = fixture({mode: 'manual', inline: true});
    let rendered = 0;
    Control.$renderManualTotals = (EditorList, amounts) => {
        assert.equal(EditorList, List);
        assert.equal(amounts, data.calculations);
        rendered++;
    };
    Control.$refreshArticleSelect(List);
    assert.equal(rendered, 1);
    assert.ok(writes.some(entry => entry[0] === 'quiqqer-erp-summary-manual' && entry[1] === true));
    assert.ok(writes.some(entry => entry[0] === 'quiqqer-erp-summary-editable' && entry[1] === false));
});

test('the shared display icon follows the mode and exposes the corresponding accessible action', () => {
    const {Control, List, state, writes} = fixture();
    Control.$refreshArticleSelect(List);
    assert.ok(writes.some(entry => entry[0] === 'icon' && entry[2] === 'fa fa-calculator'));
    assert.ok(writes.some(entry => entry[0] === 'attribute' && entry[1].includes('open-totals') &&
        entry[2] === 'aria-label' && entry[3] === 'article.summary.totalsWindow.title'));

    state.mode = 'manual';
    Control.$refreshArticleSelect(List);
    assert.ok(writes.some(entry => entry[0] === 'icon' && entry[2] === 'fa fa-edit'));
    assert.ok(writes.some(entry => entry[0] === 'attribute' && entry[1].includes('open-totals') &&
        entry[2] === 'aria-label' && entry[3] === 'manual.editTotals'));
});

test('manual summary opens one lazy popup; a late module response after destroy does nothing', () => {
    const {Control, requires, destroy} = fixture({mode: 'manual'});
    Control.openSummary();
    Control.openSummary();
    assert.equal(requires.length, 1);
    destroy();
    requires[0](function Window() { assert.fail('Destroyed summary must not open a popup.'); });
});

test('switching modes while loading still opens the same shared totals window', () => {
    const {Control, List, state, requires} = fixture({mode: 'manual'});
    let opened = 0;
    Control.openSummary();
    state.mode = 'automatic';
    requires[0](class Window {
        constructor(options) { assert.equal(options.ArticleList, List); }
        open() { opened++; }
    });
    assert.equal(opened, 1);
    assert.equal(Control.$summaryWindowLoading, false);
});

test('clicking the inline manual editor does not open another window', () => {
    const {Control, requires} = fixture({mode: 'manual', inline: true});
    Control.openSummary();
    assert.equal(requires.length, 0);
});

test('clearing the list closes the manual totals popup and hides the summary', () => {
    const {Control, List} = fixture({mode: 'manual', count: 0});
    let closed = 0;
    Control.$SummaryWindow = {close() { closed++; }};
    Control.$refreshArticleSelect(List);
    assert.equal(closed, 1);
    assert.equal(Control.$Elm.hidden, true);
});
