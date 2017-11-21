function changeCheckboxes(value) {
    var inputs = document.getElementsByTagName('input');
    var list = [];
    for (var j = inputs.length - 1; j >= 0; j--) {
        if (inputs[j].type === 'checkbox') {
            list.push(inputs[j]);
        }
    }
    for (var i = list.length - 1; i >= 0; i--) {
        list[i].checked = (typeof value === 'boolean') ? value : !list[i].checked;
    }
}

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

function openLangMenu() {
    $('#userlangdropdown').addClass('open');
    document.getElementById('userlangdropdowna').setAttribute("aria-expanded", 'true');
}

function closeLangMenu() {
    $('#userlangdropdown').removeClass('open');
    document.getElementById('userlangdropdowna').setAttribute("aria-expanded", 'false');
}

function toggleLangMenu() {
    $('#userlangdropdown').toggleClass('open');
    if (document.getElementById('userlangdropdowna').getAttribute("aria-expanded") == 'false') document.getElementById('userlangdropdowna').setAttribute("aria-expanded", 'true');
    else document.getElementById('userlangdropdowna').setAttribute("aria-expanded", 'false');
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

function loadBotQueue(getString) {
    $.getJSON("index.php?format=json&".concat(getString),
        function (data) {
            if (data == null || data == false) {
                setTimeout("location.reload()", 20000);
            } else {
                if (typeof currentList === 'undefined') {
                    currentList = [];
                    for (var key in data) {
                        if (data.hasOwnProperty(key)) {
                            currentList.push(key);
                        }
                    }
                }
                for (var key in data) {
                    if (data.hasOwnProperty(key)) {
                        if (currentList.indexOf(key) == -1) {
                            location.reload();
                        }
                        if (!$('#row'.concat(key)).hasClass(data[key]['class'])) {
                            $('#row'.concat(key)).removeClass('info');
                            $('#row'.concat(key)).removeClass('warning');
                            $('#row'.concat(key)).removeClass('danger');
                            $('#row'.concat(key)).removeClass('success');
                            $('#row'.concat(key)).addClass(data[key]['class']);
                        }
                        if (!$('#progressbar'.concat(key)).hasClass(data[key]['classProg'])) {
                            $('#progressbar'.concat(key)).removeClass('progress-bar-danger');
                            $('#progressbar'.concat(key)).removeClass('progress-bar-warning');
                            $('#progressbar'.concat(key)).removeClass('progress-bar-info');
                            $('#progressbar'.concat(key)).addClass(data[key]['classProg']);
                        }
                        if (typeof data[key]['progresstext'] === 'undefined') {
                            $('#status'.concat(key)).html(data[key]['statushtml']);
                        } else {
                            if ($('#status'.concat(key)).html().search("progress") === -1) {
                                $('#status'.concat(key)).html(data[key]['statushtml']);
                            }
                            $('#progressbar'.concat(key)).attr("style", data[key]['style']);
                            $('#progressbar'.concat(key)).attr("aria-valuenow", data[key]['aria-valuenow']);
                            $('#progressbartext'.concat(key)).html(data[key]['progresstext']);
                        }
                        $('#buttons'.concat(key)).html(data[key]['buttonhtml']);
                    }
                }
                setTimeout("loadBotQueue('".concat(getString.concat("')")), 5000);
            }
        })
        .fail(function () {
            setTimeout("loadBotQueue('".concat(getString.concat("')")), 5000);
        })
}

function loadBotJob(getString) {
    $.getJSON("index.php?format=json&".concat(getString),
        function (data) {
            if (data == null || data == false) {
                setTimeout("location.reload()", 10000);
            } else {
                $('#bqstatus').html(data['bqstatus']);
                $('#pagesmodified').html(data['pagesmodified']);
                $('#linksanalyzed').html(data['linksanalyzed']);
                $('#linksrescued').html(data['linksrescued']);
                $('#linkstagged').html(data['linkstagged']);
                $('#linksarchived').html(data['linksarchived']);
                $('#progressbar').attr("style", data['style']);
                $('#progressbar').attr("aria-valuenow", data['aria-valuenow']);
                $('#progressbartext').html(data['progresstext']);

                if (!$('#progressbar').hasClass(data['classProg'])) {
                    $('#progressbar').removeClass('progress-bar-danger');
                    $('#progressbar').removeClass('progress-bar-warning');
                    $('#progressbar').removeClass('progress-bar-info');
                    $('#progressbar').removeClass('progress-bar-success');
                    $('#progressbar').removeClass('progress-bar-striped');
                    $('#progressbar').removeClass('active');
                    $('#progressbar').addClass(data['classProg']);
                    if (data['classProg'].search('info') != -1) {
                        $('#progressbar').addClass('progress-bar-striped');
                        $('#progressbar').addClass('active');
                    }
                }

                $('#buttonhtml').html(data['buttonhtml']);
                $('#jobpages').html(data['pagelist']);
                setTimeout("loadBotJob('".concat(getString.concat("')")), 5000);
            }
        })
        .fail(function () {
            setTimeout("location.reload()", 10000);
        })
}

$("a").click(function (event) {
    // Capture the href from the selected link...
    var link = this.href;
    var host = window.location.protocol + '//' + window.location.host;

    if (link.indexOf(host) !== -1) return true;
    else {
        window.open(link, "IABotGUILinkedExternalLinkWindow");
        return false;
    }
});
