$(document).ready(function () {

    const imageInput = $('#image');
    const imagePreview = $('#image-preview');
    const imagePreviewContainer = $('#image-preview-container');
    const imageUploadZone = $('.image-upload-zone');
    const removeImageButton = $('#remove-image');
    const removeImageValue = $('#remove-image-value');
    let previewObjectUrl = null;

    imageInput.on('change', function () {
        const file = this.files[0];

        if (!file) {
            return;
        }

        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
        }

        previewObjectUrl = URL.createObjectURL(file);
        imagePreview.attr('src', previewObjectUrl);
        imagePreviewContainer.prop('hidden', false);
        imageUploadZone.prop('hidden', true);
        removeImageValue.val('0');
    });

    removeImageButton.on('click', function () {
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = null;
        }

        imageInput.val('');
        imagePreview.attr('src', '');
        imagePreviewContainer.prop('hidden', true);
        imageUploadZone.prop('hidden', false);
        removeImageValue.val('1');
    });

    $('#product-form').submit(function (e) {

        e.preventDefault();

        const form = $(this);
        const submitButton = form.find('button[type="submit"]');

        submitButton.prop('disabled', true);

        const formData = new FormData(this);

        $.ajax({
            type: "POST",
            url: this.action,
            data: formData,
            processData: false,
            contentType: false,

            success: function (response) {
                if (response.success) {
                    showToast(response.message || form.data('success-message'));

                    if (response.redirectUrl) {
                        setTimeout(function () {
                            window.location.href = response.redirectUrl;
                        }, 800);
                    }

                    if (form.data('reset-form')) {
                        form[0].reset();
                        imagePreview.attr('src', '');
                        imagePreviewContainer.prop('hidden', true);
                        imageUploadZone.prop('hidden', false);

                        if (previewObjectUrl) {
                            URL.revokeObjectURL(previewObjectUrl);
                            previewObjectUrl = null;
                        }
                    }
                }
            },

            error: function (xhr) {
                showToast(xhr.responseJSON?.message || "Une erreur est survenue.", 'error');
            },

            complete: function () {
                submitButton.prop('disabled', false);
            }
        });

    });

    if ($('#products-table').length) {

        const productsTable = $('#products-table');
        const productsDataUrl = productsTable.data('url');
        const placeholderImage = productsTable.data('placeholder');
        const productImagesBase = String(productsTable.data('image-base')).replace(/\/$/, '');

        const columns = [

            {
                data: 'image',
                orderable: false,
                searchable: false,
                render: function (data, type) {
                    if (type !== 'display') {
                        return data;
                    }

                    const image = document.createElement('img');

                    image.src = data
                        ? productImagesBase + '/' + String(data).replace(/^\//, '')
                        : placeholderImage;
                    image.className = 'product-image';
                    image.alt = data
                        ? 'Image du produit'
                        : 'Aucune image disponible';
                    image.loading = 'lazy';

                    return image;
                }
            },

            {
                data: 'libelle'
            },

            {
                data: 'prix',
                render: function (data) {
                    return parseFloat(data).toFixed(3) + ' TND';
                }
            }

        ];

        if ($('#products-table').data('is-admin')) {

            columns.push({
                data: 'fournisseur'
            });

        }

        columns.push({

            data: 'actions',
            orderable: false,
            searchable: false

        });


        productsTable.DataTable({
            processing: true,
            serverSide: true,
            pageLength: 5,
            lengthChange: false,
            
            ajax: {
                url: productsDataUrl,
                type: 'GET',
                error: function (xhr) {
                    const message = xhr.responseJSON?.message
                        || 'Impossible de charger la liste des produits.';

                    showToast(message, 'error');
                }
            },

            columns: columns
        });

    }

    $(document).on('click', '.delete-product', async function (e) {

        e.preventDefault();

        if (!await confirmAction("Voulez-vous vraiment supprimer ce produit ?")) {
            return;
        }

        const link = $(this);

        $.ajax({
            type: "POST",
            url: link.attr('href'),
            data: {
                _token: link.data('token')
            },

            success: function (response) {
                if (response.success) {
                    $('#products-table').DataTable().ajax.reload(null, false);
                    showToast(response.message);
                }
            },

            error: function (xhr) {
                showToast(xhr.responseJSON?.message || "Une erreur est survenue.", 'error');
            }
        });

    });

    const variationForm = $('#variation-form');
    const attributeList = $('#attribute-list');
    const attributeTemplate = document.querySelector('#attribute-row-template');
    let attributeRowId = 0;

    const variationsTableElement = $('#variations-table');
    const variationsTable = variationsTableElement.length
        ? variationsTableElement.DataTable({
            processing: true,
            serverSide: true,
            pageLength: 5,
            lengthChange: false,
            ajax: {
                url: variationsTableElement.data('url'),
                type: 'GET',
                error: function (xhr) {
                    showToast(
                        xhr.responseJSON?.message || 'Impossible de charger les variations.',
                        'error'
                    );
                }
            },
            columns: [
                { data: 'libelle' },
                { data: 'attributs', orderable: false },
                { data: 'reference' },
                {
                    data: 'prixSupplement',
                    render: function (data, type) {
                        return type === 'display'
                            ? '+' + Number(data).toFixed(3) + ' TND'
                            : data;
                    }
                },
                {
                    data: 'prixFinal',
                    render: function (data, type) {
                        return type === 'display'
                            ? Number(data).toFixed(3) + ' TND'
                            : data;
                    }
                },
                { data: 'stock' },
                { data: 'actions', orderable: false, searchable: false }
            ],
            order: [[0, 'asc']]
        })
        : null;

    function addAttributeRow(name = '', value = '') {
        const fragment = attributeTemplate.content.cloneNode(true);
        const row = fragment.querySelector('.attribute-row');
        const rowId = String(++attributeRowId);

        row.dataset.attributeRowId = rowId;
        row.querySelector('[name="attribut_nom[]"]').value = name;
        row.querySelector('[name="attribut_valeur[]"]').value = value;
        row.querySelector('.remove-attribute-button').dataset.attributeRowId = rowId;
        attributeList[0].appendChild(fragment);
    }

    function resetVariationForm() {
        variationForm[0].reset();
        variationForm.attr('action', variationForm.data('add-url'));
        $('#variation-token').val(variationForm.data('add-token'));
        $('#variation-form-title').text('Nouvelle variation');
        attributeList.empty();
        addAttributeRow();
    }

    function openVariationForm() {
        variationForm.prop('hidden', false);
        $('#variation-libelle').trigger('focus');
        variationForm[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    $('#add-variation-button').on('click', function () {
        resetVariationForm();
        openVariationForm();
    });

    $('#cancel-variation').on('click', function () {
        variationForm.prop('hidden', true);
        resetVariationForm();
    });

    $('#add-attribute-button').on('click', function () {
        addAttributeRow();
        attributeList.find('.attribute-row').last().find('input').first().trigger('focus');
    });

    attributeList.on('click', '.remove-attribute-button', function () {
        const rowId = String($(this).data('attribute-row-id'));
        const row = attributeList.find(`[data-attribute-row-id="${rowId}"]`);

        if (attributeList.find('.attribute-row').length === 1) {
            row.find('input').val('');
            return;
        }

        row.remove();
    });

    $(document).on('click', '.edit-variation-button', function () {
        const button = $(this);
        let attributes = {};

        try {
            attributes = JSON.parse(button.attr('data-attributs'));
        } catch (error) {
            showToast('Les caractéristiques de cette variation sont invalides.', 'error');
            return;
        }

        resetVariationForm();
        variationForm.attr('action', button.attr('data-edit-url'));
        $('#variation-token').val(button.attr('data-edit-token'));
        $('#variation-form-title').text('Modifier la variation');
        $('#variation-libelle').val(button.attr('data-libelle'));
        $('#variation-reference').val(button.attr('data-reference'));
        $('#variation-prix').val(button.attr('data-prix-supplement'));
        $('#variation-stock').val(button.attr('data-stock'));
        attributeList.empty();

        Object.entries(attributes).forEach(function ([name, value]) {
            addAttributeRow(name, value);
        });

        if (Object.keys(attributes).length === 0) {
            addAttributeRow();
        }

        openVariationForm();
    });

    variationForm.on('submit', function (e) {
        e.preventDefault();

        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        submitButton.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: this.action,
            data: form.serialize(),
            dataType: 'json',
            success: function (response) {
                showToast(response.message || 'Variation ajoutée avec succès.');

                if (variationsTable) {
                    variationsTable.ajax.reload(null, false);
                }

                $('#product-stock-total').text(response.stockTotal);
                variationForm.prop('hidden', true);
                resetVariationForm();
            },
            error: function (xhr) {
                showToast(xhr.responseJSON?.message || 'Impossible d’ajouter la variation.', 'error');
            },
            complete: function () {
                submitButton.prop('disabled', false);
            }
        });
    });

    $(document).on('submit', '.delete-variation-form', async function (e) {
        e.preventDefault();

        if (!await confirmAction('Voulez-vous vraiment supprimer cette variation ?')) {
            return;
        }

        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        submitButton.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: this.action,
            data: form.serialize(),
            dataType: 'json',
            success: function (response) {
                showToast(response.message || 'Variation supprimée avec succès.');

                if (variationsTable) {
                    variationsTable.ajax.reload(null, false);
                }

                $('#product-stock-total').text(response.stockTotal);
            },
            error: function (xhr) {
                showToast(xhr.responseJSON?.message || 'Impossible de supprimer la variation.', 'error');
                submitButton.prop('disabled', false);
            }
        });
    });

});
