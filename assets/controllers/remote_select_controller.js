import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { url: String, total: Number };
    static targets = ['select', 'query', 'status', 'previous', 'next'];

    connect() { this.offset = 0; this.sequence = 0; this.total = this.totalValue; this.update(); }
    prevent(event) { event.preventDefault(); }
    search() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => { this.offset = 0; this.load(); }, 400);
    }
    previous() { this.offset = Math.max(0, this.offset - 25); this.load(); }
    next() { this.offset += 25; this.load(); }
    update() {
        this.previousTarget.disabled = this.offset === 0;
        this.nextTarget.disabled = this.offset + 25 >= this.total;
        this.statusTarget.textContent = this.total ? `${this.offset + 1}–${Math.min(this.offset + 25, this.total)} de ${this.total} opciones` : 'No hay opciones con esta búsqueda.';
    }
    async load() {
        this.abort?.abort(); this.abort = new AbortController();
        const sequence = ++this.sequence;
        const url = new URL(this.urlValue, window.location.origin);
        url.searchParams.set('start', this.offset); url.searchParams.set('length', 25);
        url.searchParams.set('q', this.queryTarget.value.slice(0, 120));
        const selected = this.selectTarget.selectedOptions[0];
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: this.abort.signal });
            if (!response.ok || !response.headers.get('Content-Type')?.includes('application/json')) throw new Error();
            const page = await response.json();
            if (sequence !== this.sequence || !this.element.isConnected) return;
            const placeholder = this.selectTarget.options[0].text;
            this.selectTarget.replaceChildren(new Option(placeholder, ''));
            for (const item of page.data) this.selectTarget.add(new Option(item.label, item.value));
            if (selected?.value) {
                if (![...this.selectTarget.options].some(option => option.value === selected.value)) this.selectTarget.add(new Option(selected.text, selected.value));
                this.selectTarget.value = selected.value;
            }
            this.total = page.total; this.update();
        } catch (error) {
            if (error.name !== 'AbortError') this.statusTarget.textContent = 'No se han podido cargar las opciones. Espera y vuelve a buscar.';
        }
    }
    disconnect() { clearTimeout(this.timer); this.abort?.abort(); ++this.sequence; }
}
