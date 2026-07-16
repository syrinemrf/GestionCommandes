$(document).ready(function () {

    toggleLibelle();

    $('input[name="role"]').change(function () {

        toggleLibelle();

    });

    $('#email').change(function () {

        const emailInput = $(this);

        $.ajax({
            type: "POST",
            url: emailInput.data('check-url'),
            data: {
                email: emailInput.val(),
                id: emailInput.data('user-id')
            },
            dataType: 'json',

            success: function (response) {
                if (!response.available) {
                    showToast(response.message, 'error');
                    emailInput.val('');
                }
            }
        });

    });



    $('#user-form').submit(function (e) {

        e.preventDefault();

        const form = $(this);
        const submitButton = form.find('button[type="submit"]');

        submitButton.prop('disabled', true);

        $.ajax({
            type: "POST",
            url: this.action,
            data: form.serialize(),
            dataType: 'json',

            success: function (response) {
                if (response.success) {
                    showToast(response.message || form.data('success-message'));
                    if (form.data('reset-form')) {
                        form[0].reset();
                    }

                    toggleLibelle();
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


    function toggleLibelle() {

        if ($('input[name="role"]:checked').val() === "ROLE_FOURNISSEUR") {

            $('#libelle-container').show();

        } else {

            $('#libelle-container').hide();

        }

    }

    if ($('#users-table').length) {

        const usersTable = $('#users-table').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 15,
            lengthChange: false,
            ajax: $('#users-table').data('url'),

            columns: [
                { data: 'nom' },
                { data: 'prenom' },
                { data: 'email' },
                { data: 'role_badge', orderable: false, searchable: false },
                { data: 'libelle' },
                { data: 'actions', orderable: false, searchable: false }
            ]
        });

    }

    $(document).on('submit', '.delete-form', async function (e) {

        e.preventDefault();

        if (!await confirmAction("Voulez-vous vraiment supprimer cet utilisateur ?")) {
            return;
        }

        const form = $(this);
        const userId = form.data('user-id');

        $.ajax({
            type: "POST",
            url: this.action,
            data: form.serialize(),
            dataType: 'json',

            success: function (response) {
                if (response.success) {
                    $('#users-table').DataTable().ajax.reload(null, false);
                    showToast(response.message);
                }
            },

            error: function (xhr) {
                showToast(xhr.responseJSON?.message || "Une erreur est survenue.", 'error');
            }
        });

    });

    

});
