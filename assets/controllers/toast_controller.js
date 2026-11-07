import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item'];

    connect() {
        this.onToast = (event) => {
            const detail = event.detail ?? {};
            this.add(String(detail.message ?? ''), detail.kind === 'error' ? 'error' : 'success');
        };
        window.addEventListener('aselto:toast', this.onToast);
        this.itemTargets.forEach((item) => this.present(item));
    }

    disconnect() {
        window.removeEventListener('aselto:toast', this.onToast);
    }

    add(message, kind) {
        if ('' === message) {
            return;
        }

        this.element.replaceChildren();
        const item = document.createElement('p');
        item.className = 'error' === kind ? 'toast toast-error' : 'toast toast-success';
        item.setAttribute('role', 'error' === kind ? 'alert' : 'status');
        item.textContent = message;
        this.element.append(item);
        this.present(item);
    }

    present(item) {
        if (item.querySelector('button')) {
            return;
        }

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'quiet';
        close.textContent = 'Cerrar';
        close.addEventListener('click', () => item.remove());
        item.append(close);
        window.setTimeout(() => item.remove(), 10000);
    }
}
