import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { acceptedTypes, uploadError, uploadSize } from '../../assets/ui/document-upload.mjs';

const pdf = { name: 'ejemplo.pdf', type: 'application/pdf', size: 1000 };
const image = { name: 'foto.png', type: 'image/png', size: 2000 };
const source = readFileSync(new URL('../../assets/controllers/document_upload_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", 'class Controller {}')
    .replace("import { acceptedTypes, uploadError, uploadSize } from '../ui/document-upload.mjs';", '')
    .replace('export default class', 'globalThis.Upload = class');

function harness(transferAvailable = true) {
    const revoked = [];
    const listeners = new Map();
    const element = () => ({ hidden: false, disabled: false, textContent: '', attributes: new Map(),
        classList: { values: new Set(), add(value) { this.values.add(value); }, remove(value) { this.values.delete(value); } },
        setAttribute(name, value) { this.attributes.set(name, value); }, removeAttribute(name) { this.attributes.delete(name); },
        focus() { this.focused = true; }, click() { this.clicked = true; },
    });
    const context = vm.createContext({ acceptedTypes, uploadError, uploadSize, URL: { createObjectURL: () => 'blob:synthetic', revokeObjectURL: value => revoked.push(value) },
        DataTransfer: class { constructor() { if (!transferAvailable) throw new Error('Unsupported'); this.files = []; this.items = { add: file => this.files.push(file) }; } },
        document: { addEventListener: (name, fn) => listeners.set(name, fn), removeEventListener: name => listeners.delete(name) },
    });
    vm.runInContext(source, context);
    const controller = new context.Upload();
    controller.element = element();
    for (const name of ['field', 'zone', 'selection', 'name', 'size', 'preview', 'error', 'status', 'submit']) controller[`${name}Target`] = element();
    controller.fieldTarget.files = [];
    Object.defineProperty(controller.fieldTarget, 'value', { set(value) { if (value === '') this.files = []; } });
    controller.controlsTargets = [element(), element(), element()];
    controller.connect();
    return { controller, revoked, listeners };
}
const event = files => ({ dataTransfer: { files }, prevented: false, preventDefault() { this.prevented = true; } });

test('valid types and the exact eight MB boundary are accepted', () => {
    for (const type of acceptedTypes.split(',')) assert.equal(uploadError([{ ...pdf, type, size: 8_000_000 }]), null);
    assert.equal(uploadError([{ ...pdf, type: '', name: 'DOCUMENTO.PDF' }]), null);
});
test('missing, multiple, empty, oversized and unsupported files are rejected', () => {
    for (const files of [[], [pdf, image], [{ ...pdf, size: 0 }], [{ ...pdf, size: 8_000_001 }], [{ ...pdf, type: 'text/html' }], [{ ...pdf, type: '', name: 'unknown.exe' }]]) assert.ok(uploadError(files));
});
test('file sizes use Spanish decimals and units', () => {
    assert.equal(uploadSize(1_500_000), '1,5 MB');
    assert.equal(uploadSize(1500), '1,5 KB');
});
test('image selection displays its name and releases the previous object URL', () => {
    const { controller: c, revoked } = harness();
    c.fieldTarget.files = [image]; c.selected();
    assert.equal(c.nameTarget.textContent, 'foto.png');
    assert.equal(c.previewTarget.hidden, false);
    c.fieldTarget.files = [pdf]; c.selected();
    assert.deepEqual(revoked, ['blob:synthetic']);
    assert.equal(c.previewTarget.hidden, true);
});
test('drop copies one file into the same native upload field', () => {
    const { controller: c } = harness();
    const drop = event([pdf]); c.drop(drop);
    assert.ok(drop.prevented);
    assert.equal(c.fieldTarget.files[0], pdf);
    assert.equal(c.nameTarget.textContent, pdf.name);
});
test('a PDF with an image suffix is not rendered as an image', () => {
    const { controller: c } = harness(); c.fieldTarget.files = [{ ...pdf, name: 'documento.jpg' }]; c.selected();
    assert.equal(c.previewTarget.hidden, true);
});
test('an invalid replacement clears the old selection instead of uploading it', () => {
    const { controller: c } = harness();
    c.fieldTarget.files = [pdf]; c.selected(); c.drop(event([pdf, image]));
    assert.equal(c.fieldTarget.files.length, 0);
    assert.equal(c.selectionTarget.hidden, true);
    assert.match(c.errorTarget.textContent, /solo documento/);
});
test('unavailable transfer support offers the native file picker', () => {
    const { controller: c } = harness(false); c.drop(event([pdf]));
    assert.match(c.errorTarget.textContent, /Elegir archivo/);
    assert.equal(c.fieldTarget.files.length, 0);
});
test('camera and picker reuse one field and remove camera constraints when switching', () => {
    const { controller: c } = harness(); c.photo();
    assert.equal(c.fieldTarget.attributes.get('capture'), 'environment');
    assert.ok(c.fieldTarget.clicked);
    c.choose();
    assert.equal(c.fieldTarget.attributes.has('capture'), false);
    assert.equal(c.fieldTarget.accept, acceptedTypes);
});
test('removing a file clears preview and returns focus to the picker', () => {
    const { controller: c, revoked } = harness(); c.fieldTarget.files = [image]; c.selected(); c.remove();
    assert.equal(c.fieldTarget.files.length, 0);
    assert.equal(c.selectionTarget.hidden, true);
    assert.ok(c.controlsTargets[0].focused);
    assert.deepEqual(revoked, ['blob:synthetic']);
});
test('empty submission is stopped and the accessible error is shown', () => {
    const { controller: c } = harness(); const submission = event(); c.submit(submission);
    assert.ok(submission.prevented);
    assert.equal(c.errorTarget.hidden, false);
    assert.equal(c.fieldTarget.attributes.get('aria-invalid'), 'true');
});
test('submission prevents duplicates without disabling the file needed by FormData', () => {
    const { controller: c } = harness(); c.fieldTarget.files = [pdf]; c.selected(); c.submit(event());
    const second = event(); c.submit(second);
    assert.ok(second.prevented);
    assert.equal(c.submitTarget.disabled, true);
    assert.equal(c.fieldTarget.disabled, false);
    c.finished({ detail: { success: false } });
    assert.equal(c.submitTarget.disabled, false);
    assert.match(c.statusTarget.textContent, /volver a intentarlo/);
});
test('Turbo cache clears selected files and object URLs', () => {
    const { controller: c, listeners, revoked } = harness(); c.fieldTarget.files = [image]; c.selected();
    listeners.get('turbo:before-cache')(); c.disconnect();
    assert.equal(c.fieldTarget.files.length, 0);
    assert.deepEqual(revoked, ['blob:synthetic']);
    assert.equal(listeners.size, 0);
});
