const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {test} = require('node:test');

function fixture(stored = '{}') {
    class Element {
        constructor(tag) {
            this.tagName = tag;
            this.children = [];
            this.dataset = {};
            this.attributes = {};
            this.classList = {add() {}};
            this.events = {};
            this.value = '';
        }
        append(...children) {
            children.forEach(child => { child.parentNode = this; this.children.push(child); });
        }
        replaceChildren() { this.children = []; }
        setAttribute(key, value) { this.attributes[key] = value; }
        getAttribute(key) { return this.attributes[key]; }
        removeAttribute(key) { delete this.attributes[key]; }
        addEventListener(key, handler) { this.events[key] = handler; }
    }
    let definition;
    const storage = [];
    const requests = [];
    const imports = [];
    const panels = [];
    const loader = [];
    class Panel {}
    const context = {
        document: {createElement: tag => new Element(tag)},
        window: {QUIQQER_USER: {uuid: 'user-one'}},
        Class: function (value) { return value; },
        typeOf: value => value.isClass ? 'class' : typeof value,
        require: (modules, resolve, reject) => imports.push({modules, resolve, reject}),
        define: (name, dependencies, factory) => {
            definition = factory({Storage: {
                get: () => stored,
                set: (key, value) => storage.push({key, value})
            }}, Panel, {openPanelInTasks: panel => panels.push(panel)}, {
                get: (endpoint, resolve, options) => requests.push({endpoint, resolve, options})
            }, {get: (group, key) => key});
        }
    };
    vm.runInNewContext(fs.readFileSync(require.resolve('../../bin/backend/controls/Panel.js'), 'utf8'), context);
    const Content = new Element('div');
    const buttons = [];
    const control = Object.assign({}, definition, {
        parent() {}, setAttributes(value) { this.attributes = value; },
        getAttribute(key) { return this.attributes[key]; },
        addEvents(events) { this.events = events; },
        getContent: () => Content, getId: () => 'panel',
        addButton(options) {
            const Node = new Element('button');
            buttons.push({
                options,
                getAttribute: key => options[key],
                setAttribute: (key, value) => { options[key] = value; },
                getElm: () => Node,
                enable() { options.disabled = false; },
                disable() { options.disabled = true; }
            });
        },
        getButtons: () => buttons,
        Loader: {show() { loader.push('show'); }, hide() { loader.push('hide'); }}
    });
    control.initialize({});
    control.$onCreate();
    return {control, requests, storage, imports, panels, Panel, loader};
}
const menu = () => ({items: [{name: 'accounting', text: 'Buchhaltung', opened: true, items: [
    {name: 'bills', text: 'Rechnungen', opened: false, items: [
        {name: 'drafts', text: 'Entwürfe', require: 'drafts'},
        {name: 'journal', text: 'Journal', require: 'journal'}
    ]},
    {name: 'contracts', text: 'Verträge', require: 'contracts', items: [
        {name: 'templates', text: '<img src=x onerror=alert(1)>', require: 'templates'}
    ]}
]}]});

test('uses the existing endpoint, preserves provider data, order and opening defaults', () => {
    const f = fixture();
    const data = menu();
    const before = JSON.stringify(data);
    assert.equal(f.requests[0].endpoint, 'package_quiqqer_erp_ajax_panel_list');
    assert.equal(f.requests[0].options.package, 'quiqqer/erp');
    f.requests[0].resolve(data);
    assert.equal(JSON.stringify(data), before);
    assert.deepEqual(Array.from(f.control.$nodes, n => n.text), [
        'Buchhaltung', 'Rechnungen', 'Entwürfe', 'Journal', 'Verträge', '<img src=x onerror=alert(1)>'
    ]);
    assert.equal(f.control.$nodes[0].Children.hidden, false);
    assert.equal(f.control.$nodes[1].Children.hidden, true);
    assert.equal(f.control.$nodes[5].Button.children[1].textContent, '<img src=x onerror=alert(1)>');
});

test('search opens matching ancestors temporarily, handles accents and restores saved folds', () => {
    const f = fixture();
    f.requests[0].resolve(menu());
    const [accounting, bills, drafts, journal, contracts] = f.control.$nodes;
    f.control.$toggle(accounting);
    assert.equal(f.storage.length, 1);
    assert.equal(f.storage[0].key, 'quiqqer-erp-navigation:user-one');
    f.control.$filter('entwurfe');
    assert.equal(accounting.Children.hidden, false);
    assert.equal(bills.Children.hidden, false);
    assert.equal(drafts.Entry.hidden, false);
    assert.equal(journal.Entry.hidden, true);
    assert.equal(contracts.Entry.hidden, true);
    f.control.$toggle(bills);
    assert.equal(f.storage.length, 1);
    f.control.$filter('');
    assert.equal(accounting.Children.hidden, true);
    assert.equal(bills.Children.hidden, true);
    assert.equal(contracts.Entry.hidden, false);
    const restored = fixture(f.storage[0].value);
    restored.requests[0].resolve(menu());
    assert.equal(restored.control.$nodes[0].Children.hidden, true);
});

test('matching a group exposes its children; empty results and Escape are handled', () => {
    const f = fixture();
    f.requests[0].resolve(menu());
    f.control.$filter('Rechnungen');
    assert.equal(f.control.$nodes[2].Entry.hidden, false);
    assert.equal(f.control.$nodes[3].Entry.hidden, false);
    f.control.$filter('does not exist');
    assert.equal(f.control.$Status.hidden, false);
    f.control.$Input.events.keydown({key: 'Escape', stopPropagation() {}});
    assert.equal(f.control.$query, '');
    assert.equal(f.control.$Status.hidden, true);
});

test('a group action is separate from folding and action failures restore the button', async () => {
    const f = fixture();
    f.requests[0].resolve(menu());
    const node = f.control.$nodes[4];
    assert.notEqual(node.Button, node.Toggle);
    const task = f.control.$activate(node);
    assert.equal(node.busy, true);
    assert.deepEqual(f.loader, ['show', 'hide']);
    assert.equal(node.Icon.className, 'fa fa-circle-o-notch fa-circle-notch fa-spin');
    await f.control.$activate(node);
    assert.equal(f.imports.length, 1);
    f.imports[0].resolve(() => Promise.reject(new Error('Failed')));
    await task;
    assert.equal(node.Button.disabled, false);
    assert.deepEqual(f.loader, ['show', 'hide']);
    assert.equal(node.Icon.className, 'fa fa-angle-right');
    assert.equal(f.control.$Status.hidden, false);
});

test('opens existing panel classes and handles module import failure', async () => {
    const f = fixture();
    f.requests[0].resolve(menu());
    class Target extends f.Panel {}
    Target.isClass = true;
    const task = f.control.$activate(f.control.$nodes[2]);
    f.imports[0].resolve(Target);
    await task;
    assert.equal(f.panels.length, 1);
    const failure = f.control.$activate(f.control.$nodes[2]);
    f.imports[1].reject(new Error('Missing module'));
    await failure;
    assert.equal(f.control.$nodes[2].Button.disabled, false);
});

test('failed loading is retryable and destruction prevents late actions and rendering', async () => {
    const f = fixture('broken json');
    f.requests[0].options.onError();
    assert.equal(f.control.$Retry.hidden, false);
    f.control.$Retry.events.click();
    assert.equal(f.requests.length, 2);
    f.requests[1].resolve(menu());
    const task = f.control.$activate(f.control.$nodes[2]);
    let invoked = false;
    f.control.events.onDestroy();
    f.imports[0].resolve(() => { invoked = true; });
    await task;
    assert.equal(invoked, false);
    const pending = fixture();
    pending.control.events.onDestroy();
    pending.requests[0].resolve(menu());
    assert.equal(pending.control.$nodes.length, 0);
});

test('toolbar toggles all levels, persists folds and keeps search changes temporary', () => {
    const f = fixture();
    assert.equal(f.control.$ToggleAll.getAttribute('disabled'), true);
    f.requests[0].resolve(menu());
    const Button = f.control.$ToggleAll;
    assert.equal(Button.getAttribute('styles').float, 'right');
    assert.equal(Button.getAttribute('text'), undefined);
    assert.equal(Button.getAttribute('title'), 'erp.panel.navigation.collapseAll');
    Button.options.events.onClick();
    assert.equal(f.control.$nodes.filter(n => n.Children).every(n => n.Children.hidden), true);
    assert.equal(Button.getAttribute('title'), 'erp.panel.navigation.expandAll');
    Button.options.events.onClick();
    assert.equal(f.control.$nodes.filter(n => n.Children).every(n => !n.Children.hidden), true);
    assert.equal(f.storage.length, 2);
    f.control.$filter('Entwürfe');
    Button.options.events.onClick();
    assert.equal(f.control.$nodes[0].Children.hidden, true);
    assert.equal(f.storage.length, 2);
    f.control.$filter('');
    assert.equal(f.control.$nodes[0].Children.hidden, false);
    f.control.$filter('no match');
    assert.equal(Button.getAttribute('disabled'), true);
});
