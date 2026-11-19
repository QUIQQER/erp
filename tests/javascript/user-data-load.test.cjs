const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {resolve} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function deferred() {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return {promise, resolve, reject};
}

function user(id = 42, values = {}) {
    return {
        isLoaded: () => true,
        getId: () => id,
        getAttribute: key => values[key] || false,
        getAddressList: () => Promise.resolve([{id: 7, uuid: 'default-address', mail: '"contact@example.org"'}])
    };
}

function fixture(getUser = () => user()) {
    let definition;
    const events = {};
    const attributes = {userId: 42, addressId: 'historic-address', company: 'Historic company'};
    const errors = [];
    const requests = [];
    const notices = [];
    const dependencies = {
        Users: {get: getUser},
        Locale: {get: (pkg, key) => key === 'exception.lib.user.not.found' ? 'Requested user was not found' : key},
        Ajax: {get(name, callback, options) { requests.push({name, callback, options}); }}
    };
    const context = vm.createContext({
        Class: function(value) { return value; }, clearTimeout,
        document: {createElement: () => ({dataset: {}, setAttribute(key, value) { this[key] = value; }})},
        console: {error: error => errors.push(error)},
        define(name, names, factory) { definition = factory(...names.map(name => dependencies[name] || {})); }
    });
    vm.runInContext('JSON.decode = JSON.parse', context);
    vm.runInContext(readFileSync(resolve(__dirname, '../../bin/backend/controls/userData/UserData.js'), 'utf8'), context);
    const Control = {
        ...definition,
        parent() {}, addEvents(values) { Object.assign(events, values); },
        getAttribute: key => attributes[key],
        setAttribute(key, value) { attributes[key] = value; },
        setAttributes(values) { Object.assign(attributes, values); }
    };
    Control.initialize({});
    Control.$Elm = {prepend: node => notices.push(node)};
    for (const key of ['$ContactPerson', '$BtnContactPersonSelect', '$BtnContactEmailSelect', '$AddressField', '$ContactEmail']) {
        Control[key] = {value: '', disabled: false};
    }
    Control.$AddressRow = {setStyle() {}};
    let changes = 0;
    let refreshes = 0;
    Control.$fireChange = () => { changes++; };
    Control.$refreshValues = () => { refreshes++; };
    return {Control, events, attributes, errors, requests, notices, changes: () => changes, refreshes: () => refreshes};
}

async function settle() {
    for (let i = 0; i < 15; i++) await Promise.resolve();
}

for (const cached of [false, true]) {
    test('missing ' + (cached ? 'cached' : 'fresh') + ' user has an actionable error and retains stored customer/address data', async () => {
        const missing = user(0);
        missing.isLoaded = () => cached;
        missing.load = () => Promise.reject(missing);
        const {Control, attributes, errors, notices, requests, changes} = fixture(() => missing);
        await Control.setValue({userId: 42, addressId: 'historic-address', company: 'Invoice company'});
        assert.equal(attributes.userId, 42);
        assert.equal(attributes.addressId, 'historic-address');
        assert.equal(attributes.company, 'Invoice company');
        assert.equal(requests.length, 0, 'do not request addresses of a missing user');
        assert.equal(changes(), 0, 'do not save or clear a failed reference');
        assert.equal(errors.length, 1);
        assert.equal(errors[0].name, 'Error');
        assert.equal(errors[0].userId, 42);
        assert.match(errors[0].message, /Requested user was not found \(42\)/);
        assert.equal(notices[0].role, 'alert');
        assert.match(notices[0].textContent, /\(42\)/);
        assert.equal(Control.$loading, false);
        assert.equal(Control.$ContactPerson.disabled, false);
        assert.equal(Control.$BtnContactPersonSelect.disabled, true);
        assert.equal(Control.$BtnContactEmailSelect.disabled, true);
    });
}

test('real user request failure keeps its original error and ends the busy state', async () => {
    const error = new Error('Access denied');
    const Entity = user();
    Entity.isLoaded = () => false;
    Entity.load = () => Promise.reject(error);
    const {Control, errors, notices} = fixture(() => Entity);
    assert.equal(await Control.$setDataByUserId(42), null);
    assert.equal(errors[0], error);
    assert.equal(notices[0].textContent, 'Access denied');
    assert.equal(Control.$loading, false);
    assert.equal(Control.$ContactPerson.disabled, false);
});

test('setValue awaits the explicit address before restoring the historical document snapshot', async () => {
    const {Control, requests, attributes, notices} = fixture();
    let finished = false;
    const loading = Control.setValue({userId: 42, addressId: 'invoice-address', company: 'Invoice snapshot'}).then(() => { finished = true; });
    await settle();
    assert.equal(requests.length, 1);
    assert.equal(requests[0].options.uid, 42);
    assert.equal(requests[0].options.aid, 'invoice-address');
    assert.equal(finished, false);
    requests[0].callback({uuid: 'invoice-address', company: 'Live customer'});
    await loading;
    assert.equal(finished, true);
    assert.equal(attributes.company, 'Invoice snapshot');
    assert.equal(attributes.addressId, 'invoice-address');
    assert.equal(Control.$BtnContactPersonSelect.disabled, false);
    assert.equal(notices.length, 0);
});

test('address request failure settles restore, reports the error and keeps the document snapshot', async () => {
    const {Control, requests, attributes, errors} = fixture();
    const loading = Control.setValue({userId: 42, addressId: 'invoice-address', company: 'Invoice snapshot'});
    await settle();
    const error = new Error('Address unavailable');
    requests[0].options.onError(error);
    await loading;
    assert.equal(errors[0], error);
    assert.equal(attributes.addressId, 'invoice-address');
    assert.equal(attributes.company, 'Invoice snapshot');
});

test('a superseded customer load cannot overwrite a newer selection or restore an old snapshot', async () => {
    const pending = deferred();
    const old = user(42);
    old.isLoaded = () => false;
    old.load = () => pending.promise;
    const latest = user(99);
    const {Control, attributes, errors} = fixture(id => id === 42 ? old : latest);
    const older = Control.setValue({userId: 42, company: 'Old document'});
    await Control.$setDataByUserId(99);
    attributes.company = 'New selection';
    pending.resolve(old);
    await older;
    assert.equal(attributes.userId, 99);
    assert.equal(attributes.company, 'New selection');
    assert.equal(errors.length, 0);
});

test('destroyed controls ignore late user rejection and restore callbacks', async () => {
    const pending = deferred();
    const missing = user(0);
    missing.isLoaded = () => false;
    missing.load = () => pending.promise;
    const {Control, events, attributes, errors, notices, refreshes} = fixture(() => missing);
    const loading = Control.setValue({userId: 42, company: 'Late snapshot'});
    events.onDestroy();
    pending.reject(missing);
    await loading;
    assert.equal(attributes.company, 'Historic company');
    assert.equal(errors.length, 0);
    assert.equal(notices.length, 0);
    assert.equal(refreshes(), 0);
});

test('a previous address response cannot update a different customer', async () => {
    const {Control, requests, attributes, changes} = fixture();
    const address = Control.setAddressId('old-address');
    attributes.userId = 99;
    requests[0].callback({uuid: 'old-address', company: 'Obsolete address'});
    await address;
    assert.equal(attributes.company, 'Historic company');
    assert.equal(changes(), 0);
});

test('empty customer address lookup resolves without an Ajax request', async () => {
    const {Control, requests, attributes} = fixture();
    attributes.userId = '';
    await Control.setAddressId('address');
    assert.equal(requests.length, 0);
});

test('successful recovery hides the old error and enables address selection', async () => {
    let Entity = user(0);
    const {Control, notices} = fixture(() => Entity);
    await Control.$setDataByUserId(42);
    assert.equal(notices[0].hidden, false);
    Entity = user(99);
    await Control.$setDataByUserId(99);
    assert.equal(notices[0].hidden, true);
    assert.equal(Control.$BtnContactPersonSelect.disabled, false);
    assert.equal(Control.$BtnContactEmailSelect.disabled, false);
});
