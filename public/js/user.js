$(document).ready(function () {

    toggleLibelle();

    $('input[name="role"]').change(function () {

        toggleLibelle();

    });


    $('#user-form').submit(function (e) {

        e.preventDefault();

        $.ajax({
            type: "POST",
            url: "/users/add",
            data: $(this).serialize(),
            success: function () {
                alert("Utilisateur ajouté avec succès.");
                $('#user-form')[0].reset();
                toggleLibelle();
            },

            error: function () {

                alert("Une erreur est survenue.");

            }

        });

    });

});


function toggleLibelle() {

    if ($('input[name="role"]:checked').val() === "ROLE_FOURNISSEUR") {

        $('#libelle-container').show();

    } else {

        $('#libelle-container').hide();

    }

}