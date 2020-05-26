$(function () {
    initManageUrlDomain();
});

async function initManageUrlDomain() {
    console.log('init manage url domain')
    var paywallIds = $("#domaincontrolform input[name=paywallids]").val();
    if (paywallIds) {
        var runBotQueueButton = $("#runbotqueue").hide();

        await loadUrls(
            paywallIds,
            $("#found-urls"),
            $("#list-urls-progress"),
            "urls",
            function (result) {
                return result.urls.map(function (url) {
                    return $("<li>").append(
                        $("<a>", { href: url, text: url })
                    );
                });
            });

        await loadUrls(
            paywallIds,
            $("#found-pages"),
            $("#list-pages-progress"),
            "pages",
            function (result) {
                return result.pages.map(function (page) {
                    return $("<li>").append(
                        $("<a>", { href: page.url, text: page.title })
                    );
                });
            });

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

        offset = result.offset;

        // Append the result
        ol.append(resultToListItemsCallback(result));

        // Update progress
        var percentage = Math.round( 1e5 * result.progress / result.total ) / 1e3;
        progress.find(".progress-bar")
            .attr("aria-valuenow", percentage)
            .css("width", percentage + "%");
        progress.find("span").text(percentage + "%");
    } while (result.continue);

    // Remove progress bar
    progress.hide();
}

