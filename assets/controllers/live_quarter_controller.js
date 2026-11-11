import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content', 'status'];
    static values = { auth: String, url: String };

    connect() {
        this.active = true;
        this.generation = (this.generation ?? 0) + 1;
        this.authorizing = false;
        this.refreshing = false;
        this.dirty = false;
        this.pending = false;
        this.onEdit = (event) => {
            if (this.contentTarget.contains(event.target)) this.dirty = true;
        };
        this.onVisibility = () => { if (!document.hidden) this.schedule(); };
        this.element.addEventListener('input', this.onEdit);
        this.element.addEventListener('change', this.onEdit);
        document.addEventListener('visibilitychange', this.onVisibility);
        this.beforeCache = () => this.disconnect();
        document.addEventListener('turbo:before-cache', this.beforeCache);
        this.authorize();
        this.renewal = window.setInterval(() => this.authorize(), 45000);
    }

    disconnect() {
        this.active = false;
        this.source?.close();
        this.fetchAbort?.abort();
        this.authAbort?.abort();
        window.clearInterval(this.renewal);
        window.clearTimeout(this.refreshTimer);
        this.element.removeEventListener('input', this.onEdit);
        this.element.removeEventListener('change', this.onEdit);
        document.removeEventListener('visibilitychange', this.onVisibility);
        document.removeEventListener('turbo:before-cache', this.beforeCache);
    }

    async authorize() {
        if (!this.active || this.authorizing) return;
        this.authorizing = true;
        const generation = this.generation;
        const abort = this.authAbort = new AbortController();
        const timeout = window.setTimeout(() => abort.abort(), 8000);
        try {
            const response = await fetch(this.authValue, { credentials: 'same-origin', cache: 'no-store', signal: abort.signal });
            if (!this.active || generation !== this.generation) return;
            if (response.redirected || [401, 403, 404].includes(response.status)) { this.revoke(); return; }
            if (!response.ok) throw new Error('Authorization unavailable');
            const result = await response.json();
            if (!this.active || generation !== this.generation) return;
            this.source?.close();
            this.source = new EventSource(result.url, { withCredentials: true });
            this.source.onmessage = () => this.schedule();
            this.source.onopen = () => this.schedule();
            this.source.onerror = () => { if (this.active && generation === this.generation) this.statusTarget.textContent = 'La conexión se ha interrumpido. Puedes actualizar las cifras manualmente; seguimos intentando conectar.'; };
            // Also recover updates missed while the hub or browser was offline.
            this.schedule();
        } catch (error) {
            if (this.active && generation === this.generation) this.statusTarget.textContent = 'No se pueden conectar las actualizaciones. Se conservan las últimas cifras; puedes actualizarlas manualmente.';
        } finally {
            window.clearTimeout(timeout);
            if (generation === this.generation) this.authorizing = false;
        }
    }

    schedule() {
        if (!this.active) return;
        this.pending = true;
        window.clearTimeout(this.refreshTimer);
        this.refreshTimer = window.setTimeout(() => this.refresh(), 200);
    }

    manual() {
        // Never erase an editor, including any future form added to the fragment.
        if (this.dirty) {
            this.statusTarget.textContent = 'Hay cambios nuevos. Termina o guarda lo que estás escribiendo antes de actualizar.';
            return;
        }
        this.schedule();
    }

    async refresh() {
        if (!this.active || !this.pending || document.hidden) return;
        if (this.dirty) { this.statusTarget.textContent = 'Hay cambios nuevos. Tus datos escritos se conservan; termina o guarda antes de actualizar.'; return; }
        if (this.refreshing) return;
        this.refreshing = true;
        this.pending = false;
        const generation = this.generation;
        const abort = this.fetchAbort = new AbortController();
        const timeout = window.setTimeout(() => abort.abort(), 8000);
        try {
            const response = await fetch(this.urlValue, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Quarter-Fragment': '1' }, signal: abort.signal });
            if (!this.active || generation !== this.generation) return;
            if (response.redirected || [401, 403, 404].includes(response.status)) { this.revoke(); return; }
            if (!response.ok) throw new Error('Quarter unavailable');
            const html = await response.text();
            // An input can change while the response is in flight.
            if (!this.active || generation !== this.generation || this.dirty) { this.pending = true; return; }
            const focusId = this.contentTarget.contains(document.activeElement) ? document.activeElement.id : null;
            this.contentTarget.innerHTML = html;
            if (focusId) this.contentTarget.querySelector(`#${CSS.escape(focusId)}`)?.focus();
            this.statusTarget.textContent = 'Cifras actualizadas. Los cambios del trimestre aparecerán aquí.';
        } catch (error) {
            if (this.active && generation === this.generation) this.statusTarget.textContent = 'No hemos podido actualizar. Se conservan las últimas cifras. Vuelve a intentarlo.';
        } finally {
            window.clearTimeout(timeout);
            if (generation === this.generation) this.refreshing = false;
            if (generation === this.generation && this.pending && this.active && !this.dirty) this.schedule();
        }
    }

    revoke() {
        this.contentTarget.replaceChildren();
        this.statusTarget.textContent = 'La sesión o el permiso han cambiado. Vuelve a entrar para consultar este trimestre.';
        this.disconnect();
    }
}
