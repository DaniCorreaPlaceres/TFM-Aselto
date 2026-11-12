import { Controller } from '@hotwired/stimulus';
import { acceptedTypes, uploadError, uploadSize } from '../ui/document-upload.mjs';

export default class extends Controller {
    static targets = ['field', 'zone', 'selection', 'name', 'size', 'preview', 'error', 'status', 'submit', 'controls'];

    connect() {
        this.element.classList.add('upload-ready');
        this.fieldTarget.tabIndex = -1;
        this.dragDepth = 0;
        this.beforeCache = () => this.reset();
        document.addEventListener('turbo:before-cache', this.beforeCache);
        this.restore();
    }

    choose() { this.fieldTarget.accept = acceptedTypes; this.fieldTarget.removeAttribute('capture'); this.fieldTarget.click(); }
    photo() { this.fieldTarget.accept = 'image/jpeg,image/png,image/webp'; this.fieldTarget.setAttribute('capture', 'environment'); this.fieldTarget.click(); }

    selected() {
        this.releasePreview();
        this.clearError();
        const error = uploadError(this.fieldTarget.files);
        if (error) { this.reset(); this.showError(error); return; }
        const file = this.fieldTarget.files[0];
        this.selectionTarget.hidden = false;
        this.nameTarget.textContent = file.name;
        this.sizeTarget.textContent = uploadSize(file.size);
        this.statusTarget.textContent = 'Documento preparado. Revísalo y pulsa Subir documento.';
        if (file.type.startsWith('image/') || (!file.type && /\.(jpe?g|png|webp)$/i.test(file.name))) {
            this.previewUrl = URL.createObjectURL(file);
            this.previewTarget.src = this.previewUrl;
            this.previewTarget.hidden = false;
        }
    }

    dragEnter(event) { event.preventDefault(); this.dragDepth++; this.zoneTarget.classList.add('is-dragging'); }
    dragOver(event) { event.preventDefault(); if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy'; }
    dragLeave(event) { event.preventDefault(); this.dragDepth = Math.max(0, this.dragDepth - 1); if (!this.dragDepth) this.zoneTarget.classList.remove('is-dragging'); }

    drop(event) {
        event.preventDefault();
        this.dragDepth = 0;
        this.zoneTarget.classList.remove('is-dragging');
        if (this.sending) return;
        const files = event.dataTransfer?.files;
        const error = uploadError(files);
        if (error) { this.reset(); this.showError(error); return; }
        try {
            const transfer = new DataTransfer();
            transfer.items.add(files[0]);
            this.fieldTarget.files = transfer.files;
            this.selected();
        } catch {
            this.reset();
            this.showError('No se ha podido preparar el archivo arrastrado. Usa Elegir archivo.');
        }
    }

    remove() { this.reset(); this.controlsTargets[0]?.focus(); }

    reset() {
        this.dragDepth = 0;
        this.zoneTarget.classList.remove('is-dragging');
        this.releasePreview();
        this.fieldTarget.value = '';
        this.fieldTarget.accept = acceptedTypes;
        this.fieldTarget.removeAttribute('capture');
        this.selectionTarget.hidden = true;
        this.nameTarget.textContent = '';
        this.sizeTarget.textContent = '';
        this.clearError();
        this.restore();
        this.statusTarget.textContent = 'Ningún documento seleccionado.';
    }

    submit(event) {
        if (this.sending) { event.preventDefault(); return; }
        this.clearError();
        const error = uploadError(this.fieldTarget.files);
        if (error) { event.preventDefault(); this.showError(error); this.controlsTargets[0]?.focus(); return; }
        this.sending = true;
        this.element.setAttribute('aria-busy', 'true');
        this.submitTarget.disabled = true;
        this.controlsTargets.forEach(button => { button.disabled = true; });
        this.submitTarget.textContent = 'Enviando documento…';
        this.statusTarget.textContent = 'Estamos enviando el documento. Después podrás revisar sus datos.';
    }

    finished(event) {
        if (!event.detail.success) {
            this.restore();
            this.statusTarget.textContent = 'No se ha completado el envío. Puedes volver a intentarlo.';
        }
    }
    restore() {
        this.sending = false;
        this.element.removeAttribute('aria-busy');
        this.submitTarget.disabled = false;
        this.controlsTargets.forEach(button => { button.disabled = false; });
        this.submitTarget.textContent = 'Subir documento';
    }
    showError(message) { this.errorTarget.textContent = message; this.errorTarget.hidden = false; this.fieldTarget.setAttribute('aria-invalid', 'true'); }
    clearError() { this.errorTarget.hidden = true; this.errorTarget.textContent = ''; this.fieldTarget.removeAttribute('aria-invalid'); }
    releasePreview() {
        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
        this.previewTarget.hidden = true;
        this.previewTarget.removeAttribute('src');
    }
    disconnect() { document.removeEventListener('turbo:before-cache', this.beforeCache); this.releasePreview(); }
}
