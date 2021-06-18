InternetArchiveBot (IABot)
--------------------------

A Wikipedia bot that fights [linkrot](https://en.wikipedia.org/wiki/Wikipedia:Link_rot).

# What is InternetArchiveBot
IABot is a powerful PHP, framework independent, OAuth bot designed primarily for use for Wikimedia Foundation wikis, per the request of the global communities, by [Cyberpower678](https://en.wikipedia.org/wiki/User:Cyberpower678). It is a global bot that uses wiki-specific functions in an abstract class to run on different wikis with different rules. For maximum flexibility, it features on and off site configuration values that can be altered to suit the operator, and/or the wiki community. Its function is to address many aspects of linkrot. For large sites, it can be set to multi-thread with a specified number of workers to get the job done faster. Each worker analyzes its own page, and reports back to the master with the statistics afterwards.

# How it works
IABot has a suite of functions it can do when it analyzes a page. Since the aim is to address link rot as completely as possible, it analyzes links in many ways by:

* Looking for URLs on the page rather than the DB. This allows the bot to grab how the url is being used, such as detecting if it's used in a cite template, a reference, or if it's a bare link. This allows the bot to intelligently handle sources formatted in various ways, almost like a human.
* Checking the archives if a link already exists, and if it doesn't request archiving into the Wayback Machine.
* Looking into the archives at the Wayback Machine to fetch a working copy of the page for a link that is dead, or using archives already being used for a URL on Wikipedia.
* Checking if untagged dead links are dead or not. This has a false positive rate of 0.1%.
* Automatically resolving templates that resolve as URLs in citation templates and working from there. The same also applies for templates as access dates.
* Saving all that information to a DB, which allows for the use of interfaces that can make use of this information, and allows the bot to learn, and improve its services.
* Convert existing archive URLs to their long form if enabled.
* Fix improper usages of archive templates, or mis-formatted URLs.

IABot's functions are in several different classes, based on the functions they do. Communication-related functions and wiki configuration values, are stored in the API class. DB related functions in the DB class, miscellaneous core functions in a static Core class, dead link checking functions in a CheckIfDead class, thread engine in Thread class, and the global and wiki-specific parsing functions in an abstract Parser class. While all but the last functions can run uniformly on all wikis, the Parser class requires a class extension due to its abstract nature. The class extensions contain the functions that allow the bot to operate properly on a given wiki, with its given rules. When the bot starts up, it will attempt to load the proper extension of the Parser class and initialize that as its parsing class.

# Installation

## Using Docker
Using Docker is the quickest and easiest way to install InternetArchiveBot.  If you expect to run the bot on a multitude of wikis, it may be better to break up the install to a dedicated execution VM and a dedicated MariaDB VM.

Docker automatically provides IABot with the needed PHP and MariaDB environment, but does not come with Tor support.

- For first-time setup, [see below](#first-time-setup)
- Run `docker-compose up`
- Open http://localhost:8080/ for the admin UI
- Run `docker-compose exec iabot php deadlink.php`

The Docker image is preloaded with xDebug.  It is recommended to use PHPStorm when developing, or debugging, InternetArchiveBot.  PHPStorm comes with Docker support, as well as VCS management, Composer support, and xDebug support.

## Manual install
Manually installing offers more flexibility, but is more complicated to set up.  This is the recommended method when deploying to a large wikifarm. IABot requires the following to run:

* PHP 7.2.9 or higher with intl, curl, mysqli, mysqlnd, json, pcntl, and tideways/xhprof (optional)
* A tor package from HomeBrew, apt, or some other package handler
* A SQL database (latest MariaDB recommended)
* [Composer](https://getcomposer.org/)
* A webserver like Apache httpd

1. Decide on whether or not to run the DB on a separate host
1. Install PHP with required extensions.  You can run `php -m` to check for installed modules, and `php -v` to check its version.
1. You may optionally install a Tor package from your host's package manager.  Tor will work right out of the box, if installed, and shouldn't require any further setup.
1. Install your database server on your desired host
1. Install your webserver on your host to run IABot
1. Clone this repo. For easiest setup, if your webserver loads content from `/var/www/html`, you can copy the contents of the repo to `/var/www`.
1. If you opt not to go this route, you may symlink, or move, the `html` folder of the this repo to the `html` folder of the webserver.
1. Create a file `html/setpath.php` with `<?php $path='/path/to/src/folder/';`
1. Run `composer install`
1. Copy `app/src/deadlink.config.inc.php` to `app/src/deadlink.config.local.inc.php`
1. Define your configuration values.  If you did steps 8 and 9, you need to define `$publicHTMLPath` as the relative path, relative to the location of the config file, to the `html` folder of the webserver.  Otherwise, you can just leave it as is.
1. Open a browser to your webserver to complete bot setup
1. When the bot is set up, you can execute the bot by running `php deadlink.php`

# Development

## Requirements
- Docker Compose `docker-compose`
- MySQL / MariaDB client `mysql`

## First-time setup
- Create an account at https://meta.wikimedia.org
- Copy `app/src/deadlink.config.docker.inc.php` to `app/src/deadlink.config.local.inc.php`
- Add your Wikimedia account name to `$interfaceMaster['members'][]` in `app/src/deadlink.config.local.inc.php`;
- Obtain TWO OAuth consumers at https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration/propose
- First OAuth consumer goes to `$oauthKeys['default']['bot']` in `app/src/deadlink.config.local.inc.php`
  - Set *Application name* to e.g. "IABot Dev Bot"
  - Set *Application description* to e.g. "localhost testing"
  - Check ON the checkbox *This consumer is for use only by <your-account>*
  - Check ON the following checkboxes in *Applicable grants*: *High-volume editing*, *Edit existing pages*, *Edit protected pages*, *Create, edit, and move pages*
  - Agree to the terms and click "Propose"
  - Copy obtained 4 keys to the corresponding entries in `$oauthKeys['default']['bot']` and your Wikimedia account name in `username`
- Second OAuth consumer goes to `$oauthKeys['default']['webappfull']` in `app/src/deadlink.config.local.inc.php`:
  - Set *Application name* to e.g. "IABot Dev Web App Full"
  - Set *Application description* to e.g. "localhost testing"
  - Check OFF the checkbox *This consumer is for use only by <your-account>*
  - Set *OAuth "callback" URL* to http://localhost:8080/oauthcallback.php
  - Check ON the following checkboxes in *Applicable grants*: *High-volume editing*, *Edit existing pages*, *Edit protected pages*, *Create, edit, and move pages*
  - Agree to the terms and click "Propose"
  - Copy obtained 2 keys to the corresponding entries in `$oauthKeys['default']['webappfull']`
- Run `docker-compose build` to build the IABot image
- Run `docker-compose up`, it will take a few minutes for the containers to come up
- Run `mysql -h127.0.0.1 -uroot -p5yBtFxeedrfupieNk7mp1oWyP8aR91kAp9nO8oSH iabot < first-time.sql`
- Open http://localhost:8080/index.php?page=systemconfig
- On *Login required* screen, click "Login to get started."
  - On the Wikipedia OAuth screen for app *IABot Dev Web App Full*, click "Allow"
  - If you are redirected to https://localhost:8080/index.php?page=systemconfig&systempage=definearchives&returnedfrom=oauthcallback, you will get a protocol error in your browser due to HTTPS. Edit the URL to change `https` to `http` such that the URL becomes http://localhost:8080/index.php?page=systemconfig&systempage=definearchives&returnedfrom=oauthcallback and navigate to it.
  - Accept the *Terms of Service* form
  - On the *User preferences* form, click "Save"

The bot should now be ready to run :tada:

### Troubleshooting
In case you can't import the `first-time.sql` database or prefer to perform a manual setup, do the following:
- Open http://localhost:8080, you will be redirected to http://localhost:8080/setup.php
- Fill in the *Configure system globals* form
  - Set *Disable bot editing* to "No"
  - Set *User Agent*, *User Agent to pass to external sites*, *The bot's task name* to `IABot`
  - Set *Enable logging on an external tool* to "No"
  - Set *Send failure emails when accessing the Wayback Machine fails* to "No"
  - Set *Web application email to send from* to your email
  - Set *Complete root URL of this web application* to http://localhost:8080/
  - Set *Use additional servers to validate if a link is dead* to "No"
  - Set *Enable performance profiling* to "No"
  - Set *Default wiki to load* to `testwiki`
  - Set *Enable automatic false positive reporting* to "No"
  - Set *Internet Archive Availability requests throttle* to 0
  - Set *Disable the interface* to "No"
  - Click "Submit"
- Fill in the *Define wiki* form
  - Set *i18n source URL* to https://meta.wikimedia.org/w/api.php
  - Set *i18n source name* to `meta`
  - Set *Default language* to `en`
  - Set *The root URL of the wiki* to https://test.wikipedia.org/
  - Set *URL to wiki API* to https://test.wikipedia.org/w/api.php
  - Set *URL to wiki OAuth* to https://test.wikipedia.org/w/index.php?title=Special:OAuth
  - Set *Enable runpage for this wiki* to "Yes"
  - Set *Enable nobots compliance* to "Yes"
  - Set *enablequeue* to "No"
  - Set *OAuth key set to use* to "Default"
  - Set *Wiki DB to use* to "Do not use the wiki DB"
  - Click "Submit"
- On *Login required* screen, click "Login to get started."
  - On the Wikipedia OAuth screen for app *IABot Dev Web App Full*, click "Allow"
  - If you are redirected to https://localhost:8080/index.php?page=systemconfig&systempage=definearchives&returnedfrom=oauthcallback, you will get a protocol error in your browser due to HTTPS. Edit the URL to change `https` to `http` such that the URL becomes http://localhost:8080/index.php?page=systemconfig&systempage=definearchives&returnedfrom=oauthcallback and navigate to it.
  - Accept the *Terms of Service* form
  - On the *User preferences* form, click "Save"
- On the *Define archive templates* form, add a template:
  - Click "+"
  - Set *Template name* to e.g. `Webarchive`
  - Set *Template behavior* to "Append template to URL"
  - Set *Template syntax* to `url|1={archiveurl}|date|2={archivetimestamp:automatic}|title={title}`
  - Click "Submit"
  - Click the top-level header "IABot Management Interface"
- Fill in the *Configure bot behavior* form
  - Set *Links to scan* to "All external links on an article"
  - Set *Scanned links to modify* to "All dead links"
  - Set *How to handle tagged links* to "Treat tagged links as dead"
  - Set *Tagging dead citation templates* to "Tag with a dead link template"
  - Set *Pages to scan* to "Scan all mainspace pages"
  - Set *Archive versions* to "Use archives closest to the access date (applies to newly searched archives)"
  - Set *Modify existing archives* to "No"
  - Set *Leave talk page messages* to "No"
  - Set *Only leave talk page messages* to "No"
  - Set *Leave archiving errors on talk pages* to "No"
  - Set *Leave verbose talk messages* to "No"
  - Set *Talk message section header* to `hello`
  - Set *Talk message* to `hello`
  - Set *Talk message only section header* to `hello`
  - Set *Talk only message* to `hello`
  - Set *Talk message error section header* to `hello`
  - Set *Talk error message* to `hello`
  - Set *Default used date formats* to `hello`
  - Set *Opt-out tags* to `{{hello}}`
  - Set *Talk-only tags* to `{{hello}}`
  - Set *No-talk tags* to `{{hello}}`
  - Set *Paywall tags* to `{{hello}}`
  - Set *Reference tags* to `{{hello}};{{hello}}`
  - Set *Opt-out tags* > *Webarchive* > *@default* to `{{hello}}`
  - Set *Dead link tags* to `{{hello}}`
  - Set *Template behavior* to "Append template to URL"
  - Set *Dead link template syntax* to blank
  - Set *Notify only on domains* to blank
  - Set *Scan for dead links* to "Yes"
  - Set *Submit live links to the Wayback Machine* to "No"
  - Set *Convert archives to long-form format* to "Yes"
  - Set *Normalize archive URL encoding* to "Yes"
  - Set *Convert plain links to cite templates* to "Yes"
  - Set *Edit rate limit* to `60 per minute`
  - Set *Added archive talk-only* to `hello`
  - Set *Dead link talk-only* to `hello`
  - Set *No dead link talk-only* to `hello`
  - Set *Added archive message item* to `hello`
  - Set *Modified archive message item* to `hello`
  - Set *Fixed source message item* to `hello`
  - Set *Dead link message item* to `hello`
  - Set *No dead message item* to `hello`
  - Set *Default message item* to `hello`
  - Set *Archive error item* to `hello`
  - Set *Edit summary* to `hello`
  - Set *Error message summary* to `hello`
  - Set *Message summary* to `hello`
  - Click "Submit"
- On the *Define user groups* form, create a new group:
  - Click "+"
  - Set *Group name* to `root`
  - Check ON all permission checkboxes in the *Inherits flags* section
  - Click "Submit"

### Debug run
- Ensure https://test.wikipedia.org/wiki/Moon exists or create it as a copy of https://en.wikipedia.org/wiki/Moon
- Edit `app/src/deadlink.config.local.inc.php`
  - Set `$debug = true;`
  - Set `$debugPage = [ 'title' => "Moon", 'pageid' => 0 ];`
  - Set `$debugStyle = "test";`
- Run `docker-compose exec iabot php deadlink.php`

# Configuration

As of v2.0, the values on wiki pages for configuring IABot are no longer used.  The bot instead is configured with the IABot Management Interface.  All global keywords are still used.

If you are running InternetArchiveBot yourself, you can configure it via the on wiki config page and by creating a new `deadlink.config.local.inc.php` file in the same directory. If someone else is running InternetArchiveBot and you just need to configure it for a particular wiki, you can set up a subpage of the bot's userpage called **Dead-links.js** and configure it there. For example, https://en.wikipedia.org/wiki/User:InternetArchiveBot/Dead-links.js. The configuration values are explained below:

* **link_scan** – Determines what to scan for when analyzing a page.  Set to 0 to handle every external URL on the article.  Set to 1 to only scan URLs that are inside reference tags.

* **page_scan** – Determines what pages to scan when doing it's run.  Set to 0 to scan all of the main space.  Set to 1 to only scan for pages that have dead link tags.

* **dead_only** – Determines what URLs it can touch and/or modify.  Set to 0 to allow the bot to modify all links.  Set to 1 to only allow the bot to modify URLs tagged as dead.  Set to 2 allow the bot to modify all URLs tagged as dead and and all dead URLs that are not tagged.

* **tag_override** – Tells the bot to override its own judgement regarding URLs.  If a human tags a URL as dead when the bot determines it alive, setting this to 1 will allow the tag to override the bot's judgement.  Set to 0 to disable.

* **archive_by_accessdate** – Setting this to 1 will instruct the bot to provide archive snapshots as close to the URLs original access data as possible.  Setting this to 0 will have the bot simply find the newest working archive.  Exceptions to this are the archive snapshots already found and stored in the DB for already scanned URLs.

* **touch_archive** – This setting determines whether or not the bot is allowed to touch a URL that already has an archive snapshot associated with it.  Setting this to 1 enables this feature.  Setting this to 0 disables this feature.  In the event of invalid archives being present or detectable mis-formatting of archive URLs, the bot will ignore this setting and touch those respective URLs.

* **notify_on_talk** – This setting instructs the bot to leave a message of what changes it made to a page on its respective talk page.  When editing the main page, the talk page message is only left when new archives are added to URLs or existing archives are changed.  When only leaving a talk page message without editing the main page, the message is left if a URL is detected to be dead, or archive snapshots were found for given URLs.  Setting this to 1 enables this feature.  Setting this to 0 disables it.

* **notify_error_on_talk** – This instructs the bot to leave messages about problematic sources not being archived on respective talk pages.  Setting to 1 enables this feature.

* **talk_message_header** – Set the section header of the talk page message it leaves behind, when **notify_on_talk** is set to 1. See the "Magic Word Globals" subsection for usable magic words.

* **talk_message** – The main body of the talk page message left when **notify_on_talk** is set to 1.

* **talk_message_header_talk_only** – Set the section header of the talk page message it leaves behind when the bot doesn't edit the main article. See the "Magic Word Globals" subsection for usable magic words.

* **talk_message_talk_only** – The main body of the talk page message left when the bot doesn't edit the main article. See the "Magic Word Globals" subsection for usable magic words.

* **talk_error_message_header** – Set the section header of the talk page error message left behind, when **notify_error_on_talk** is set to 1.

* **talk_error_message** – The main body of the talk page error message left when **notify_error_on_talk** is set to 1. Supports the following magic words:
  * *{problematiclinks}*: A bullet generated list of errors encountered during the archiving process.

* **deadlink_tags** – A collection of dead link tags to seek out.  Automatically resolves the redirects, so redirects are not required.  Format the template as you would on an article, without parameters.

* **citation_tags** – A collection of citation tags to seek out, that support URLs.  Automatically resolves the redirects, so redirects are not required.  Format the template as you would on an article, without parameters.

* **archive#_tags** –  A collection of general archive tags to seek out, that supports the archiving services IABot uses.  Automatically resolves the redirects, so redirects are not required.  Format the template as you would on an article, without parameters.  The "#" is a number.  Multiple categories can be implemented to handle different unique archiving templates.  This is dependent on how the bot is designed to handle these on a given wiki and is wiki specific.

* **talk_only_tags** –  A collection of IABot tags to seek out, that signal the bot to only leave a talk page message.  These tags overrides the active configuration.

* **no_talk_tags** –  A collection of IABot tags to seek out, that signal the bot to not leave a talk page message.  These tags overrides the active configuration.

* **ignore_tags** – A collection of bot specific tags to seek out.  These tags instruct the bot to ignore the source the tag is attached to.  Automatically resolves the redirects, so redirects are not required.  Format the template as you would on an article, without parameters.

* **verify_dead** – Activate the dead link checker algorithm.  The bot will check all untagged and not yet flagged as dead URLs and act on that information.  Set to 1 to enable.  Set to 0 to disable.

* **archive_alive** – Submit live URLs not yet in the Wayback Machine for archiving into the Wayback Machine.  Set to 1 to enable.  Requires permission from the developers of the Wayback Machine.

* **notify_on_talk_only** – Disable editing of the main article and leave a message on the talk page only.  This overrides **notify_on_talk**.  Set to 1 to enable.

* **convert_archives** – This option instructs the bot to convert all recognized archives to HTTPS when possible, and forces the long-form snapshot URLs, when possible, to include a decodable timestamp and original URL.

* **convert_to_cites** – This option instructs the bot to convert plain links inside references with no title to citation templates.  Set to 0 to disable.

* **mladdarchive** – Part of the **{modifiedlinks}** magic word, this is used to describe the addition of an archive to a URL. Supports the following magic words:
  * *{link}*: The original URL.
  * *{newarchive}*: The new archive of the original URL.

* **mlmodifyarchive** – Part of the **{modifiedlinks}** magic word, this is used to describe the modification of an archive URL for the original URL. Supports the following magic words:
  * *{link}*: The original URL.
  * *{oldarchive}*: The old archive of the original URL.
  * *{newarchive}*: The new archive of the original URL.

* **mlfix** – Part of the **{modifiedlinks}** magic word, this is used to describe the formatting changes and/or corrections made to a URL. Supports the following magic words:
  * *{link}*: The original URL.

* **mltagged** –  Part of the **{modifiedlinks}** magic word, this is used to describe that the original URL has been tagged as dead. Supports the following magic words:
  * *{link}*: The original URL.

* **mltagremoved** – Part of the **{modifiedlinks}** magic word, this is used to describe that the original URL has been untagged as dead. Supports the following magic words:
  * *{link}*: The original URL.

* **mldefault** – Part of the **{modifiedlinks}** magic word, this is used as the default text in the event of an internal error when generating the **{modifiedlinks}** magic word. Supports the following magic words:
  * *{link}*: The original URL.

* **mladdarchivetalkonly** – Part of the **{modifiedlinks}** magic word, this is used to describe the recommended addition of an archive to a URL.  This is used when the main article hasn't been edited. Supports the following magic words:
  * *{link}*: The original URL.
  * *{newarchive}*: The new archive of the original URL.

* **mltaggedtalkonly** –  Part of the **{modifiedlinks}** magic word, this is used to describe that the original URL has been found to be dead and should be tagged.  This is used when the main article hasn't been edited. Supports the following magic words:
  * *{link}*: The original URL.

* **mltagremovedtalkonly** – Part of the **{modifiedlinks}** magic word, this is used to describe that the original URL has been tagged as dead, but found to be alive and recommends the removal of the tag.  This is used when the main article hasn't been edited. Supports the following magic words:
  * *{link}*: The original URL.

* **plerror** – Part of the **{problematiclinks}** magic word, this is used to describe the problem the Wayback machine encountered during archiving. Supports the following magic words:
  * *{problem}*: The problem URL.
  * *{error}*: The error that was encountered for the URL during the archiving process.

* **maineditsummary** – This sets the edit summary the bot will use when editing the main article. See the "Magic Word Globals" subsection for usable magic words. (Items 11, 12, and 13 are not supported)

* **errortalkeditsummary** – This sets the edit summary the bot will use when posting the error message on the article's talk page.

* **talkeditsummary** = This sets the edit summary the bot will use when posting the analysis information on the article's talk page. See the [Magic Word Globals](#magic-word-globals) subsection for usable magic words.

## Magic Word Globals

These magic words are available when mentioned in the respective configuration options above.
* **{namespacepage}**: The page name of the main article that was analyzed.
* **{linksmodified}**: The number of links that were either tagged or rescued on the main article.
* **{linksrescued}**: The number of links that were rescued on the main article.
* **{linksnotrescued}**: The number of links that were unable to be rescued on the main article.
* **{linkstagged}**: The number of links that were tagged dead on the main article.
* **{linksarchived}**: The number of links that were archived into the Wayback Machine on the main article.
* **{linksanalayzed}**: The number of links that were overall analyzed on the main article.
* **{pageid}**: The page ID of the main article that was analyzed.
* **{title}**: The URL encoded variant of the name of the main article that was analyzed.
* **{logstatus}**: Returns "fixed" when the bot is set to edit the main article.  Returns "posted" when the bot is set to only leave a message on the talk page.
* **{revid}**: The revision ID of the edit to the main article.  Empty if there is no edit to the main article.
* **{diff}**: The URL of the revision comparison page of the edit to main article.  Empty if there is no edit to the main article.
* **{modifedlinks}**: A bullet generated list of actions performed/to be performed on the main article using the custom defined text in the other variables.
