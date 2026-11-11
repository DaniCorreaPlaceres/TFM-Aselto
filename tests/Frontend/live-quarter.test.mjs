import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../assets/controllers/live_quarter_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", 'class Controller {}')
    .replace('export default class', 'globalThis.LiveQuarter = class');

function harness(fetch) {
    const listeners = new Map();
    const element = { addEventListener: (name, callback) => listeners.set(name, callback), removeEventListener() {} };
    const content = { innerHTML: 'previous figures', contains: () => false, replaceChildren() { this.innerHTML = ''; }, querySelector: () => null };
    const document = { hidden: false, activeElement: null, addEventListener() {}, removeEventListener() {} };
    const timers = [];
    const context = vm.createContext({ document, fetch, AbortController, CSS: { escape: value => value }, window: {
        setTimeout: callback => { timers.push(callback); return timers.length; }, clearTimeout() {}, setInterval() {}, clearInterval() {},
    } });
    vm.runInContext(source, context);
    const controller = new context.LiveQuarter();
    Object.assign(controller, { active: true, pending: true, dirty: false, refreshing: false, contentTarget: content, statusTarget: { textContent: '' }, element, urlValue: '/ingresos?year=2026&quarter=4', authValue: '/trimestre/en-vivo/acceso' });
    return { controller, content, document, listeners, timers };
}

test('refresh requests only a financial fragment and replaces its content', async () => {
    const requests = [];
    const { controller, content } = harness(async (url, options) => { requests.push({ url, options }); return { ok: true, status: 200, text: async () => 'updated figures' }; });
    await controller.refresh();
    assert.equal(content.innerHTML, 'updated figures');
    assert.equal(requests[0].url, '/ingresos?year=2026&quarter=4');
    assert.equal(requests[0].options.headers['X-Quarter-Fragment'], '1');
    assert.equal(requests[0].options.cache, 'no-store');
});

test('an unavailable server preserves the latest figures', async () => {
    const { controller, content } = harness(async () => { throw new Error('offline'); });
    await controller.refresh();
    assert.equal(content.innerHTML, 'previous figures');
    assert.match(controller.statusTarget.textContent, /conservan/);
});

test('a revoked permission removes financial content and closes the stream', async () => {
    const { controller, content } = harness(async () => ({ ok: false, status: 403 }));
    let closed = false;
    controller.source = { close: () => { closed = true; } };
    await controller.refresh();
    assert.equal(content.innerHTML, '');
    assert.equal(controller.active, false);
    assert.equal(closed, true);
});

test('an expired session redirect is not inserted as a fragment', async () => {
    const { controller, content } = harness(async () => ({ redirected: true, status: 200 }));
    await controller.refresh();
    assert.equal(content.innerHTML, '');
    assert.equal(controller.active, false);
});

test('edited form contents are never overwritten', async () => {
    let fetched = false;
    const { controller, content } = harness(async () => { fetched = true; });
    controller.dirty = true;
    await controller.refresh();
    controller.manual();
    assert.equal(fetched, false);
    assert.equal(content.innerHTML, 'previous figures');
});

test('an edit made during a request preserves the editor', async () => {
    let complete;
    const { controller, content } = harness(() => new Promise(resolve => { complete = resolve; }));
    const pending = controller.refresh();
    controller.dirty = true;
    complete({ ok: true, status: 200, text: async () => 'must not replace the editor' });
    await pending;
    assert.equal(content.innerHTML, 'previous figures');
    assert.equal(controller.pending, true);
});

test('a detached view cannot receive a late response', async () => {
    let complete;
    const { controller, content } = harness(() => new Promise(resolve => { complete = resolve; }));
    const pending = controller.refresh();
    controller.disconnect();
    complete({ ok: true, status: 200, text: async () => 'late response' });
    await pending;
    assert.equal(content.innerHTML, 'previous figures');
});

test('hidden pages defer requests until visible again', async () => {
    let fetched = false;
    const { controller, document } = harness(async () => { fetched = true; });
    document.hidden = true;
    await controller.refresh();
    assert.equal(fetched, false);
    assert.equal(controller.pending, true);
});

test('a stale permission response cannot clear a reconnected view', async () => {
    let complete;
    const { controller, content } = harness(() => new Promise(resolve => { complete = resolve; }));
    controller.generation = 1;
    const pending = controller.refresh();
    controller.generation = 2;
    complete({ ok: false, status: 403 });
    await pending;
    assert.equal(content.innerHTML, 'previous figures');
    assert.equal(controller.active, true);
});
