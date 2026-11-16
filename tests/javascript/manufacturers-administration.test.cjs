const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {resolve} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function load(file, dependencies, globals = {}) {
    let definition;
    vm.runInNewContext(readFileSync(resolve(__dirname, '../../bin/backend/controls/manufacturers', file), 'utf8'), {
        Class: function (value) { return value; },
        define(name, names, factory) { definition = factory(...names.map(name => dependencies[name] ?? {})); },
        JSON: {encode: JSON.stringify},
        ...globals
    });
    return definition;
}

function fixture() {
    const requests = [];
    const events = [];
    const listeners = new Map();
    const addEventListener = (name, callback) => listeners.set(name, callback);
    const removeEventListener = name => listeners.delete(name);
    const definition = load('Administration.js', {
        Ajax: {get(name, callback, options) { requests.push(JSON.parse(options.params)); callback({data: []}); }},
        Users: {removeEvents() {}}
    }, {
        document: {addEventListener, removeEventListener},
        window: {innerWidth: 800, innerHeight: 700, addEventListener, removeEventListener}
    });
    const filters = {
        hidden: true,
        style: {},
        focused: false,
        querySelector() { return {focus: () => {filters.focused = true;}}; },
        getBoundingClientRect: () => ({width: 360, height: 180}),
        remove() { this.removed = true; }
    };
    const button = {
        attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        getBoundingClientRect: () => ({right: 760, top: 50, bottom: 84}),
        focus() { this.focused = true; }
    };
    const control = Object.assign({}, definition, {
        $Grid: {options: {page: 4, perPage: 50}, setData(data) {this.data = data;}},
        $Filters: filters,
        $FilterButton: button,
        $SearchInput: {value: 'PCSG'},
        $SearchForm: {
            elements: Object.fromEntries(['userId', 'username', 'firstname', 'lastname', 'email', 'company'].map(name => [name, {checked: true}])),
            remove() {this.removed = true;}
        },
        $periodEnabled: false,
        fireEvent: name => events.push(name)
    });
    return {control, requests, events, filters, button, listeners};
}

test('manufacturer panel provides its native button bar to the administration control', () => {
    const toolbar = {};
    let options;
    const definition = load('AdministrationPanel.js', {
        'package/quiqqer/erp/bin/backend/controls/manufacturers/Administration': class {
            constructor(value) {options = value;}
            inject() {}
            resize() {}
        }
    });
    definition.$onInject.call({
        Loader: {show() {}},
        getButtonBar: () => toolbar,
        getContent: () => ({set() {}, setStyle() {}})
    });
    assert.equal(options.toolbar, toolbar);
});

test('submitting manufacturer search resets pagination and sends the selected search fields', async () => {
    const f = fixture();
    f.control.$SearchForm.elements.email.checked = false;
    await f.control.$submitSearch();
    assert.equal(f.requests[0].page, 1);
    assert.equal(f.requests[0].search, 'PCSG');
    assert.equal(f.requests[0].filter.id, 1);
    assert.equal(f.requests[0].filter.email, 0);
    assert.equal(f.requests[0].filter.company, 1);
    assert.equal(f.requests[0].filter.regdate_from, '');
    assert.deepEqual(f.events, ['refreshBegin', 'refreshEnd']);
});

test('optional period uses local month boundaries and switching it off clears both dates', async () => {
    const f = fixture();
    f.control.$TimeFilter = {getValue: () => ({
        from: new Date(2026, 10, 1).getTime() / 1000,
        to: new Date(2026, 10, 30, 23, 59, 59).getTime() / 1000
    })};
    f.control.$periodEnabled = true;
    await f.control.$submitSearch();
    assert.equal(f.requests[0].filter.regdate_from, '2026-11-01');
    assert.equal(f.requests[0].filter.regdate_to, '2026-11-30');
    f.control.$periodEnabled = false;
    await f.control.$submitSearch();
    assert.equal(f.requests[1].filter.regdate_from, '');
    assert.equal(f.requests[1].filter.regdate_to, '');
});

test('filter overlay aligns under its button, exposes state and returns focus on close', () => {
    const f = fixture();
    f.control.$filterOutside = () => {};
    f.control.$filterKeydown = () => {};
    f.control.$filterReposition = () => {};
    f.control.openFilter();
    assert.equal(f.filters.hidden, false);
    assert.equal(f.filters.focused, true);
    assert.equal(f.filters.style.left, '400px');
    assert.equal(f.filters.style.top, '92px');
    assert.equal(f.button.attributes['aria-expanded'], 'true');
    assert.equal(f.listeners.size, 4);
    f.control.closeFilter(true);
    assert.equal(f.filters.hidden, true);
    assert.equal(f.button.focused, true);
    assert.equal(f.button.attributes['aria-expanded'], 'false');
    assert.equal(f.listeners.size, 0);
});

test('destroy removes external form and overlay but preserves the parent panel button bar', () => {
    const f = fixture();
    let destroyed = 0;
    f.control.$FilterControl = {destroy() {destroyed++;}};
    f.control.$TimeFilter = {destroy() {destroyed++;}};
    f.control.$PeriodToggle = {remove() {this.removed = true;}};
    f.control.$Toolbar = {destroy() {throw new Error('Parent toolbar must survive');}};
    f.control.$ownsToolbar = false;
    f.control.$onDestroy();
    assert.equal(f.control.$SearchForm.removed, true);
    assert.equal(f.filters.removed, true);
    assert.equal(f.control.$PeriodToggle.removed, true);
    assert.equal(destroyed, 2);
});

test('grid sizing matches the ten-pixel padding on each edge', async () => {
    const f = fixture();
    const dimensions = {};
    f.control.$GridContainer = {getSize: () => ({x: 1000, y: 600})};
    f.control.$Grid.setWidth = value => {dimensions.width = value;};
    f.control.$Grid.setHeight = value => {dimensions.height = value;};
    await f.control.resize();
    assert.deepEqual(dimensions, {width: 980, height: 580});
});
