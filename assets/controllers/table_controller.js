import { Controller } from '@hotwired/stimulus';
import { exportCell } from '../ui/table-export.mjs';

export default class extends Controller {
    static values = { filterColumn: Number };

    connect() {
        if (!window.DataTable || this.table) return;
        this.tableElement = this.element.querySelector('table');
        if (this.hasFilterColumnValue) {
            this.filterValues = [...new Set([...this.tableElement.tBodies[0].rows].map(row => row.cells[this.filterColumnValue]?.textContent.trim()).filter(Boolean))].sort();
        }
        const exportOptions = {
            columns: ':not(.no-export)',
            modifier: { search: 'applied', page: 'all' },
            escapeExcelFormula: true,
            format: { body: (data, row, column, node) => exportCell(node, data) },
        };
        this.table = new window.DataTable(this.tableElement, {
            responsive: true,
            order: [],
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50],
            stateSave: false,
            layout: { topStart: { buttons: [
                { extend: 'excelHtml5', text: 'Excel', title: 'Aselto', exportOptions },
                { extend: 'csvHtml5', text: 'CSV', bom: true, fieldSeparator: ';', exportOptions: { ...exportOptions, format: { body: (data, row, column, node) => exportCell(node, data, true) } } },
            ] }, topEnd: 'search', bottomStart: ['pageLength', 'info'], bottomEnd: 'paging' },
            language: {
                search: 'Buscar en el listado', searchPlaceholder: 'Escribe para buscar…',
                emptyTable: 'No hay datos en este listado.', zeroRecords: 'No hay resultados con estos filtros.',
                info: '_START_–_END_ de _TOTAL_ registros', infoEmpty: '0 registros',
                infoFiltered: '(de _MAX_ en total)', lengthMenu: 'Mostrar _MENU_ registros',
                paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' },
                aria: { orderable: 'Ordenar por esta columna', orderableReverse: 'Invertir el orden', paginate: { first: 'Primera página', previous: 'Página anterior', next: 'Página siguiente', last: 'Última página', number: 'Página ' } },
            },
            columnDefs: [{ targets: 'no-export', orderable: false, searchable: false }],
        });
        if (this.hasFilterColumnValue) this.addFilter();
        this.beforeCache = () => this.teardown();
        document.addEventListener('turbo:before-cache', this.beforeCache);
    }

    addFilter() {
        const index = this.filterColumnValue;
        const values = this.filterValues;
        this.filter = document.createElement('label');
        this.filter.className = 'table-filter';
        this.filter.append(document.createTextNode(`Filtrar por ${this.tableElement.tHead.rows[0].cells[index].textContent.toLowerCase()} `));
        const select = document.createElement('select');
        select.add(new Option('Todos', ''));
        values.forEach(value => select.add(new Option(value, value)));
        select.addEventListener('change', () => this.table?.column(index).search(select.value, { exact: true }).draw());
        this.filter.append(select);
        this.table.table().container().before(this.filter);
    }

    teardown() {
        this.filter?.remove();
        this.filter = null;
        this.table?.destroy();
        this.table = null;
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.beforeCache);
        this.teardown();
    }
}
