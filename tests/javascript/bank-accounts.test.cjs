const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');

function fixture() {
    const requests = [];
    const dialogs = [];
    class Element {
        constructor() { this.children = []; this.dataset = {}; this.attributes = {}; }
        setAttribute(name, value) { this.attributes[name] = value; }
        removeAttribute(name) { delete this.attributes[name]; }
        appendChild(child) { this.children.push(child); child.parent = this; }
        replaceChildren() { this.children = []; }
        remove() { this.parent.children = this.parent.children.filter(child => child !== this); }
        querySelector(selector) {
            return this.children.find(child => selector === '[data-name="' + child.dataset.name + '"]') || null;
        }
        focus() { this.focused = true; }
        after(element) { this.afterElement = element; }
    }
    let definition;
    const context = {
        document: {createElement: () => new Element()},
        Class: function(value) { return value; },
        define(name, dependencies, factory) {
            const request = method => (name, resolve, options) => requests.push({method, name, resolve, ...options});
            definition = factory({}, function(options) {
                this.open = () => dialogs.push(options);
            }, {getFormData: form => form.data}, {get: (pkg, key) => key}, {
                get: request('get'), post: request('post')
            }, {escape: value => value, render: () => ''}, '', '');
        }
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,
        '../../bin/backend/controls/settings/BankAccounts.js'), 'utf8'), context);
    const control = Object.assign({}, definition, {$BankAccounts: {5: {id: 5, title: 'Original'}}});
    const Input = new Element();
    Input.value = '{"123":{"title":"Stale settings"}}';
    Input.attributes.name = 'bankAccounts.accounts';
    control.getElm = () => Input;
    control.$Container = new Element();
    const status = new Element(); status.dataset.name = 'status';
    const create = new Element(); create.dataset.name = 'create';
    control.$Container.appendChild(status);
    control.$Container.appendChild(create);
    let builds = 0;
    control.$buildList = () => builds++;
    const Content = new Element();
    const Form = new Element();
    Form.dataset.name = 'bank-account-form';
    Form.data = {title: 'Edited', default: true};
    Form.reportValidity = () => true;
    Content.appendChild(Form);
    const Submit = {disabled: false, disable() { this.disabled = true; }, enable() { this.disabled = false; }};
    const Win = {closed: false, getContent: () => Content, getButton: () => Submit, close() { this.closed = true; }};
    return {control, requests, dialogs, Input, Win, Submit, Content, Form, status, builds: () => builds};
}

test('opening accounts reads persisted state instead of the surrounding settings snapshot', async () => {
    const f = fixture();
    const promise = f.control.$onImport();
    assert.equal(f.requests[0].method, 'get');
    assert.equal(f.requests[0].name, 'package_quiqqer_erp_ajax_settings_bankAccounts_getList');
    assert.equal(f.Input.attributes.name, undefined);
    const persisted = {8: {id: 8, title: 'Persisted'}};
    f.requests[0].resolve(persisted);
    await promise;
    assert.equal(f.control.$BankAccounts, persisted);
    assert.equal(f.builds(), 1);
});

test('failed initial read never exposes an editable empty account list', async () => {
    const f = fixture();
    const promise = f.control.$onImport();
    f.requests[0].onError(new Error('Offline'));
    await promise;
    assert.equal(f.builds(), 0);
    assert.equal(f.control.$Container.children[0].attributes.role, 'alert');
});

test('save waits for the server, prevents duplicate submissions and then adopts persisted defaults', async () => {
    const f = fixture();
    const before = f.control.$BankAccounts;
    const promise = f.control.$persist(f.Win, 'save', {id: '5', data: '{"title":"Edited"}'});
    f.control.$persist(f.Win, 'save', {id: '5', data: '{}'});
    assert.equal(f.requests.length, 1);
    assert.equal(f.requests[0].method, 'post');
    assert.equal(f.requests[0].id, '5');
    assert.equal(f.control.$BankAccounts, before);
    assert.equal(f.Win.closed, false);
    assert.equal(f.Submit.disabled, true);
    const persisted = {5: {id: 5, title: 'Edited', default: true}, 6: {id: 6, default: false}};
    f.requests[0].resolve(persisted);
    await promise;
    assert.equal(f.control.$BankAccounts, persisted);
    assert.equal(f.Win.closed, true);
    assert.match(f.status.textContent, /saved$/);
    assert.equal(f.Input.value, '{"123":{"title":"Stale settings"}}');
});

test('failed save leaves the dialog and form intact and allows retry', async () => {
    const f = fixture();
    const before = f.control.$BankAccounts;
    const promise = f.control.$persist(f.Win, 'save', {id: '5', data: '{}'});
    f.requests[0].onError(new Error('Permission denied'));
    await promise;
    assert.equal(f.control.$BankAccounts, before);
    assert.equal(f.Win.closed, false);
    assert.equal(f.Form.data.title, 'Edited');
    assert.equal(f.Submit.disabled, false);
    assert.equal(f.Content.querySelector('[data-name="save-error"]').attributes.role, 'alert');
    const retry = f.control.$persist(f.Win, 'save', {id: '5', data: '{}'});
    assert.equal(f.Content.querySelector('[data-name="save-error"]'), null);
    f.requests[1].resolve({5: {id: 5, title: 'Edited'}});
    await retry;
    assert.equal(f.Win.closed, true);
});

test('deletion does not remove the card until the server confirms persistence', async () => {
    const f = fixture();
    const promise = f.control.$persist(f.Win, 'delete', {id: '5'});
    assert.ok(f.control.$BankAccounts[5]);
    assert.equal(f.requests[0].name, 'package_quiqqer_erp_ajax_settings_bankAccounts_delete');
    f.requests[0].resolve([]);
    await promise;
    assert.equal(Object.keys(f.control.$BankAccounts).length, 0);
    assert.equal(f.Win.closed, true);
});

test('editor validates first and sends form values without changing the local account', async () => {
    const f = fixture();
    f.control.$openEditor('5');
    assert.equal(f.requests.length, 0);
    const submit = f.dialogs[0].events.onSubmit;
    f.Form.reportValidity = () => false;
    submit(f.Win);
    assert.equal(f.requests.length, 0);
    f.Form.reportValidity = () => true;
    const promise = submit(f.Win);
    assert.deepEqual(JSON.parse(f.requests[0].data), f.Form.data);
    assert.equal(f.requests[0].id, '5');
    assert.equal(f.control.$BankAccounts[5].title, 'Original');
    f.requests[0].resolve({5: {id: 5, title: 'Edited'}});
    await promise;
});
