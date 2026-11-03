const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function loadControl(file, dependencies = {}) {
    let control;
    const context = vm.createContext({
        Class: function (definition) {
            return definition;
        },
        define(name, names, factory) {
            control = factory(...names.map(name => dependencies[name] || {}));
        }
    });
    vm.runInContext('Object.merge = Object.assign;', context);
    vm.runInContext(readFileSync(join(__dirname, '../../bin/backend/controls/articles', file), 'utf8'), context);
    return control;
}

const ArticleList = loadControl('ArticleList.js');
const requests = [];
const Article = loadControl('Article.js', {
    'package/quiqqer/erp/bin/backend/utils/Money': {
        serializeInput: value => value,
        validatePrice: value => Promise.resolve(Number(value))
    },
    Ajax: {
        get(name, resolve, options) {
            requests.push(options);
            resolve(options.price / (1 + Number(options.vat) / 100));
        }
    }
});

function article(vat, calculatedVat = 19) {
    const attributes = {vat, unitPrice: 100};
    const cell = () => ({
        text: '',
        getElement: () => null,
        set(key, value) {
            this.text = typeof key === 'object' ? key.html : value;
        }
    });
    return Object.assign(Object.create(Article), {
        $created: true,
        $calculate: true,
        $bruttoCalc: {},
        $Total: cell(),
        $UnitPrice: cell(),
        $Price: cell(),
        $VAT: cell(),
        $DiscountBrutto: cell(),
        getAttribute: key => attributes[key],
        setAttribute: (key, value) => attributes[key] = value,
        getAttributes: () => ({...attributes}),
        getType: () => Article.Type,
        showLoader() {},
        hideLoader() {},
        fireEvent() {},
        getCurrencyFormatter: () => Promise.resolve({format: value => String(value)}),
        $calc: () => Promise.resolve({
            vat: calculatedVat,
            unitPrice: attributes.unitPrice,
            calculated: {nettoSubSum: attributes.unitPrice, nettoSum: attributes.unitPrice}
        })
    });
}

for (const missing of ['', null, undefined]) {
    test(`displayed VAT is saved for missing value ${String(missing)}`, async () => {
        const instance = article(missing);
        await instance.calc();
        assert.equal(instance.$VAT.text, '19%');
        const saved = ArticleList.save.call({$articles: [instance]});
        assert.equal(saved[0].vat, 19);
    });
}

test('net and gross entry keep the displayed rate in the saved position', async () => {
    for (const grossInput of [false, true]) {
        const instance = article('');
        await instance.calc();
        const netPrice = grossInput ? await instance.getNettoPrice(119, false) : 100;
        await instance.setUnitPrice(netPrice);
        const [saved] = ArticleList.save.call({$articles: [instance]});
        assert.equal(saved.unitPrice, 100);
        assert.equal(saved.vat, 19);
    }
    assert.equal(requests.at(-1).vat, 19);
});

for (const vat of [0, '0', 7, 5.5, 23]) {
    test(`explicit/imported VAT ${vat} is preserved`, async () => {
        const instance = article(vat);
        await instance.setVat(vat);
        const [saved] = ArticleList.save.call({$articles: [instance]});
        assert.equal(saved.vat, Number(vat));
    });
}

test('a calculated zero rate is stored as explicit zero', async () => {
    const instance = article('', 0);
    await instance.calc();
    assert.equal(instance.getAttribute('vat'), 0);
});

test('invalid calculation does not turn missing VAT into zero', async () => {
    const instance = article('', NaN);
    await instance.calc();
    assert.equal(instance.getAttribute('vat'), '');
});
