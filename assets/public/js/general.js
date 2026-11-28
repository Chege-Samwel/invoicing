$(document).ready(function () {
    $('.carousel-caption img').fadeIn(3000);
    $('.carousel').carousel({
        pause: 'hover',
        interval: 5000,
    });
    $('.close-alert').click(function () {
        $('.alert-errors').hide();
    });
});
function registerValidate() {
    $('.form-registration sup').text('');
    var valid = true;
    var email = $('#user_email').val();
    var password = $('#user_password').val();
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,15})+$/;
    var result_email = regex.test(email);
    password = $.trim(password);
    var pass_lenght = password.length;
    if (result_email == false) {
        $('.err-email').text(lang.invalid_email);
        valid = false;
    }
    if (pass_lenght == 0) {
        $('.err-password').text(lang.invalid_pass);
        valid = false;
    }
    if (valid == true) {
        document.getElementById("registerMe").submit();
    }
}
/*
 * invReceiveAction() used to POST an anonymous accept/refuse straight onto the
 * invoice. It is gone with the form it submitted: a client's answer is filed
 * as a request and confirmed by the business. Kept as a no-op because a cached
 * copy of an old page may still call it.
 */
function invReceiveAction() {
    return false;
}