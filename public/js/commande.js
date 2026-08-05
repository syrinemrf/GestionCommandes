$(document).ready(function () {
    const tableElement = $('#commandes-table');
    let commandesTable = null;

    if (tableElement.length) {
        const isAdmin = Number(tableElement.data('is-admin')) === 1;
        const columns = [
            { data: 'numero' },
            { data: 'date' },
            { data: 'client' },
            {
                data: 'totalHt',
                render: function (value, type) {
                    return type === 'display'
                        ? Number(value).toFixed(3) + ' TND'
                        : value;
                }
            },
            {
                data: 'totalTtc',
                render: function (value, type) {
                    return type === 'display'
                        ? Number(value).toFixed(3) + ' TND'
                        : value;
                }
            },
            { data: 'statut', orderable: false },
            { data: 'lastUpdated' }
        ];

        if (isAdmin) {
            columns.push({ data: 'fournisseur' });
            columns.push({ data: 'createdBy' });
        }

        columns.push({
            data: 'actions',
            orderable: false,
            searchable: false
        });

        commandesTable = tableElement.DataTable({
            processing: true,
            serverSide: true,
            pageLength: 8,
            lengthChange: false,
            ajax: {
                url: tableElement.data('url'),
                type: 'GET',
                data: function (data) {
                    data.fournisseur = $('#commande-supplier-filter').val();
                    data.statut = $('#commande-status-filter').val();
                    data.dateFrom = $('#commande-date-from').val();
                    data.dateTo = $('#commande-date-to').val();
                },
                error: function (xhr) {
                    showToast(
                        xhr.responseJSON?.message
                        || 'Impossible de charger les commandes.',
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

        const filterToolbar = $('#commande-table-filters');
        const firstLayoutRow = tableElement
            .closest('.dt-container')
            .find('.dt-layout-row')
            .first();
        let toolbarTarget = firstLayoutRow.find('.dt-layout-start');

        if (!toolbarTarget.length) {
            toolbarTarget = $('<div class="dt-layout-cell dt-start">')
                .prependTo(firstLayoutRow);
        }

        filterToolbar.prop('hidden', false).appendTo(toolbarTarget);

        $('#commande-supplier-filter, #commande-status-filter, #commande-date-from, #commande-date-to')
            .on('change', function () {
                commandesTable.ajax.reload();
            });

        $('#commande-clear-filters').on('click', function () {
            $('#commande-supplier-filter').val('');
            $('#commande-status-filter').val('');
            $('#commande-date-from').val('');
            $('#commande-date-to').val('');
            commandesTable.ajax.reload();
        });
    }

    $(document).on(
        'submit',
        '.delete-commande-form',
        async function (event) {
            event.preventDefault();

            if (!await confirmAction(
                'Voulez-vous vraiment supprimer cette commande ?'
            )) {
                return;
            }

            $.ajax({
                url: this.action,
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    commandesTable?.ajax.reload(null, false);
                    showToast(response.message);
                },
                error: function (xhr) {
                    showToast(
                        xhr.responseJSON?.message
                        || 'Impossible de supprimer la commande.',
                        'error'
                    );
                }
            });
        }
    );

    function updateStatusSelectStyle(select, status) {
        const statusClasses = (select.attr('class') || '')
            .split(/\s+/)
            .filter(className => className.startsWith(
                'commande-status-select-'
            ));

        select
            .removeClass(statusClasses.join(' '))
            .addClass('commande-status-select-' + status.toLowerCase());
    }

    $(document).on(
        'change',
        '.commande-status-select',
        async function () {
            const select = $(this);
            const commandeId = Number(select.data('id'));
            const ancienStatut = String(select.data('current-status'));
            const nouveauStatut = String(select.val());

            if (!commandeId || nouveauStatut === ancienStatut) {
                return;
            }

            if (
                nouveauStatut === 'ANNULEE'
                && !await confirmAction(
                    'Voulez-vous vraiment annuler cette commande ? '
                    + 'Les quantités réservées seront restituées au stock.'
                )
            ) {
                select.val(ancienStatut);
                return;
            }

            select.prop('disabled', true);

            $.ajax({
                url: select.data('url'),
                type: 'POST',
                dataType: 'json',
                data: {
                    statut: nouveauStatut,
                    _token: select.data('token')
                },
                success: function (response) {
                    select.data('current-status', nouveauStatut);
                    updateStatusSelectStyle(select, nouveauStatut);
                    showToast(response.message);
                    commandesTable?.ajax.reload(null, false);
                    refreshOpenDetailsModal();
                },
                error: function (xhr) {
                    select.val(ancienStatut);
                    showToast(
                        xhr.responseJSON?.message
                        || 'Impossible de modifier le statut.',
                        'error'
                    );
                },
                complete: function () {
                    select.prop('disabled', false);
                }
            });
        }
    );

    const detailsModal = $('#commande-details-modal');
    const detailsContent = $('#commande-details-content');

    function refreshOpenDetailsModal() {
        if (detailsModal.prop('hidden')) {
            return;
        }

        const url = detailsContent.data('url');

        if (!url) {
            return;
        }

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'html',
            success: function (html) {
                detailsContent.html(html);
            },
            error: function (xhr) {
                showToast(
                    xhr.responseJSON?.message
                    || 'Impossible d’actualiser les détails de la commande.',
                    'error'
                );
            }
        });
    }

    function closeDetailsModal() {
        detailsModal.prop('hidden', true);
        detailsContent.empty();
        $('body').removeClass('modal-open');
    }

    $(document).on('click', '.commande-details-button', function () {
        detailsContent.data('url', $(this).data('url'));
        detailsContent.html(
            '<div class="commande-details-loading">'
            + '<span class="spinner"></span>'
            + '<span>Chargement des détails...</span>'
            + '</div>'
        );
        detailsModal.prop('hidden', false);
        $('body').addClass('modal-open');
        $('#close-commande-details').trigger('focus');

        $.ajax({
            url: $(this).data('url'),
            type: 'GET',
            dataType: 'html',
            success: function (html) {
                detailsContent.html(html);
            },
            error: function (xhr) {
                closeDetailsModal();
                showToast(
                    xhr.responseJSON?.message
                    || 'Impossible de charger les détails de la commande.',
                    'error'
                );
            }
        });
    });

    $('#close-commande-details').on('click', closeDetailsModal);

    detailsModal.on('click', function (event) {
        if (event.target === this) {
            closeDetailsModal();
        }
    });

    $(document).on('keydown', function (event) {
        if (
            event.key === 'Escape'
            && detailsModal.length
            && !detailsModal.prop('hidden')
        ) {
            closeDetailsModal();
        }
    });

    const form = $('#commande-form');

    if (!form.length) {
        return;
    }

    const linesContainer = $('#commande-lines');
    const lineTemplate = document.querySelector('#commande-line-template');
    const isAdmin = Number(form.data('is-admin')) === 1;
    const placeholderImage = String(form.data('placeholder'));
    const imageBase = String(form.data('image-base')).replace(/\/$/, '');
    const initialLines = form.data('initial-lines') || [];
    const currentSupplier = form.data('current-fournisseur') || '';
    const commandId = form.data('command-id') || '';
    const currentTva = Number(form.data('default-tva')) || 0;
    let products = [];

    function setButtonLoading(button, loading) {
        button.prop('disabled', loading);
        button.find('.button-label').prop('hidden', loading);
        button.find('.button-loading').prop('hidden', !loading);
    }

    function productImage(product) {
        return product?.image
            ? imageBase + '/' + String(product.image).replace(/^\//, '')
            : placeholderImage;
    }

    function populateProductSelect(select, selectedProductId = null) {
        select.empty().append(
            $('<option>', { value: '', text: 'Sélectionner' })
        );

        products.forEach(function (product) {
            select.append(
                $('<option>', {
                    value: product.id,
                    text: product.libelle,
                    selected: Number(product.id) === Number(selectedProductId)
                })
            );
        });
    }

    function updateProductImage(line) {
        const productId = Number(line.find('.commande-product').val());
        const product = products.find(
            item => Number(item.id) === productId
        );

        line.find('.commande-product-image')
            .attr('src', productImage(product))
            .attr('alt', product ? product.libelle : '');
    }

    function populateVariationSelect(line, selectedVariationId = null) {
        const productId = Number(line.find('.commande-product').val());
        const product = products.find(
            item => Number(item.id) === productId
        );
        const select = line.find('.commande-variation');
        const variationField = line.find('.commande-variation-field');
        const showVariationField = Boolean(product && !product.isStandard);

        select.empty().append(
            $('<option>', { value: '', text: 'Sélectionner' })
        );

        const variations = product?.variations || [];
        variationField.prop('hidden', !showVariationField);
        line.toggleClass(
            'commande-line-without-variation',
            !showVariationField
        );
        select.prop('required', Boolean(product));

        const variationId = selectedVariationId
            || (
                product?.isStandard && variations.length === 1
                    ? variations[0].id
                    : null
            );

        variations.forEach(function (variation) {
            select.append(
                $('<option>', {
                    value: variation.id,
                    text: variation.libelle,
                    selected: Number(variation.id)
                        === Number(variationId)
                }).attr({
                    'data-price': variation.prix,
                    'data-stock': variation.stockDisponible
                })
            );
        });

        updateProductImage(line);
        updateLine(line);
    }

    function addLine(values = {}) {
        const fragment = lineTemplate.content.cloneNode(true);
        const line = $(fragment).find('.commande-line');

        linesContainer.append(line);
        populateProductSelect(
            line.find('.commande-product'),
            values.produitId
        );
        line.find('.commande-quantity').val(values.quantite || 1);
        populateVariationSelect(line, values.variationId);

        return line;
    }

    function updateLine(line) {
        const option = line.find('.commande-variation option:selected');
        const price = Number(option.data('price')) || 0;
        const stock = option.val() === ''
            ? null
            : Number(option.data('stock'));
        const quantity = Math.max(
            0,
            Number(line.find('.commande-quantity').val()) || 0
        );

        line.find('.commande-stock').text(
            stock === null ? '—' : String(stock)
        );
        line.find('.commande-price').text(price.toFixed(3) + ' TND');
        line.find('.commande-line-total').text(
            (price * quantity).toFixed(3) + ' TND'
        );
        line.find('.commande-quantity').attr(
            'max',
            stock === null ? null : stock
        );
        updateTotals();
    }

    function updateTotals() {
        let totalHt = 0;

        linesContainer.find('.commande-line').each(function () {
            const line = $(this);
            const price = Number(
                line.find('.commande-variation option:selected').data('price')
            ) || 0;
            const quantity = Math.max(
                0,
                Number(line.find('.commande-quantity').val()) || 0
            );

            totalHt += price * quantity;
        });

        const totalTva = totalHt * currentTva / 100;

        $('#commande-total-ht').text(totalHt.toFixed(3) + ' TND');
        $('#commande-total-tva').text(totalTva.toFixed(3) + ' TND');
        $('#commande-total-ttc').text(
            (totalHt + totalTva).toFixed(3) + ' TND'
        );
        $('#commande-tva-label').text(currentTva.toFixed(3));
    }

    function loadProducts(fournisseurId) {
        const requestData = {};

        if (isAdmin && fournisseurId) {
            requestData.fournisseur = fournisseurId;
        }

        if (
            commandId
            && (
                !isAdmin
                || Number(fournisseurId) === Number(currentSupplier)
            )
        ) {
            requestData.commande = commandId;
        }

        return $.ajax({
            url: form.data('products-url'),
            type: 'GET',
            data: requestData,
            dataType: 'json'
        }).then(function (response) {
            products = response.products || [];
        }).catch(function (xhr) {
            products = [];
            showToast(
                xhr.responseJSON?.message
                || 'Impossible de charger les produits.',
                'error'
            );
        });
    }

    function displayInitialLines() {
        linesContainer.empty();

        if (initialLines.length) {
            initialLines.forEach(addLine);
        } else {
            addLine();
        }

        updateTotals();
    }

    $('#commande-fournisseur').on('change', function () {
        const fournisseurId = $(this).val();

        products = [];
        linesContainer.empty();

        if (!fournisseurId) {
            addLine();
            return;
        }

        loadProducts(fournisseurId).then(function () {
            addLine();
        });
    });

    $('#add-commande-line').on('click', function () {
        if (isAdmin && !$('#commande-fournisseur').val()) {
            showToast('Sélectionnez d’abord un fournisseur.', 'error');
            return;
        }

        addLine().find('.commande-product').trigger('focus');
    });

    linesContainer.on('change', '.commande-product', function () {
        populateVariationSelect($(this).closest('.commande-line'));
    });

    linesContainer.on('change', '.commande-variation', function () {
        updateLine($(this).closest('.commande-line'));
    });

    linesContainer.on('input', '.commande-quantity', function () {
        updateLine($(this).closest('.commande-line'));
    });

    linesContainer.on('click', '.remove-commande-line', function () {
        const line = $(this).closest('.commande-line');

        if (linesContainer.find('.commande-line').length === 1) {
            line.find('select').val('');
            line.find('.commande-quantity').val(1);
            populateVariationSelect(line);
            return;
        }

        line.remove();
        updateTotals();
    });

    form.on('submit', function (event) {
        event.preventDefault();
        const submitButton = form.find('button[type="submit"]');

        setButtonLoading(submitButton, true);

        $.ajax({
            url: this.action,
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function (response) {
                showToast(response.message);
                setTimeout(function () {
                    window.location.href = form.data('redirect-url');
                }, 600);
            },
            error: function (xhr) {
                showToast(
                    xhr.responseJSON?.message
                    || 'Impossible d’enregistrer la commande.',
                    'error'
                );
            },
            complete: function () {
                setButtonLoading(submitButton, false);
            }
        });
    });

    const supplierId = isAdmin
        ? ($('#commande-fournisseur').val() || currentSupplier)
        : currentSupplier;

    if (isAdmin && !supplierId) {
        displayInitialLines();
    } else {
        loadProducts(supplierId).then(displayInitialLines);
    }
});
