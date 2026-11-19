const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function fixture(options = {}) {
    class Node {
        constructor() {
            this.children = [];
            this.dataset = {};
            this.attributes = {};
            this.listeners = {};
            this.value = '';
            this.validityMessage = '';
        }

        append(...children) { this.children.push(...children); }
        appendChild(child) { this.append(child); }
        setAttribute(key, value) { this.attributes[key] = value; }
        addEventListener(key, callback) { this.listeners[key] = callback; }
        setCustomValidity(message) { this.validityMessage = message; }
        reportValidity() { this.reported = true; }
        focus() { document.activeElement = this; }

        get valueAsNumber() {
            return this.value === '' ? NaN : Number(this.value);
        }

        checkValidity() {
            const value = this.valueAsNumber;
            return !this.validityMessage && Number.isFinite(value) &&
                (this.min === undefined || value >= this.min) &&
                (this.max === undefined || value <= this.max);
        }
    }

    const document = {
        activeElement: null,
        createElement: () => new Node()
    };
    const events = [];
    let TaxAmount;
    vm.runInNewContext(readFileSync(join(__dirname, '../../bin/backend/controls/articles/TaxAmount.js'), 'utf8'), {
        document,
        Class: function (definition) {
            function Control(attributes) {
                this.options = {...definition.options, ...attributes};
            }
            Control.prototype = Object.assign({
                getAttribute(key) { return this.options[key]; },
                setAttribute(key, value) { this.options[key] = value; },
                fireEvent(name, values) { events.push({name, values}); }
            }, definition);
            return Control;
        },
        define(name, dependencies, factory) {
            TaxAmount = factory({}, {get: (group, key) => key});
        }
    });
    const Control = new TaxAmount({rate: 19, amount: 1.5951, ...options});
    Control.create();
    return {Control, document, events};
}

test('editing the tax amount and rate preserves the other value and its precision', () => {
    const {Control, events} = fixture();
    Control.$Amount.value = '1.595123';
    Control.$Amount.listeners.change();
    assert.equal(Control.getValue().vat, 19);
    assert.equal(Control.getValue().sum, 1.595123);
    assert.equal(events[0].name, 'amountChange');
    assert.equal(events[0].values[1], 1.595123);

    Control.$Rate.value = '7';
    Control.$Rate.listeners.change();
    assert.equal(Control.getValue().vat, 7);
    assert.equal(Control.getValue().sum, 1.595123);
    assert.equal(events[1].name, 'rateChange');
    assert.equal(Control.$Remove.attributes['aria-label'], 'manual.removeTax (7%)');
});

test('duplicate rates and empty amounts retain the saved values for correction', () => {
    const {Control, events} = fixture({validateRate: rate => rate === 7 ? 'Duplicate rate' : ''});
    Control.$Rate.value = '7';
    Control.$Rate.listeners.change();
    assert.equal(Control.getValue().vat, 19);
    assert.equal(Control.$Rate.validityMessage, 'Duplicate rate');
    assert.equal(Control.$Rate.reported, true);

    Control.$Amount.value = '';
    Control.$Amount.listeners.change();
    assert.equal(Control.getValue().sum, 1.5951);
    assert.equal(Control.$Amount.reported, true);
    assert.equal(events.length, 0);
});

test('external synchronization preserves an active edit and removal emits its own event', () => {
    const {Control, document, events} = fixture();
    document.activeElement = Control.$Amount;
    Control.$Amount.value = '3.123';
    Control.setAmount(2.2345);
    assert.equal(Control.$Amount.value, '3.123');
    assert.equal(Control.getValue().sum, 2.2345);
    assert.equal(events.length, 0);

    document.activeElement = null;
    Control.setAmount(2.2345);
    assert.equal(Control.$Amount.value, 2.2345);
    Control.$Remove.listeners.click();
    assert.equal(events[0].name, 'remove');
    assert.equal(events[0].values[0], Control);
});
