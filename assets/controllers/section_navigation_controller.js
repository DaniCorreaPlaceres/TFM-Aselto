import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    navigate(event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href^="#quarter-"]');
        if (!link) return;
        const section = document.getElementById(link.getAttribute('href').slice(1));
        if (!section || !this.element.contains(section)) return;
        // Stay on the current page: a Turbo hash visit caches and tears down live widgets.
        event.preventDefault();
        section.focus({ preventScroll: true });
        section.scrollIntoView({ block: 'start' });
    }
}
