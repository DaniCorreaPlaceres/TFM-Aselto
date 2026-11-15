import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { url: String, export: String, filters: Array };
    static targets = ['status'];

    connect() {
        if (!window.DataTable || this.table) return;
        this.abort = new AbortController();
        this.filter = '';
        this.table = new window.DataTable(this.element.querySelector('table'), {
            serverSide: true, processing: true, responsive: true,
            pageLength: 25, lengthMenu: [10, 25, 50, 100], searchDelay: 400, order: [],
            ajax: async (data, callback) => {
                const url = this.query(this.urlValue, data);
                this.lastQuery = data;
                try {
                    const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: this.abort.signal });
                    if (!response.ok || !response.headers.get('Content-Type')?.includes('application/json')) throw new Error();
                    const page = await response.json();
                    this.statusTarget.textContent = '';
                    callback(page);
                } catch (error) {
                    if (error.name === 'AbortError') return;
                    this.statusTarget.textContent = 'No se ha podido cargar el listado. Espera unos minutos y recarga la página.';
                    callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                }
            },
            layout: { topStart: { buttons: [
                { text: 'Excel', action: () => this.download('xlsx') },
                { text: 'CSV', action: () => this.download('csv') },
            ] }, topEnd: 'search', bottomStart: ['pageLength', 'info'], bottomEnd: 'paging' },
            columnDefs: [{ targets: 'no-export', orderable: false, searchable: false }],
            language: { search: 'Buscar en el listado', emptyTable: 'No hay registros en esta vista.', zeroRecords: 'No hay resultados con estos filtros.', info: '_START_–_END_ de _TOTAL_ registros', infoEmpty: '0 registros', infoFiltered: '(de _MAX_ en total)', lengthMenu: 'Mostrar _MENU_ registros', processing: 'Cargando…', loadingRecords: 'Cargando registros…', paginate: { first: 'Primera', previous: 'Anterior', next: 'Siguiente', last: 'Última' }, aria: { orderable: 'Ordenar por esta columna', orderableReverse: 'Invertir el orden', paginate: { first: 'Primera página', previous: 'Página anterior', next: 'Página siguiente', last: 'Última página', number: 'Página ' } } },
        });
        this.element.querySelector('input[type="search"]')?.setAttribute('maxlength', '120');
        if (this.filtersValue.length) {
            const label = document.createElement('label');
            label.className = 'table-filter';
            label.textContent = 'Filtrar por tipo o estado ';
            const select = document.createElement('select');
            select.add(new Option('Todos', ''));
            this.filtersValue.forEach(value => select.add(new Option(value, value)));
            select.addEventListener('change', () => { this.filter = select.value; this.table.ajax.reload(); });
            label.append(select);
            this.element.prepend(label);
            this.filterElement = label;
        }
        this.beforeCache = () => this.teardown();
        document.addEventListener('turbo:before-cache', this.beforeCache);
    }

    query(base, data = {}) {
        const url = new URL(base, window.location.origin);
        url.searchParams.set('table', '1');
        url.searchParams.set('draw', data.draw ?? 0);
        url.searchParams.set('start', data.start ?? 0);
        url.searchParams.set('length', data.length ?? 25);
        url.searchParams.set('q', data.search?.value ?? '');
        url.searchParams.set('column', data.order?.[0]?.column ?? 0);
        url.searchParams.set('order', data.order?.[0]?.dir ?? 'asc');
        url.searchParams.set('filter', this.filter);
        return url;
    }

    download(format) {
        const url = this.query(this.exportValue, this.lastQuery);
        url.searchParams.set('format', format);
        // A native navigation streams the file; it does not buffer the export in JS.
        window.location.assign(url);
    }

    teardown() {
        this.abort?.abort();
        this.filterElement?.remove();
        this.table?.destroy();
        this.table = null;
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.beforeCache);
        this.teardown();
    }
}
