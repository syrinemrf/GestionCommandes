$(document).ready(function () {

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
                response = response.trim();

                if (response === 'success') {
                    alert(form.data('success-message'));

                    if (form.data('reset-form')) {
                        form[0].reset();
                    }
                } else {
                    alert(response || "Une erreur est survenue.");
                }
            },

            error: function (xhr) {
                alert(xhr.responseText || "Une erreur est survenue.");
            },

            complete: function () {
                submitButton.prop('disabled', false);
            }
        });

    });

    if ($('#products-table').length) {

        const columns = [

            {
                data: 'image',
                orderable: false,
                searchable: false,
                render: function (data) {
                    return data
                        ? '<img src="' + data + '" class="product-image" alt="Produit">'
                        : '-';
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


        $('#products-table').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 15,
            lengthChange: false,
            ajax: $('#products-table').data('url'),

            

            columns: columns
        });

    }

    $(document).on('submit', '.delete-form', function (e) {

        e.preventDefault();

        if (!confirm("Voulez-vous vraiment supprimer ce produit ?")) {
            return;
        }

        const form = $(this);

        $.ajax({
            type: "POST",
            url: this.action,
            data: form.serialize(),

            success: function (response) {
                response = response.trim();

                if (response === 'success') {
                    $('#products-table').DataTable().ajax.reload(null, false);
                    alert("Produit supprimé avec succès.");
                }
            },

            error: function () {
                alert("Une erreur est survenue.");
            }
        });

    });

});
