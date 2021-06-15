InternetArchiveBot (IABot)
--------------------------

A Wikipedia bot that fights [linkrot](https://en.wikipedia.org/wiki/Wikipedia:Link_rot). [Read more about it](https://meta.wikimedia.org/wiki/InternetArchiveBot).

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

## First-time setup
- Create an account at https://meta.wikimedia.org
- Copy `app/src/deadlink.config.docker.inc.php` to `app/src/deadlink.config.local.inc.php`
- Add your Wikimedia account name to `$interfaceMaster['members'][]` in `app/src/deadlink.config.local.inc.php`;
- Obtain TWO OAuth consumers at https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration/propose
- First OAuth consumer goes to `$oauthKeys['default']['bot']` in `app/src/deadlink.config.local.inc.php`:
  - Set *Application name* to e.g. "IABot Dev Bot"
  - Set *Application description* to e.g. "localhost testing"
  - Check ON the checkbox *This consumer is for use only by <your-account>*
  - Check ON the following checkboxes in *Applicable grants*: *High-volume editing*, *Edit existing pages*, *Edit protected pages*, *Create, edit, and move pages*
  - Agree to the terms and click "Propose"
  - Copy obtained 4 keys to the corresponding entries in `$oauthKeys['default']['bot']` and your account name in `username`
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
- Open http://localhost:8080 - you will be redirected to http://localhost:8080/setup.php
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
  - Set *i18n source URL* to http://meta.wikimedia.org/w/api.php
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
  - On the *Define archive templates*, TBD
