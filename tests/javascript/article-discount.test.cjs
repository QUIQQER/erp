const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');

function fixture(input, locale = 'de-DE') {
    let definition;
    let Money;
    let inputElement;
    let editInitialValue;
    const requests = [];
    const errors = [];
    const attributes = {discount: '', vat: 19, unitPrice: 10};
    const Locale = {getNumberFormatter: options => new Intl.NumberFormat(locale, options)};
    // Fixed server responses; locale parsing and rejection are tested with real PHP separately.
    const amounts = new Map([
        ['5,5', 5.5], ['5.5', 5.5], ['0,5', 0.5], ['0.5', 0.5],
        ['4,622', 4.622], ['4.622', locale === 'de-DE' ? 4622 : 4.622],
        ['1.234,56', 1234.56], ['1,234.56', 1234.56], ['1’234.56', 1234.56],
        ['1 234,56', 1234.56], ['', null], ['-', null], ['0', 0]
    ]);
    const Ajax = {
        get(endpoint, resolve, params) {
            requests.push({endpoint, params});
            const payload = JSON.parse(params.value ?? params.price);
            assert.equal(payload.locale, new Intl.NumberFormat(locale).resolvedOptions().locale);
            const value = typeof payload.value === 'number' ? payload.value : amounts.get(payload.value);
            if (value === undefined) {
                params.onError(new Error('Invalid price input'));
                return;
            }
            if (endpoint === 'package_quiqqer_erp_ajax_money_validatePrice') {
                resolve(value);
            } else {
                assert.equal(endpoint, 'package_quiqqer_erp_ajax_calcNettoPrice');
                resolve(Math.round(value / 1.19 * 1000) / 1000);
            }
        }
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../bin/backend/utils/Money.js'), 'utf8'), {
        define(name, dependencies, factory) { Money = factory(Locale); },
        require(dependencies, callback) { callback(Ajax); }
    });
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../bin/backend/controls/articles/Article.js'), 'utf8'), {
        define(name, dependencies, factory) {
            definition = factory({}, {}, {}, {}, {}, {}, Money, {}, {}, Locale, Ajax);
        },
        Class: function(options) { return options; },
        typeOf: value => typeof value,
        console: {error: error => errors.push(error)},
        Element: function(tag, options) {
            inputElement = {
                ...options,
                inject() { return this; },
                focus() {}, select() {},
                destroy() { this.destroyed = true; this.value = ''; },
                addEvents(events) { this.events = events; },
                checkValidity() { return this.valid !== false; },
                reportValidity() { this.reported = true; },
                set(options) { Object.assign(this, options); }
            };
            return inputElement;
        }
    });
    const Control = Object.assign({}, definition, {
        getAttribute: key => attributes[key],
        setAttribute: (key, value) => { attributes[key] = value; },
        $Discount: {set() {}},
        $DiscountBrutto: {get: () => attributes.discount},
        $UnitPrice: {set() {}},
        $UnitPriceBrutto: {get: () => attributes.unitPrice},
        $Formatter: new Intl.NumberFormat(locale),
        $createEditField: (container, initial) => {
            editInitialValue = initial;
            return Promise.resolve(input);
        },
        fireEvent() {},
        calc: () => Promise.resolve()
    });
    return {
        Control, Money, attributes, requests, errors,
        getEditInitialValue: () => editInitialValue,
        createNumberField: () => definition.$createEditField.call(Control, {}, '10', 'number'),
        getInputElement: () => inputElement
    };
}

for (const [locale, input, expected] of [
    ['de-DE', '5,5', 5.5], ['de-DE', '5.5', 5.5], ['de-DE', '0,5', 0.5],
    ['en-US', '5.5', 5.5], ['en-GB', '0.5', 0.5], ['fr-FR', '5,5', 5.5]
]) {
    test(`gross discount ${input} uses ${locale} and preserves its computed number`, async () => {
        const {Control, attributes, requests} = fixture(input, locale);
        await Control.$onEditBruttoDiscount();
        assert.equal(attributes.discount, Math.round(expected / 1.19 * 1000) / 1000);
        assert.equal(Math.round(attributes.discount * 1.19 * 100) / 100, expected);
        assert.equal(JSON.parse(requests[0].params.price).value, input);
        assert.equal(typeof JSON.parse(requests[1].params.value).value, 'number');
    });
}

for (const [locale, input] of [
    ['de-DE', '1.234,56'], ['en-US', '1,234.56'], ['de-CH', '1’234.56'], ['fr-FR', '1 234,56']
]) {
    test(`net discount transmits the display locale ${locale}`, async () => {
        const {Control, attributes, requests} = fixture(input, locale);
        await Control.$onEditDiscount();
        assert.equal(attributes.discount, 1234.56);
        assert.equal(JSON.parse(requests[0].params.value).value, input);
        assert.equal(JSON.parse(requests[1].params.value).value, 1234.56);
    });
}

test('numeric unit prices reach validation as JSON numbers without locale parsing', async () => {
    const {Control, attributes, requests} = fixture(4.622);
    await Control.$onEditUnitPriceQuantity();
    assert.equal(attributes.unitPrice, 4.622);
    assert.equal(JSON.parse(requests[0].params.value).value, 4.622);
    await Control.$onEditBruttoPrice();
    assert.equal(JSON.parse(requests[1].params.price).value, 4.622);
});

test('number field reads valueAsNumber and retains invalid edits for correction', async () => {
    const f = fixture();
    const result = f.createNumberField();
    const input = f.getInputElement();
    assert.equal(input.required, true);
    assert.equal(input.step, 'any');
    input.value = 'invalid';
    input.valueAsNumber = NaN;
    input.events.blur();
    assert.equal(input.reported, true);
    assert.equal(input.destroyed, undefined);
    input.value = '4.622';
    input.valueAsNumber = 4.622;
    input.events.blur();
    assert.equal(await result, 4.622);
});

test('numeric discounts are still validated by the server', async () => {
    const {Control, attributes, requests} = fixture();
    await Control.setDiscount(4.622);
    assert.equal(attributes.discount, 4.622);
    assert.equal(JSON.parse(requests[0].params.value).value, 4.622);
});

test('discount edits show the same regional number format used for parsing', async () => {
    const f = fixture('4,622');
    f.attributes.discount = 4.622;
    await f.Control.$onEditDiscount();
    assert.equal(f.getEditInitialValue(), '4,622');
    assert.equal(f.attributes.discount, 4.622);
    const percentage = fixture('4,622%');
    percentage.attributes.discount = '4.622%';
    await percentage.Control.$onEditBruttoDiscount();
    assert.equal(percentage.getEditInitialValue(), '4,622%');
    assert.equal(percentage.attributes.discount, '4.622%');
});

test('percentage, zero and empty discounts remain supported', async () => {
    for (const [input, expected] of [['5,5%', '5.5%'], ['0', null], ['', null]]) {
        const {Control, attributes} = fixture(input);
        await Control.$onEditBruttoDiscount();
        assert.equal(attributes.discount, expected);
    }
});

test('failed server validation keeps the previous amount and handles the rejection', async () => {
    const {Control, attributes, errors} = fixture('12oops');
    attributes.discount = 5.5;
    await Control.$onEditDiscount();
    assert.equal(attributes.discount, 5.5);
    assert.equal(errors.length, 1);
});

test('non-finite values cannot silently turn into JSON null', async () => {
    const {Money, requests} = fixture();
    for (const value of [NaN, Infinity, -Infinity, null, {}, true]) {
        await assert.rejects(Money.validatePrice(value), /Invalid price input/);
    }
    assert.equal(requests.length, 0);
});
