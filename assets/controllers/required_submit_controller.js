import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['field', 'error'];

    static values = { message: String };

    guard(event) {
        this.clear();

        if (this.hasFile()) {
            return;
        }

        event.preventDefault();
        this.errorTarget.hidden = false;
        this.errorTarget.textContent = this.messageValue;
        this.fieldTarget.setAttribute('aria-invalid', 'true');
        this.fieldTarget.setAttribute('aria-describedby', this.errorTarget.id || this.ensureErrorId());
        this.fieldTarget.focus();
        window.dispatchEvent(new CustomEvent('aselto:toast', {
            detail: { message: this.messageValue, kind: 'error' },
        }));
    }

    clear() {
        this.errorTarget.hidden = true;
        this.errorTarget.textContent = '';
        this.fieldTarget.removeAttribute('aria-invalid');
    }

    hasFile() {
        return this.fieldTarget.files instanceof FileList && this.fieldTarget.files.length > 0;
    }

    ensureErrorId() {
        if ('' === this.errorTarget.id) {
            this.errorTarget.id = `${this.fieldTarget.id}-error`;
        }

        return this.errorTarget.id;
    }
}
