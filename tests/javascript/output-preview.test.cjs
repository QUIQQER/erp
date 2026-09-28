// Run with node --experimental-vm-modules --test tests/javascript/*.test.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');

function fixture({status = 200, contentType = 'application/pdf', bytes = [37, 80, 68, 70], failure, pdfFailure,
    signRequests = false, printFailure} = {}) {
    const calls = [];
    const timers = new Map();
    let nextTimer = 0;
    class Element {
        constructor(tag) {
            this.tagName = tag;
            this.children = [];
            this.style = {};
            this.dataset = {};
            this.listeners = {};
            this.contentWindow = {
                listeners: {},
                addEventListener(event, callback) { this.listeners[event] = callback; },
                focus: () => calls.push('focus'),
                print: () => {
                    if (printFailure) throw printFailure;
                    calls.push('print');
                }
            };
        }
        replaceChildren(...children) { this.children = children; }
        appendChild(child) { child.parent = this; this.children.push(child); }
        setAttribute(key, value) { this[key] = value; }
        getContext() { return {}; }
        addEventListener(event, callback) { this.listeners[event] = callback; }
        remove() { if (this.parent) this.parent.children = this.parent.children.filter(child => child !== this); }
        click() { calls.push({download: this.download, href: this.href}); }
    }
    const Body = new Element('body');
    class TestURL extends URL {
        static createObjectURL(blob) { calls.push({blob}); return 'blob:output-pdf'; }
        static revokeObjectURL(url) { calls.push({revoke: url}); }
    }
    const Preview = new Element('div');
    const Content = {
        querySelector(selector) {
            assert.equal(selector, '[data-name="preview"]');
            return Preview;
        },
        getElement: () => Preview
    };
    let definition;
    const context = vm.createContext({
        define(module, dependencies, factory) {
            definition = factory({}, {}, {}, {}, {}, {}, {}, {}, {}, {get: (group, key) => key}, {}, '');
        },
        Class: function(options) { return options; },
        document: {createElement: tag => new Element(tag), body: Body},
        Blob,
        URL: TestURL,
        Headers,
        URLSearchParams,
        URL_OPT_DIR: '/packages/',
        fetch: async (url, options) => {
            calls.push({fetch: url, options});
            if (failure) throw failure;
            return {
                ok: status >= 200 && status < 300,
                status,
                headers: {get: name => name === 'Content-Type' ? contentType : 'inline; filename="Invoice 42.pdf"'},
                arrayBuffer: async () => Uint8Array.from(bytes).buffer
            };
        }
    });
    context.window = context;
    context.location = {href: 'https://erp.example/admin/', origin: 'https://erp.example'};
    context.setTimeout = callback => { const id = ++nextTimer; timers.set(id, callback); return id; };
    context.clearTimeout = id => timers.delete(id);
    const jwt = 'test.' + Buffer.from(JSON.stringify({iat: Date.now() / 1000, exp: Date.now() / 1000 + 3600}))
        .toString('base64url') + '.signature';

    if (signRequests) {
        if (process.env.QUIQQER_FRS_SCRIPT) {
            context.document.querySelector = () => ({content: jwt});
            context.atob = value => Buffer.from(value, 'base64').toString();
            context.XMLHttpRequest = class { open() {} send() {} };
            vm.runInContext(fs.readFileSync(process.env.QUIQQER_FRS_SCRIPT, 'utf8'), context);
        } else {
            // Exercise the fetch-decorator contract without requiring the optional FRS package in CI.
            const originalFetch = context.fetch;
            context.fetch = (url, options) => originalFetch(url, {
                ...options, headers: new Headers({'X-Jwt-Token': jwt})
            });
        }
    }
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../bin/backend/controls/OutputDialog.js'), 'utf8'), context, {
        importModuleDynamically: async () => {
            calls.push('import');
            const module = new vm.SyntheticModule(['GlobalWorkerOptions', 'getDocument'], function() {
                this.setExport('GlobalWorkerOptions', {});
                this.setExport('getDocument', options => {
                    calls.push({pdf: Array.from(options.data)});
                    return {promise: pdfFailure ? Promise.reject(pdfFailure) : Promise.resolve({
                        numPages: 2,
                        getPage: async number => ({
                            getViewport: () => ({width: 600, height: 800}),
                            render: () => {
                                calls.push({render: number});
                                return {promise: Promise.resolve()};
                            }
                        })
                    })};
                });
            }, {context});
            await module.link(() => {});
            await module.evaluate();
            return module;
        }
    });
    const Control = Object.assign({}, definition, {
        getAttribute: key => ({entityId: '42&show=0', entityType: 'invoice', entityPlugin: 'quiqqer/invoice'})[key],
        getId: () => 'output-42',
        getContent: () => Content,
        $PDFView: {getStatus: () => true},
        $Template: {id: 'default', provider: 'quiqqer/erp'},
        Loader: {show: () => calls.push('show'), hide: () => calls.push('hide')}
    });
    return {Control, Preview, calls, Body, timers, jwt};
}

for (const status of [401, 403]) {
    test(`HTTP ${status} shows a permission message and handles the rejected preview`, async () => {
        const {Control, Preview, calls} = fixture({status, contentType: 'text/html'});
        await Control.$renderPreview();
        assert.equal(Preview.children[0].role, 'alert');
        assert.equal(Preview.children[0].textContent, 'exception.no.permission');
        assert.equal(calls.includes('import'), false);
        assert.equal(calls.at(-1), 'hide');
    });
}

for (const options of [
    {status: 500},
    {contentType: 'text/html'},
    {contentType: 'application/json'},
    {contentType: null},
    {bytes: []}
]) {
    test(`invalid response never reaches pdf.js: ${JSON.stringify(options)}`, async () => {
        const {Control, Preview, calls} = fixture(options);
        await Control.$renderPreview();
        assert.equal(Preview.children[0].textContent, 'controls.OutputDialog.preview_error');
        assert.equal(Preview.children[0].role, 'alert');
        assert.equal(calls.includes('import'), false);
        assert.equal(calls.at(-1), 'hide');
    });
}

test('network failures retain the error object and stop the loader', async () => {
    const failure = new Error('Network unavailable');
    const {Control, calls} = fixture({failure});
    await assert.rejects(Control.showAsPDF(), error => error === failure);
    assert.equal(calls.at(-1), 'hide');
});

test('the preview caller also handles a rejection without an error object', async () => {
    const {Control, Preview} = fixture();
    Control.showAsPDF = () => Promise.reject();
    await Control.$renderPreview();
    assert.equal(Preview.children[0].textContent, 'controls.OutputDialog.preview_error');
});

test('pdf.js failures are handled by the caller and rendered as text', async () => {
    const {Control, Preview, calls} = fixture({pdfFailure: new Error('<script>private details</script>')});
    await Control.$renderPreview();
    assert.equal(Preview.children.length, 1);
    assert.equal(Preview.children[0].textContent, 'controls.OutputDialog.preview_error');
    assert.equal(calls.at(-1), 'hide');
});

test('valid PDF bytes are fetched once with session credentials and every page is rendered', async () => {
    const {Control, Preview, calls} = fixture({contentType: 'application/pdf; charset=binary'});
    await Control.$renderPreview();
    const requests = calls.filter(call => call.fetch);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].options.credentials, 'same-origin');
    const query = new URL(requests[0].fetch, 'https://erp.example').searchParams;
    assert.equal(query.get('id'), '42&show=0');
    assert.equal(query.get('show'), '1');
    assert.deepEqual(calls.find(call => call.pdf).pdf, [37, 80, 68, 70]);
    assert.deepEqual(calls.filter(call => call.render).map(call => call.render), [1, 2]);
    assert.equal(Preview.children.length, 2);
    assert.equal(Preview.children[0].tagName, 'canvas');
    assert.equal(Preview.children[0].width, 600);
    assert.equal(calls.at(-1), 'hide');
});

test('request signing also applies to PDF preview', async () => {
    const {Control, calls, jwt} = fixture({signRequests: true});
    await Control.$renderPreview();
    assert.equal(calls.find(call => call.fetch).options.headers.get('X-Jwt-Token'), jwt);
});

for (const signRequests of [false, true]) {
    test(`download uses fetch, preserves the filename and releases its blob; signing=${signRequests}`, async () => {
        const {Control, calls, Body, timers, jwt} = fixture({signRequests});
        await Control.saveAsPdf();
        const request = calls.find(call => call.fetch);
        assert.equal(request.options.credentials, 'same-origin');
        assert.equal(request.options.headers?.get('X-Jwt-Token'), signRequests ? jwt : undefined);
        assert.deepEqual(calls.find(call => call.download), {download: 'Invoice 42.pdf', href: 'blob:output-pdf'});
        assert.equal(calls.find(call => call.blob).blob.type, 'application/pdf');
        assert.equal(Body.children.length, 0);
        Array.from(timers.values()).at(-1)();
        assert.deepEqual(calls.at(-1), {revoke: 'blob:output-pdf'});
    });

    test(`print loads signed PDF bytes into a local blob frame; signing=${signRequests}`, async () => {
        const {Control, calls, Body, jwt} = fixture({signRequests});
        const printing = Control.print();
        await new Promise(resolve => setImmediate(resolve));
        const Frame = Body.children[0];
        assert.equal(Frame.src, 'blob:output-pdf');
        assert.equal(calls.filter(call => call.fetch).length, 1);
        assert.equal(calls.find(call => call.fetch).options.headers?.get('X-Jwt-Token'), signRequests ? jwt : undefined);
        Frame.listeners.load();
        await printing;
        assert.equal(calls.includes('print'), true);
        Frame.contentWindow.listeners.afterprint();
        assert.equal(Body.children.length, 0);
        assert.deepEqual(calls.at(-1), {revoke: 'blob:output-pdf'});
    });
}

test('failed output does not print or offer a bogus download', async () => {
    const {Control, calls, Body} = fixture({status: 403});
    await assert.rejects(Control.print(), error => error.status === 403);
    await assert.rejects(Control.saveAsPdf(), error => error.status === 403);
    assert.equal(calls.some(call => call.blob || call.download || call === 'print'), false);
    assert.equal(Body.children.length, 0);
});

test('print errors release the private PDF and preserve the error', async () => {
    const failure = new Error('Print unavailable');
    const {Control, calls, Body, timers} = fixture({printFailure: failure});
    const printing = Control.print();
    await new Promise(resolve => setImmediate(resolve));
    Body.children[0].listeners.load();
    await assert.rejects(printing, error => error === failure);
    assert.equal(Body.children.length, 0);
    assert.equal(timers.size, 0);
    assert.deepEqual(calls.at(-1), {revoke: 'blob:output-pdf'});
});
