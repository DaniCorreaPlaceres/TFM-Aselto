import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['base', 'rate', 'total', 'quota', 'surcharge', 'surchargeGroup', 'surchargeQuota', 'withholding', 'withholdingGroup', 'withholdingQuota'];
    static values = { taxName: String };

    connect() {
        this.recalculate();
    }

    recalculate(event) {
        const edited = event?.target instanceof HTMLElement ? event.target.closest('[data-field]') : null;

        if (edited instanceof HTMLElement && !this.isEmpty(edited) && !(edited.contains(this.baseTarget) && this.invalidBase()) && !(edited.contains(this.rateTarget) && this.invalidRate()) && !(this.hasWithholdingTarget && edited.contains(this.withholdingTarget) && this.invalidWithholding())) {
            this.clearGroup(edited);
        }

        const withholdingRate = this.refreshWithholding();
        const surchargeRate = this.refreshSurcharge();
        const base = this.cents(this.baseTarget.value);
        const rate = this.rateBasisPoints();

        if (null === base || null === rate || undefined === rate || undefined === base || undefined === withholdingRate) {
            this.totalTarget.value = '';
            if (this.hasSurchargeQuotaTarget) {
                this.surchargeQuotaTarget.textContent = 'Cuota de recargo: se calcula con la base y el recargo.';
            }
            if (this.hasWithholdingQuotaTarget) {
                this.withholdingQuotaTarget.textContent = 'Cuota de retención: se calcula con la base y el porcentaje.';
            }
            this.quotaTarget.textContent = `Cuota de ${this.taxNameValue}: se calcula con la base y ${this.taxNameValue}`;

            return;
        }

        const quota = Math.floor((base * rate + 5000) / 10000);
        this.quotaTarget.textContent = `Cuota de ${this.taxNameValue}: ${this.euros(quota)} €`;
        const surchargeQuota = Math.floor((base * surchargeRate + 5000) / 10000);
        if (this.hasSurchargeQuotaTarget) {
            this.surchargeQuotaTarget.textContent = '' === this.surchargeTarget.value ? 'Sin recargo.' : `Cuota de recargo: ${this.euros(surchargeQuota)} €`;
        }
        const withholdingQuota = Math.floor((base * (withholdingRate ?? 0) + 5000) / 10000);
        if (this.hasWithholdingQuotaTarget) {
            this.withholdingQuotaTarget.textContent = null === withholdingRate ? 'Sin retención.' : `Cuota de retención: ${this.euros(withholdingQuota)} €`;
        }
        this.totalTarget.value = this.euros(base + quota + surchargeQuota - withholdingQuota);
    }

    refreshSurcharge() {
        if (!this.hasSurchargeTarget) {
            return 0;
        }

        const direction = this.element.querySelector('input[name="direction"]:checked')?.value;
        const allowed = 'IVA' === this.taxNameValue && 'gasto' === direction;
        const rate = this.rateBasisPoints();

        this.surchargeGroupTarget.hidden = !allowed;
        this.surchargeTarget.disabled = !allowed;
        for (const option of this.surchargeTarget.options) {
            option.disabled = '' !== option.value && (!allowed || Number(option.dataset.vatRate) !== rate);
        }

        if (!allowed || this.surchargeTarget.selectedOptions[0]?.disabled) {
            this.surchargeTarget.value = '';
        }

        return Math.round(Number(this.surchargeTarget.value) * 100);
    }

    refreshWithholding() {
        if (!this.hasWithholdingTarget) {
            return null;
        }

        const allowed = 'ingreso' === this.element.querySelector('input[name="direction"]:checked')?.value;
        this.withholdingGroupTarget.hidden = !allowed;
        this.withholdingTarget.disabled = !allowed;
        if (!allowed) {
            this.withholdingTarget.value = '';
        }

        return this.withholdingBasisPoints();
    }

    withholdingBasisPoints() {
        if (!this.hasWithholdingTarget || '' === this.withholdingTarget.value.trim()) {
            return null;
        }

        const text = this.withholdingTarget.value.trim().replace(',', '.');
        if (!/^\d{1,3}(\.\d{1,2})?$/.test(text)) {
            return undefined;
        }

        const [whole, fraction = '0'] = text.split('.');
        const rate = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));

        return rate <= 10000 ? rate : undefined;
    }

    invalidWithholding() {
        return undefined === this.withholdingBasisPoints();
    }

    submit(event) {
        const invalidBase = this.invalidBase();
        const invalidRate = this.invalidRate();
        const invalidWithholding = this.invalidWithholding();
        const missing = 'confirm' === event.submitter?.value ? this.missingFields() : [];

        this.clearErrors();

        if (invalidBase) {
            missing.push(this.baseTarget.closest('[data-field]'));
        }

        if (invalidRate) {
            missing.push(this.rateTarget.closest('[data-field]'));
        }

        if (invalidWithholding) {
            missing.push(this.withholdingTarget.closest('[data-field]'));
        }

        const groups = missing.filter((group) => group instanceof HTMLElement);

        if (0 === groups.length) {
            return;
        }

        event.preventDefault();
        groups.forEach((group) => this.showError(group, (invalidBase && group === this.baseTarget.closest('[data-field]')) || (invalidRate && group === this.rateTarget.closest('[data-field]')) || (invalidWithholding && group === this.withholdingTarget.closest('[data-field]'))
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
        return [...this.element.querySelectorAll('[data-field]')].filter((group) => !group.hasAttribute('data-optional') && this.isEmpty(group));
    }

    invalidBase() {
        return undefined === this.cents(this.baseTarget.value);
    }

    invalidRate() {
        return undefined === this.rateBasisPoints();
    }

    rateBasisPoints() {
        const text = this.rateTarget.value.trim().replace(',', '.');

        if ('' === text) {
            return null;
        }

        if (!/^\d{1,3}(\.\d{1,2})?$/.test(text)) {
            return undefined;
        }

        const [whole, fraction = '0'] = text.split('.');
        const rate = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));

        return rate <= 10000 ? rate : undefined;
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
