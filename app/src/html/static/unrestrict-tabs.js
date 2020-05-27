$(window).on( 'click', 'a', function (event) {
    // Capture the href from the selected link...
    var link = this.href;
    var host = window.location.protocol + '//' + window.location.host;

    if (link.indexOf(host) !== -1) return true;
    else {
        window.open(link);
        return false;
    }
});