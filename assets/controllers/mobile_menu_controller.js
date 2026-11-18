import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle'];

    connect() {
        this.toggleTarget.hidden = false;
        this.element.classList.add('navigation-ready');
        this.close();
        this.breakpoint = window.matchMedia('(max-width: 991px)');
        this.onBreakpoint = () => this.close();
        this.breakpoint.addEventListener('change', this.onBreakpoint);
        this.beforeCache = () => this.close();
        document.addEventListener('turbo:before-cache', this.beforeCache);
    }

    toggle() {
        const open = this.toggleTarget.getAttribute('aria-expanded') !== 'true';
        this.element.classList.toggle('navigation-open', open);
        this.toggleTarget.setAttribute('aria-expanded', String(open));
        this.toggleTarget.setAttribute('aria-label', open ? 'Cerrar menú de navegación' : 'Abrir menú de navegación');
    }

    close() {
        this.element.classList.remove('navigation-open');
        this.toggleTarget.setAttribute('aria-expanded', 'false');
        this.toggleTarget.setAttribute('aria-label', 'Abrir menú de navegación');
    }

    escape(event) {
        if (this.breakpoint.matches && this.toggleTarget.getAttribute('aria-expanded') === 'true') {
            event.preventDefault();
            this.close();
            this.toggleTarget.focus();
        }
    }

    navigate(event) {
        if (event.target.closest('a') && this.breakpoint.matches) this.close();
    }

    disconnect() {
        this.breakpoint.removeEventListener('change', this.onBreakpoint);
        document.removeEventListener('turbo:before-cache', this.beforeCache);
    }
}
