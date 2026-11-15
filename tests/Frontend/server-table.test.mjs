import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = (await readFile(new URL('../../assets/controllers/server_table_controller.js', import.meta.url), 'utf8')).replace("import { Controller } from '@hotwired/stimulus';", 'class Controller {}');
const { default: ServerTable } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

function fixture() {
    let configuration;
    let download;
    let destroyed = false;
    globalThis.window = { location: { origin: 'http://localhost:8080', assign: value => { download = value; } }, DataTable: class { constructor(table, options) { configuration = options; } destroy() { destroyed = true; } } };
    globalThis.document = { addEventListener() {}, removeEventListener() {} };
    const controller = new ServerTable();
    controller.urlValue = '/listados/inbox?section=submitted';
    controller.exportValue = '/listados/inbox/exportar?section=submitted';
    controller.filtersValue = [];
    controller.statusTarget = { textContent: '' };
    controller.element = { querySelector: () => ({ setAttribute() {} }) };
    controller.connect();
    return { controller, configuration, downloaded: () => download, destroyed: () => destroyed };
}

test('server tables preserve the authorized endpoint context and all current filters for export', () => {
    const { controller, downloaded } = fixture();
    controller.filter = 'Justificante pendiente';
    controller.lastQuery = { draw: 5, start: 100, length: 25, search: { value: 'Cliente & compañía' }, order: [{ column: 2, dir: 'desc' }] };
    controller.download('xlsx');
    const url = downloaded();
    assert.equal(url.pathname, '/listados/inbox/exportar');
    assert.equal(url.searchParams.get('section'), 'submitted');
    assert.equal(url.searchParams.get('q'), 'Cliente & compañía');
    assert.equal(url.searchParams.get('filter'), 'Justificante pendiente');
    assert.equal(url.searchParams.get('column'), '2');
    assert.equal(url.searchParams.get('order'), 'desc');
    assert.equal(url.searchParams.get('format'), 'xlsx');
});

test('expired-session HTML is never inserted as table data', async () => {
    const { configuration, controller } = fixture();
    globalThis.fetch = async () => ({ ok: true, headers: new Headers({ 'Content-Type': 'text/html' }) });
    let page;
    await configuration.ajax({ draw: 4, start: 0, length: 25 }, result => { page = result; });
    assert.equal(page.draw, 4);
    assert.deepEqual(page.data, []);
    assert.match(controller.statusTarget.textContent, /No se ha podido/);
});

test('a successful page keeps complete totals instead of treating its rows as the whole dataset', async () => {
    const { configuration } = fixture();
    const expected = { draw: 2, recordsTotal: 131, recordsFiltered: 100, data: [['First row']] };
    globalThis.fetch = async () => ({ ok: true, headers: new Headers({ 'Content-Type': 'application/json' }), json: async () => expected });
    let page;
    await configuration.ajax({ draw: 2, start: 0, length: 25 }, result => { page = result; });
    assert.deepEqual(page, expected);
});

test('Turbo teardown aborts pending page requests and destroys the widget', () => {
    const { controller, destroyed } = fixture();
    const signal = controller.abort.signal;
    controller.disconnect();
    assert.equal(signal.aborted, true);
    assert.equal(destroyed(), true);
});
