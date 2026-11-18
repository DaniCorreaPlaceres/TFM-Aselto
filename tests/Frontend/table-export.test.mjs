import { test } from 'node:test';
import assert from 'node:assert/strict';
import { exportCell } from '../../assets/ui/table-export.mjs';

test('exports underlying amounts for Excel and Spanish CSV without currency symbols', () => {
    const node = { dataset: { export: '1234.56' }, textContent: '1.234,56 €' };
    assert.equal(exportCell(node, ''), '1234.56');
    assert.equal(exportCell(node, '', true), '1234,56');
});
test('preserves zero and negative numeric data', () => {
    assert.equal(exportCell({ dataset: { export: '0.00' } }, ''), '0.00');
    assert.equal(exportCell({ dataset: { export: '-123.45' } }, '', true), '-123,45');
});
test('dates keep their unambiguous ISO representation', () => {
    assert.equal(exportCell({ dataset: { export: '2026-11-01' }, textContent: '01/11/2026' }, ''), '2026-11-01');
});
test('removes layout whitespace and exports text rather than HTML', () => {
    assert.equal(exportCell({ textContent: '  Cliente\n de ejemplo  ' }, '<a>Cliente</a>'), 'Cliente de ejemplo');
});
test('neutralizes formula prefixes in fiscal names and other free text', () => {
    for (const prefix of ['=', '+', '-', '@', '\t=']) {
        const result = exportCell({ textContent: `${prefix}FORMULA()` }, '');
        assert.ok(result.startsWith("'"));
    }
});
test('does not allow a forged raw value to bypass formula protection', () => {
    assert.equal(exportCell({ dataset: { export: '=FORMULA()' } }, ''), "'=FORMULA()");
});
