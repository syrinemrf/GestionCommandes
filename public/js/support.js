$(document).ready(function () {
    const supportTableElement = $('#support-table');

    if (supportTableElement.length) {
        const isAdmin = Number(supportTableElement.data('is-admin')) === 1;
        const columns = [
            { data: 'numero' },
            { data: 'objet' },
            { data: 'categorie' },
            { data: 'priorite', orderable: false },
            { data: 'statut', orderable: false },
            { data: 'updatedAt' }
        ];

        if (isAdmin) {
            columns.push({ data: 'fournisseur' });
            columns.push({ data: 'admin' });
        }

        columns.push({
            data: 'actions',
            orderable: false,
            searchable: false
        });

        const supportTable = supportTableElement.DataTable({
            processing: true,
            serverSide: true,
            pageLength: 10,
            lengthChange: false,
            ajax: {
                url: supportTableElement.data('url'),
                type: 'GET',
                data: function (data) {
                    data.statut = $('#support-status-filter').val();
                    data.priorite = $('#support-priority-filter').val();
                    data.categorie = $('#support-category-filter').val();
                },
                error: function (xhr) {
                    showToast(
                        xhr.responseJSON?.message
                        || 'Impossible de charger les demandes.',
                        'error'
                    );
                }
            },
            columns: columns,
            createdRow: function (row, data) {
                $(row).attr('data-id', data.id);
            },
            order: []
        });

        const filterToolbar = $('#support-table-filters');
        const firstLayoutRow = supportTableElement
            .closest('.dt-container')
            .find('.dt-layout-row')
            .first();
        let toolbarTarget = firstLayoutRow.find('.dt-layout-start');

        if (!toolbarTarget.length) {
            toolbarTarget = $('<div class="dt-layout-cell dt-start">')
                .prependTo(firstLayoutRow);
        }

        filterToolbar.prop('hidden', false).appendTo(toolbarTarget);

        $('#support-status-filter, #support-priority-filter, #support-category-filter')
            .on('change', function () {
                supportTable.ajax.reload();
            });

        $('#support-clear-filters').on('click', function () {
            $('#support-status-filter').val('');
            $('#support-priority-filter').val('');
            $('#support-category-filter').val('');
            supportTable.ajax.reload();
        });
    }

    $('.support-ajax-form').on('submit', function (event) {
        event.preventDefault();

        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        submitButton.prop('disabled', true);

        $.ajax({
            url: this.action,
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function (response) {
                showToast(response.message);

                if (response.redirectUrl) {
                    setTimeout(function () {
                        window.location.href = response.redirectUrl;
                    }, 500);
                    return;
                }

                if (form.data('reload')) {
                    setTimeout(function () {
                        window.location.reload();
                    }, 500);
                }
            },
            error: function (xhr) {
                showToast(
                    xhr.responseJSON?.message
                    || 'Une erreur est survenue.',
                    'error'
                );
            },
            complete: function () {
                submitButton.prop('disabled', false);
            }
        });
    });
});
