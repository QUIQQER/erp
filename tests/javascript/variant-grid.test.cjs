const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {resolve} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function fixture({variants = [], onGridCreate} = {}) {
    let definition;
    let dialog;
    let grid;
    let request;
    let submit;
    let selected = [];

    class Element {
        inject() { return this; }
        set() {}
        getSize() { return {x: 900, y: 600}; }
    }

    class Grid {
        constructor(container, options) {
            this.options = options;
            grid = this;
        }
        addEvents() {}
        setData(data) { this.data = data; }
        getSelectedData() { return selected; }
    }

    class Confirm {
        constructor(options) {
            this.options = options;
            this.Loader = {show() {}, hide() {}};
            dialog = this;
        }
        getContent() { return new Element(); }
        open() { this.options.events.onOpen(this); }
        close() { this.closed = true; }
    }

    class Product {
        getVariantFields() { return Promise.resolve([{id: 42, title: 'Color'}]); }
    }

    const modules = {
        'qui/QUI': {fireEvent(name, args) {
            assert.equal(name, 'quiqqerProductsVariantGridCreate');
            onGridCreate?.(...args);
        }},
        'qui/controls/windows/Confirm': Confirm,
        'package/quiqqer/products/bin/classes/Product': Product,
        'controls/grid/Grid': Grid,
        Ajax: {get(name, callback, params) {
            request = {name, params};
            callback(variants);
        }},
        Locale: {get: (group, key) => key, getCurrent: () => 'de'}
    };
    const filename = resolve(__dirname, '../../bin/backend/controls/articles/product/AddProductWindow.js');
    vm.runInNewContext(readFileSync(filename, 'utf8'), {
        define(name, dependencies, factory) {
            definition = factory(...dependencies.map(dependency => modules[dependency]));
        },
        Class: function (value) { return value; },
        Element,
        Promise
    }, {filename});

    const control = Object.create(definition);

    return {
        control,
        async open() {
            submit = control.$openVariantChildren(21);
            await new Promise(resolve => setImmediate(resolve));
        },
        getGrid: () => grid,
        getRequest: () => request,
        async select(index) {
            selected = [grid.data.data[index]];
            dialog.options.events.onSubmit(dialog);
            return submit;
        },
        getDialog: () => dialog
    };
}

test('ERP variant grid uses the shared column hook and preserves extension values', async () => {
    let hook;
    const f = fixture({
        variants: [0, -1.25, 12.3456].map((quantity, index) => ({
            id: index + 100,
            active: true,
            priority: 0,
            defaultVariant: index === 0,
            fields: [{id: 42, value: 'blue', options: {entries: []}}, {id: 18, value: 0, options: {}}],
            packageQuantity: quantity,
            packageFlag: false,
            packageMissing: null
        })),
        onGridCreate(Control, options, metadata) {
            hook = {Control, metadata};
            // Replacement also has to work: consumers may replace the column model.
            options.columnModel = [...options.columnModel, {
                dataIndex: 'packageQuantity', dataType: 'number', sortable: false
            }];
            options.storageKey = 'test-erp-variants';
        }
    });
    await f.open();

    const grid = f.getGrid();
    assert.equal(hook.Control, f.control);
    assert.equal(hook.metadata.context, 'erp.variant-selection');
    assert.equal(hook.metadata.productId, 21);
    assert.equal(grid.options.columnModel.at(-1).dataIndex, 'packageQuantity');
    assert.equal(grid.options.storageKey, 'test-erp-variants');
    assert.deepEqual(Array.from(grid.data.data, row => row.packageQuantity), [0, -1.25, 12.3456]);
    assert.equal(grid.data.data[0].priority, 0);
    assert.equal(grid.data.data[0].packageFlag, false);
    assert.equal(grid.data.data[0].packageMissing, null);
    assert.equal(grid.data.data[0]['field-42'], 'blue');
    assert.equal(Object.hasOwn(grid.data.data[0], 'field-undefined'), false);
    assert.equal(f.getRequest().name, 'package_quiqqer_erp_ajax_products_getVariantChildren');
    assert.equal(f.getRequest().params.productId, 21);
    assert.equal(await f.select(1), 101);
    assert.equal(f.getDialog().closed, true);
});

test('ERP variant selection keeps its standard columns and data without extensions', async () => {
    const f = fixture({variants: [{id: 31, active: false, fields: []}]});
    await f.open();

    assert.equal(f.getGrid().options.storageKey, 'quiqqer-erp-addproductwindow-variants');
    assert.equal(f.getGrid().options.columnModel.some(column => column.dataIndex === 'productNo'), true);
    assert.equal(f.getGrid().data.data[0].id, 31);
    assert.equal(f.getGrid().data.data[0].title, '-');
    assert.equal(await f.select(0), 31);
});

test('ERP variant selection accepts an empty result', async () => {
    const f = fixture();
    await f.open();
    assert.equal(f.getGrid().data.data.length, 0);
});
