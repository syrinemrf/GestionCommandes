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

});
