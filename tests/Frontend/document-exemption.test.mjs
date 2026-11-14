import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../assets/controllers/document_review_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", 'class Controller {}')
    .replace('export default class', 'globalThis.Review = class');

function harness(direction = 'ingreso') {
    const context = vm.createContext({ HTMLElement: class {}, Intl });
    vm.runInContext(source, context);
    const controller = new context.Review();
    Object.assign(controller, {
        element: { querySelector: () => ({ value: direction }) }, taxNameValue: 'IVA',
        hasExemptionTarget: true, exemptionTarget: { checked: true },
        baseTarget: { value: '100' }, rateTarget: { value: '21' }, rateGroupTarget: {},
        quotaTarget: {}, totalTarget: {}, hasWithholdingTarget: true,
        withholdingTarget: { value: '15' }, withholdingGroupTarget: {}, hasWithholdingQuotaTarget: true, withholdingQuotaTarget: {},
        hasSurchargeTarget: true, surchargeTarget: { value: '5.2', options: [], selectedOptions: [] },
        surchargeGroupTarget: {}, hasSurchargeQuotaTarget: true, surchargeQuotaTarget: {},
    });
    return controller;
}

test('exempt income keeps IRPF and calculates a zero indirect tax quota', () => {
    const review = harness();
    review.recalculate();
    assert.equal(review.totalTarget.value, '85,00');
    assert.match(review.quotaTarget.textContent, /0,00 €.*exento/);
    assert.equal(review.withholdingTarget.value, '15');
    assert.equal(review.rateTarget.disabled, true);
    assert.equal(review.rateTarget.value, '');
    assert.equal(review.surchargeTarget.value, '');
});

test('exempt expense clears surcharge and incompatible withholding', () => {
    const review = harness('gasto');
    review.recalculate();
    assert.equal(review.totalTarget.value, '100,00');
    assert.equal(review.surchargeTarget.value, '');
    assert.equal(review.surchargeTarget.disabled, true);
    assert.equal(review.withholdingTarget.value, '');
});

test('removing exemption requires a new percentage and recalculates when chosen', () => {
    const review = harness();
    review.recalculate();
    review.exemptionTarget.checked = false;
    review.recalculate();
    assert.equal(review.totalTarget.value, '');
    assert.equal(review.rateTarget.disabled, false);
    assert.equal(review.rateGroupTarget.hidden, false);
    review.rateTarget.value = '21';
    review.recalculate();
    assert.equal(review.totalTarget.value, '106,00');
});
