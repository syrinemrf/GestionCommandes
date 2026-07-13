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

            success: function (response) {
                response = response.trim();

                if (response === 'email_exists') {
                    alert("Cet email est déjà utilisé.");
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

            success: function (response) {
                response = response.trim();

                if (response === 'success') {
                    alert(form.data('success-message'));
                    if (form.data('reset-form')) {
                        form[0].reset();
                    }

                    toggleLibelle();
                }
            },

            error: function () {
                alert("Une erreur est survenue.");
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

    $('.delete-form').submit(function (e) {

        e.preventDefault();

        if (!confirm("Voulez-vous vraiment supprimer cet utilisateur ?")) {
            return;
        }

        const form = $(this);
        const userId = form.data('user-id');

        $.ajax({
            type: "POST",
            url: this.action,

            success: function (response) {
                response = response.trim();

                if (response === 'success') {
                    $('tr[data-user-id="' + userId + '"]').remove();
                    alert("Utilisateur supprimé avec succès.");
                }
            },

            error: function () {
                alert("Une erreur est survenue.");
            }
        });

    });

    

});