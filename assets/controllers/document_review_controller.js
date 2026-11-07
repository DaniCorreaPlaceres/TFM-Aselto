import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['base', 'vat', 'total', 'quota'];

    connect() {
        this.recalculate();
    }

    recalculate(event) {
        const edited = event?.target instanceof HTMLElement ? event.target.closest('[data-field]') : null;

        if (edited instanceof HTMLElement && !this.isEmpty(edited) && !(edited.contains(this.baseTarget) && this.invalidBase())) {
            this.clearGroup(edited);
        }

        const base = this.cents(this.baseTarget.value);
        const rate = this.vatTarget.value;

        if (null === base || '' === rate || undefined === base) {
            this.totalTarget.value = '';
            this.quotaTarget.textContent = 'Cuota de IVA: se calcula con la base y el IVA';

            return;
        }

        const quota = Math.round(base * Number(rate) / 100);
        this.quotaTarget.textContent = `Cuota de IVA: ${this.euros(quota)} €`;
        this.totalTarget.value = this.euros(base + quota);
    }

    submit(event) {
        const invalidBase = this.invalidBase();
        const missing = 'confirm' === event.submitter?.value ? this.missingFields() : [];

        this.clearErrors();

        if (invalidBase) {
            missing.push(this.baseTarget.closest('[data-field]'));
        }

        const groups = missing.filter((group) => group instanceof HTMLElement);

        if (0 === groups.length) {
            return;
        }

        event.preventDefault();
        groups.forEach((group) => this.showError(group, invalidBase && group === this.baseTarget.closest('[data-field]')
            ? group.dataset.invalid
            : group.dataset.message));
        groups[0].querySelector('input, select')?.focus();
        window.dispatchEvent(new CustomEvent('aselto:toast', {
            detail: {
                message: 1 === groups.length ? groups[0].querySelector('.field-error')?.textContent : 'Revisa los campos marcados.',
                kind: 'error',
            },
        }));
    }

    missingFields() {
        return [...this.element.querySelectorAll('[data-field]')].filter((group) => this.isEmpty(group));
    }

    invalidBase() {
        return undefined === this.cents(this.baseTarget.value);
    }

    isEmpty(group) {
        if ('direction' === group.dataset.field) {
            return null === group.querySelector('input:checked');
        }

        const control = group.querySelector('input, select');

        return null === control || '' === control.value.trim();
    }

    showError(group, message) {
        const error = group.querySelector('.field-error');
        const control = group.querySelector('input, select');

        if (!(error instanceof HTMLElement)) {
            return;
        }

        error.hidden = false;
        error.textContent = message ?? '';
        control?.setAttribute('aria-invalid', 'true');

        if ('' !== error.id) {
            control?.setAttribute('aria-describedby', error.id);
        }
    }

    clearErrors() {
        this.element.querySelectorAll('[data-field]').forEach((group) => {
            if (group instanceof HTMLElement) {
                this.clearGroup(group);
            }
        });
    }

    clearGroup(group) {
        const error = group.querySelector('.field-error');

        if (error instanceof HTMLElement) {
            error.hidden = true;
            error.textContent = '';
        }

        group.querySelectorAll('[aria-invalid]').forEach((control) => {
            control.removeAttribute('aria-invalid');
        });
    }

    cents(value) {
        let text = value.replace(/[\s\u00a0]/g, '');

        if ('' === text) {
            return null;
        }

        if (text.includes(',')) {
            text = text.replaceAll('.', '').replace(',', '.');
        }

        if (!/^\d{1,9}(\.\d{1,2})?$/.test(text)) {
            return undefined;
        }

        const [euros, fraction = '0'] = text.split('.');

        return (Number(euros) * 100) + Number(fraction.padEnd(2, '0'));
    }

    euros(cents) {
        return new Intl.NumberFormat('es-ES', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(cents / 100);
    }
}
