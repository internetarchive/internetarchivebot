var currentOffset = 0;

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
    $.getJSON("index.php?format=json".concat(getString),
        function (data) {
            if (data == null || data == false) {
                setTimeout("loadBotQueue('".concat(getString.concat("')")), 10000);
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
                        $('#queuetable').html(data['table']);
                        $('#summaryheader').html(data['summary']);
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
    $.getJSON("index.php?format=json&offset="+currentOffset+"&".concat(getString),
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


                // $('#jobpages').html(data['pagelist']);
                var page;
                for (var key in data['pages']) {
                    if (data['pages'].hasOwnProperty(key)) {
                        page = data['pages'][key];
                        console.log( page['page_title'] + ' changed' );

                        // Built the new UI
                        // TODO get this url from the JSON
                        var url = "https://en.wikipedia.org/wiki/";
                        var stateStyleColor;
                        var stateClassName;
                        var stateGlyphName;
                        if( $page['rev_id'] == 0) {
                            url += encodeURIComponent( page['page_title'] );
                        } else {
                            url += 'Special:Diff/' + encodeURIComponent( page['rev_id'] );
                        }

                        if (page['status'] === 'complete') {
                            stateStyleColor = '#62C462';
                            stateClassName = 'has-success';
                            stateGlyphName = 'glyphicon-ok-sign';
                        } else if (page['status'] === 'skipped') {
                            stateStyleColor = '#EE5F5B';
                            stateClassName = 'has-error';
                            stateGlyphName = 'glyphicon-remove-sign';
                        }
                        $('#li'+key)
                            .empty()
                            .css({
                                'color': stateStyleColor
                            })
                            .append( $('<span>').addClass(stateClassName).append(
                                $('<label class="control-label"><span class="glyphicon' +
                            stateGlyphName + '"></span></label>') )
                            )
                            .append( $('<a>').attr( 'href', url ).text( page['page_title'] ) );
                    }
                    if( key >= currentOffset ) {
                        currentOffset = key;
                    }
                }

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

// Preview the theme when changing it in the dropdown in the preferences
$(function () {
    var loadingLink;

    $("#user_default_theme").on("change", function () {
        var value = this.selectedOptions[0].dataset.theme || this.value;

        // Another link is in the process of being shown. Remove it.
        if (loadingLink) {
            loadingLink.remove();
        }

        // Replace the linked CSS file.
        var oldLink = $("link[rel=stylesheet][href$='bootstrap.min.css']");
        $("html").addClass("theme-transition");

        loadingLink = oldLink
            .clone()
            .prop("href", "static/" + value + "/css/bootstrap.min.css")
            .on("load", function () {
                oldLink.remove();
                loadingLink = undefined;
            })
            .on("error", function () {
                loadingLink.remove();
                loadingLink = undefined;
                $(".theme-transition").removeClass("theme-transition");
            })
            .insertAfter(oldLink);
    });
});
