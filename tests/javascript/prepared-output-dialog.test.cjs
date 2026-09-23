const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');

function fixture({fetchPdf, url = '/signing-download.php?requestId=reviewed', name = 'Reviewed contract.pdf'} = {}) {
    const calls = [];
    class Element {
        constructor(tag) {
            this.tagName = tag;
            this.children = [];
            this.dataset = {};
            this.listeners = {};
            this.classList = {add() {}};
            this.contentWindow = {
                focus: () => calls.push('focus'),
                print: () => calls.push('print')
            };
        }
        append(...children) {
            children.forEach(child => this.appendChild(child));
        }
        appendChild(child) {
            child.parent = this;
            this.children.push(child);
            if (this.tagName === 'select' && !this.value) this.value = child.value;
        }
        addEventListener(event, listener) { this.listeners[event] = listener; }
        setAttribute(key, value) { this[key] = value; }
        replaceWith(child) { this.parent.children[this.parent.children.indexOf(this)] = child; }
        click() { calls.push({download: this.download, href: this.href}); }
        remove() { this.parent.children = this.parent.children.filter(child => child !== this); }
    }
    const Content = new Element('div');
    const Submit = {
        disabled: false,
        disable() { this.disabled = true; },
        enable() { this.disabled = false; },
        setAttribute(key, value) { this[key] = value; }
    };
    let definition;
    const pdf = {immutable: true};
    class TestURL extends URL {
        static createObjectURL(blob) { assert.equal(blob, pdf); calls.push('createBlob'); return 'blob:reviewed-pdf'; }
        static revokeObjectURL(blob) { calls.push({revoke: blob}); }
    }
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../bin/backend/controls/OutputDialog.js'), 'utf8'), {
        define(module, dependencies, factory) {
            definition = factory({}, {}, {}, {}, {}, {}, {}, {}, {}, {get: (group, key) => key}, {}, '');
        },
        Class: function(options) { return options; },
        document: {createElement: tag => new Element(tag)},
        window: {location: {href: 'https://erp.example/admin/', origin: 'https://erp.example'}},
        URL: TestURL,
        AbortController,
        fetch: async (request, options) => {
            calls.push({fetch: request, options});
            return fetchPdf ? fetchPdf(options.signal) : {
                ok: true,
                headers: {get: () => 'application/pdf'},
                blob: async () => pdf
            };
        }
    });
    const Control = Object.assign({}, definition, {
        getAttribute: key => ({preparedPdfUrl: url, preparedPdfName: name})[key],
        getContent: () => Content,
        getButton: () => Submit,
        Loader: {hide: () => calls.push('hideLoader')},
        fireEvent: (event, args) => calls.push({event, args})
    });
    return {Control, Content, Submit, calls, pdf};
}

test('prepared output loads exact PDF once with session credentials and only offers print/PDF', async () => {
    const {Control, Content, Submit, calls} = fixture();
    await Control.$openPreparedPdf(Content);
    assert.equal(Submit.disabled, true);
    const State = Control.$preparedPdf;
    assert.deepEqual(State.Select.children.map(option => option.value), ['print', 'pdf']);
    assert.equal(State.Preview.title, 'Reviewed contract.pdf');
    assert.equal(State.Preview.src, 'blob:reviewed-pdf');
    const request = calls.find(call => call.fetch);
    assert.equal(request.fetch, 'https://erp.example/signing-download.php?requestId=reviewed');
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(request.options.headers, undefined);
    State.Preview.listeners.load();
    assert.equal(Submit.disabled, false);
    Control.$onSubmit();
    assert.equal(calls.filter(call => call === 'print').length, 1);
    assert.equal(calls.filter(call => call.fetch).length, 1);
    assert.equal(calls.find(call => call.event)?.args[0].output, 'print');
});

test('save PDF downloads the same reviewed bytes and preserves the given filename', async () => {
    const {Control, Content, Submit, calls} = fixture({name: '<img src=x onerror=alert(1)>.pdf'});
    await Control.$openPreparedPdf(Content);
    const State = Control.$preparedPdf;
    State.Preview.listeners.load();
    assert.equal(Content.children[0].children[0].children[0].textContent, '<img src=x onerror=alert(1)>.pdf');
    State.Select.value = 'pdf';
    State.Select.listeners.change();
    assert.match(Submit.text, /pdf\.btn$/);
    Control.$onSubmit();
    assert.deepEqual(calls.find(call => call.download), {
        download: '<img src=x onerror=alert(1)>.pdf', href: 'blob:reviewed-pdf'
    });
    assert.equal(calls.includes('print'), false);
});

test('a pending or failed PDF never enables output and JSON authentication failures are shown as errors', async () => {
    for (const response of [
        {ok: false, headers: {get: () => 'application/pdf'}},
        {ok: true, headers: {get: () => 'application/json'}}
    ]) {
        const {Control, Content, Submit, calls} = fixture({fetchPdf: () => response});
        await Control.$openPreparedPdf(Content);
        Control.$onSubmit();
        assert.equal(Submit.disabled, true);
        assert.equal(calls.includes('print'), false);
        assert.equal(Content.children[1].role, 'alert');
        assert.equal(calls.includes('createBlob'), false);
    }
});

test('prepared output rejects external and executable URLs before fetching', async () => {
    for (const url of ['https://outside.example/private.pdf', 'javascript:alert(1)', 'data:application/pdf,x']) {
        const {Control, Content, calls} = fixture({url});
        await Control.$openPreparedPdf(Content);
        assert.equal(calls.some(call => call.fetch), false);
        assert.equal(Content.children[1].role, 'alert');
    }
});

test('closing releases private PDF data and aborts requests; late load cannot re-enable actions', async () => {
    const {Control, Content, Submit, calls} = fixture();
    await Control.$openPreparedPdf(Content);
    const State = Control.$preparedPdf;
    Control.$disposePreparedPdf();
    State.Preview.listeners.load();
    assert.equal(Submit.disabled, true);
    assert.equal(State.Controller.signal.aborted, true);
    assert.equal(Control.$preparedPdf, null);
    assert.deepEqual(calls.find(call => call.revoke), {revoke: 'blob:reviewed-pdf'});
});

test('closing during the download prevents late responses creating a private blob', async () => {
    let finish;
    const {Control, Content, calls, pdf} = fixture({fetchPdf: () => new Promise(resolve => { finish = resolve; })});
    const loading = Control.$openPreparedPdf(Content);
    Control.$disposePreparedPdf();
    finish({ok: true, headers: {get: () => 'application/pdf'}, blob: async () => pdf});
    await loading;
    assert.equal(calls.includes('createBlob'), false);
});

test('prepared output rejects unsupported actions and blocks duplicate submissions', async () => {
    const {Control, Content, calls} = fixture();
    await Control.$openPreparedPdf(Content);
    Control.$onSubmit();
    assert.equal(calls.includes('print'), false);
    const State = Control.$preparedPdf;
    State.Preview.listeners.load();
    State.Select.value = 'email';
    Control.$onSubmit();
    assert.equal(calls.some(call => call.event), false);
    State.Select.value = 'print';
    State.Preview.contentWindow.print = () => {
        calls.push('print');
        Control.$onSubmit();
    };
    Control.$onSubmit();
    assert.equal(calls.filter(call => call === 'print').length, 1);
});
