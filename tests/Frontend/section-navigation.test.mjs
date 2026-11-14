import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../assets/controllers/section_navigation_controller.js', import.meta.url), 'utf8')
    .replace("import { Controller } from '@hotwired/stimulus';", 'class Controller {}')
    .replace('export default class', 'globalThis.SectionNavigation = class');

test('internal navigation focuses and scrolls without a hash visit that tears down live widgets', () => {
    const calls = [];
    const section = { focus: () => calls.push('focus'), scrollIntoView: () => calls.push('scroll') };
    const context = vm.createContext({ document: { getElementById: id => id === 'quarter-movements' ? section : null } });
    vm.runInContext(source, context);
    const controller = new context.SectionNavigation();
    controller.element = { contains: node => node === section };
    const link = { getAttribute: () => '#quarter-movements' };
    controller.navigate({ button: 0, target: { closest: () => link }, preventDefault: () => calls.push('prevent') });
    assert.deepEqual(calls, ['prevent', 'focus', 'scroll']);
});

test('modified clicks, ordinary links and sections outside the component retain native navigation', () => {
    const context = vm.createContext({ document: { getElementById: () => ({}) } });
    vm.runInContext(source, context);
    const controller = new context.SectionNavigation();
    controller.element = { contains: () => false };
    const reject = () => assert.fail('Native navigation must be retained');
    controller.navigate({ button: 0, ctrlKey: true, preventDefault: reject });
    controller.navigate({ button: 0, target: { closest: () => null }, preventDefault: reject });
    controller.navigate({ button: 0, target: { closest: () => ({ getAttribute: () => '#quarter-history' }) }, preventDefault: reject });
});
