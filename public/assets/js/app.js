'use strict';

document.addEventListener('DOMContentLoaded', () => {
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
