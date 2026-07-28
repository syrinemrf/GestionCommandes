$(document).ready(function () {

    $('#profile-form').submit(function (event) {
        event.preventDefault();

        const form = $(this);
        const submitButton = form.find('button[type="submit"]');

        submitButton.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: this.action,
            data: form.serialize(),

            success: function (response) {
                if (!response.success) {
                    return;
                }

                $('#current-password, #new-password, #password-confirmation').val('');
                $('.sidebar .user-name').text(response.user.fullName);
                $('.navbar .user-email').text(response.user.email);
                showToast(response.message);
            },

            error: function (xhr) {
                showToast(xhr.responseJSON?.message || 'Une erreur est survenue.', 'error');
            },

            complete: function () {
                submitButton.prop('disabled', false);
            }
        });
    });

});
