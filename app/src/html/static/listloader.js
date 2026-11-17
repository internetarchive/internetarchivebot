$(function () {
    initManageUrlDomain();
});

async function initManageUrlDomain() {
    console.log('init manage url domain')
    var paywallIds = $("#domaincontrolform input[name=paywallids]").val();
    if (paywallIds) {
        var runBotQueueButton = $("#runbotqueue").hide();

        if (!await loadUrls(
            paywallIds,
            $("#found-urls"),
            $("#list-urls-progress"),
            "urls",
            function (result) {
                return result.urls.map(function (url) {
                    // The URL links to the live page; the icon opens IABot's data page for that URL.
                    return $("<li>").append(
                        $("<a>", {href: url, text: url}),
                        " ",
                        $("<sup>").append(
                            "(",
                            $("<a>", {
                                href: "index.php?page=manageurlsingle&url=" + encodeURIComponent(url),
                                title: "URL data"
                            }).append(
                                $("<span>", {class: "glyphicon glyphicon-list-alt", "aria-hidden": "true"}),
                                $("<span>", {class: "sr-only", text: "URL data"})
                            ),
                            ")"
                        )
                    );
                });
            })) return;

        if (!await loadUrls(
            paywallIds,
            $("#found-pages"),
            $("#list-pages-progress"),
            "pages",
            function (result) {
                return result.pages.map(function (page) {
                    return $("<li>").append(
                        $("<a>", {href: page.url, text: page.title})
                    );
                });
            })) return;

        runBotQueueButton.show();
    }
}

async function loadUrls(paywallIds, ol, progress, load, resultToListItemsCallback) {
    // Show progress
    progress.show();
    var offset = 0;

    do {
        var domainSearch = $("#domaincontrolform input[name=domainsearch]").val();

        // Result: { urls: ['http://...'], offset: 123 }
        var result = await $.get("index.php?page=manageurldomain&pageaction=submitpaywalls&domainsearch="
            + domainSearch
            + "&paywallids=" + paywallIds
            + "&load=" + load
            + "&offset=" + offset);

        if (result.error) {
            progress.hide();
            ol.before($("<div>", {class: "alert alert-danger", text: result.error}));
            return false;
        }

        offset = result.offset;

        // Append the result
        ol.append(resultToListItemsCallback(result));

        // Update progress
        var percentage = result.total ? Math.round(1e5 * result.progress / result.total) / 1e3 : 100;
        progress.find(".progress-bar")
            .attr("aria-valuenow", percentage)
            .css("width", percentage + "%");
        progress.find("span").text(percentage + "%");
    } while (result.continue);

    // Remove progress bar
    progress.hide();
    return true;
}

