function openUserMenu() {
    $('#usermenudropdown').addClass('open');
    document.getElementById('usermenudropdowna').setAttribute("aria-expanded", 'true');
}
function closeUserMenu() {
    $('#usermenudropdown').removeClass('open');
    document.getElementById('usermenudropdowna').setAttribute("aria-expanded", 'false');
}
function openWikiMenu() {
    $('#userwikidropdown').addClass('open');
    document.getElementById('userwikidropdowna').setAttribute("aria-expanded", 'true');
}
function closeWikiMenu() {
    $('#userwikidropdown').removeClass('open');
    document.getElementById('userwikidropdowna').setAttribute("aria-expanded", 'false');
}
function toggleWikiMenu() {
    $('#userwikidropdown').toggleClass('open');
    if (document.getElementById('userwikidropdowna').getAttribute("aria-expanded") == 'false') document.getElementById('userwikidropdowna').setAttribute("aria-expanded", 'true');
    else document.getElementById('userwikidropdowna').setAttribute("aria-expanded", 'false');
}
function toggleUserMenu() {
    $('#usermenudropdown').toggleClass('open');
    if (document.getElementById('usermenudropdowna').getAttribute("aria-expanded") == 'false') document.getElementById('usermenudropdowna').setAttribute("aria-expanded", 'true');
    else document.getElementById('usermenudropdowna').setAttribute("aria-expanded", 'false');
}
function throwEmailFormError(error) {
    $('#emailform').removeClass('has-success');
    $('#emailform').removeClass('has-warning');
    $('#emailform').addClass('has-error');
    $('#emailglyphicon').removeClass('glyphicon-ok');
    $('#emailglyphicon').removeClass('glyphicon-warning-sign');
    $('#emailglyphicon').addClass('glyphicon-remove');
    $('#emailconfirmtext').text(error);
}
function throwEmailFormWarning(warning) {
    $('#emailform').removeClass('has-success');
    $('#emailform').addClass('has-warning');
    $('#emailform').removeClass('has-error');
    $('#emailglyphicon').removeClass('glyphicon-ok');
    $('#emailglyphicon').addClass('glyphicon-warning-sign');
    $('#emailglyphicon').removeClass('glyphicon-remove');
    $('#emailconfirmtext').text(warning);
}
function throwEmailFormSuccess(success) {
    $('#emailform').addClass('has-success');
    $('#emailform').removeClass('has-warning');
    $('#emailform').removeClass('has-error');
    $('#emailglyphicon').addClass('glyphicon-ok');
    $('#emailglyphicon').removeClass('glyphicon-warning-sign');
    $('#emailglyphicon').removeClass('glyphicon-remove');
    $('#emailconfirmtext').text(success);
}
function resetEmailForm() {
    $('#emailform').removeClass('has-success');
    $('#emailform').removeClass('has-warning');
    $('#emailform').removeClass('has-error');
    $('#emailglyphicon').removeClass('glyphicon-ok');
    $('#emailglyphicon').removeClass('glyphicon-warning-sign');
    $('#emailglyphicon').removeClass('glyphicon-remove');
    $('#emailconfirmtext').text("");
}
(function (h, o, t, j, a, r) {
    h.hj = h.hj || function () {
            (h.hj.q = h.hj.q || []).push(arguments)
        };
    h._hjSettings = {hjid: 370938, hjsv: 5};
    a = o.getElementsByTagName('head')[0];
    r = o.createElement('script');
    r.async = 1;
    r.src = t + h._hjSettings.hjid + j + h._hjSettings.hjsv;
    a.appendChild(r);
})(window, document, '//static.hotjar.com/c/hotjar-', '.js?sv=');
function validateEmail(savedemail, success, warning, error) {
    var x = $('#email').val();
    if (x == '') {
        resetEmailForm();
        return;
    }
    if (x == savedemail) {
        throwEmailFormSuccess(success);
        return;
    }
    var re = /^(([^<>()\[\]\.,;:\s@\"]+(\.[^<>()\[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
    if (re.test(x)) {
        throwEmailFormWarning(warning);
        return;
    } else {
        throwEmailFormError(error);
        return;
    }
}