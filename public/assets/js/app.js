'use strict';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });

    document.querySelectorAll('.section-actions').forEach((bar) => {
        const actions = [...bar.children].flatMap((child) =>
            child.classList.contains('btn-list') ? [...child.children] : [child]
        );
        let previousCapacity = 0;
        const arrange = () => {
            const capacity = Math.max(1, Math.min(6, Math.floor((bar.clientWidth + 8) / 158)));
            if (capacity === previousCapacity) return;
            previousCapacity = capacity;
            const rows = [];
            let offset = 0;
            let remainingRows = Math.ceil(actions.length / capacity);
            while (remainingRows > 0) {
                const size = Math.ceil((actions.length - offset) / remainingRows);
                const row = document.createElement('div');
                row.className = 'action-row';
                row.append(...actions.slice(offset, offset + size));
                rows.push(row);
                offset += size;
                remainingRows -= 1;
            }
            bar.replaceChildren(...rows);
        };
        arrange();
        if (typeof window.ResizeObserver === 'function') {
            new window.ResizeObserver(arrange).observe(bar);
        } else {
            window.addEventListener('resize', arrange);
        }
    });

    document.querySelectorAll('select[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => select.form.requestSubmit());
    });

    document.querySelectorAll('select[data-context-select]').forEach((select) => {
        select.addEventListener('change', () => {
            select.form.requestSubmit(select.form.querySelector('button[name="load"]'));
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') ?? 'Confermare l’operazione?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    if (typeof window.DataTable !== 'function') {
        return;
    }

    document.querySelectorAll('table[data-datatable]').forEach((table) => {
        if (table.querySelector('tbody td[colspan]')) {
            return;
        }

        const compact = table.dataset.datatableCompact === 'true';
        const serverPage = table.dataset.datatableMode === 'server-page';
        const pageLength = Number.parseInt(table.dataset.datatablePageLength ?? '25', 10);

        const options = {
            autoWidth: false,
            order: [],
            ordering: true,
            paging: !compact && !serverPage,
            pageLength: Number.isFinite(pageLength) ? pageLength : 25,
            lengthMenu: [10, 25, 50, 100],
            responsive: true,
            columnDefs: [{ targets: 0, responsivePriority: 1 }],
            searching: true,
            stateSave: !compact && !serverPage,
            language: {
                emptyTable: 'Nessun dato disponibile.',
                info: 'Elementi _START_–_END_ di _TOTAL_',
                infoEmpty: 'Nessun elemento',
                infoFiltered: '(filtrati da _MAX_)',
                lengthMenu: 'Mostra _MENU_',
                loadingRecords: 'Caricamento…',
                paginate: {
                    first: 'Prima',
                    last: 'Ultima',
                    next: 'Successiva',
                    previous: 'Precedente',
                },
                search: 'Filtra:',
                searchPlaceholder: serverPage ? 'Cerca nella pagina corrente' : 'Cerca nella tabella',
                zeroRecords: 'Nessun risultato corrispondente.',
            },
            layout: compact ? {
                topStart: null,
                topEnd: 'search',
                bottomStart: null,
                bottomEnd: null,
            } : {
                topStart: serverPage ? null : 'pageLength',
                topEnd: 'search',
                bottomStart: serverPage ? null : 'info',
                bottomEnd: serverPage ? null : 'paging',
            },
        };

        new window.DataTable(table, options);
    });
});
