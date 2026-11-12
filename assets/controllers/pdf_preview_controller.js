import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['canvas', 'status', 'previous', 'next', 'text'];
    static values = { url: String, library: String, worker: String, resources: String };

    async connect() {
        this.active = true;
        this.pageNumber = 1;
        try {
            const pdfjs = await import(this.libraryValue);
            if (!this.active) return;
            pdfjs.GlobalWorkerOptions.workerSrc = this.workerValue;
            this.loading = pdfjs.getDocument({
                url: this.urlValue, withCredentials: true, isEvalSupported: false,
                standardFontDataUrl: `${this.resourcesValue}/standard_fonts/`,
                cMapUrl: `${this.resourcesValue}/cmaps/`, cMapPacked: true,
                wasmUrl: `${this.resourcesValue}/wasm/`,
            });
            this.pdf = await this.loading.promise;
            if (!this.active) { await this.pdf.destroy(); return; }
            this.observer = new ResizeObserver(() => {
                clearTimeout(this.resizeTimer);
                this.resizeTimer = setTimeout(() => this.render(), 150);
            });
            this.observer.observe(this.element);
            await this.render();
        } catch (error) {
            if (this.active) this.statusTarget.textContent = 'No se puede mostrar la vista previa. Puedes abrir el archivo original con el enlace inferior.';
        }
    }

    previous() { if (this.pageNumber > 1) { this.pageNumber--; this.render(); } }
    next() { if (this.pdf && this.pageNumber < this.pdf.numPages) { this.pageNumber++; this.render(); } }

    async render() {
        if (!this.active || !this.pdf) return;
        const generation = this.generation = (this.generation ?? 0) + 1;
        try {
            this.rendering?.cancel();
            const page = await this.pdf.getPage(this.pageNumber);
            if (!this.active || generation !== this.generation) return;
            const canvas = this.canvasTarget;
            const base = page.getViewport({ scale: 1 });
            const width = Math.max(1, this.element.clientWidth - 4);
            const viewport = page.getViewport({ scale: width / base.width * Math.min(devicePixelRatio || 1, 2) });
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            this.rendering = page.render({ canvasContext: canvas.getContext('2d'), viewport });
            await this.rendering.promise;
            if (!this.active || generation !== this.generation) return;
            this.statusTarget.textContent = `Página ${this.pageNumber} de ${this.pdf.numPages}`;
            canvas.setAttribute('aria-label', `Vista del documento, página ${this.pageNumber}`);
            this.previousTarget.disabled = this.pageNumber === 1;
            this.nextTarget.disabled = this.pageNumber === this.pdf.numPages;
            const text = await page.getTextContent();
            if (this.active && generation === this.generation) this.textTarget.textContent = text.items.map(item => item.str ?? '').join(' ') || 'Esta página es una imagen y no contiene texto extraíble.';
        } catch (error) {
            if (this.active && generation === this.generation && error.name !== 'RenderingCancelledException') this.statusTarget.textContent = 'No se puede mostrar esta página. Abre el archivo original.';
        }
    }

    disconnect() {
        this.active = false;
        this.generation = (this.generation ?? 0) + 1;
        clearTimeout(this.resizeTimer);
        this.observer?.disconnect();
        this.rendering?.cancel();
        this.loading?.destroy();
    }
}
