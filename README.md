InternetArchiveBot (IABot)
--------------------------

A Wikipedia bot that fights [linkrot](https://en.wikipedia.org/wiki/Wikipedia:Link_rot). [Read more about it](https://meta.wikimedia.org/wiki/InternetArchiveBot).

# Installation

## Using Docker
Using Docker is the quickest and easiest way to install InternetArchiveBot.  If you expect to run the bot on a multitude of wikis, it may be better to break up the install to a dedicated execution VM and a dedicated MariaDB VM.

Docker automatically provides IABot with the needed PHP and MariaDB environment, but does not come with Tor support.

1. Run `docker-compose build` to build the IABot image
1. Run `docker-compose up`, it will take a few minutes for the containers to come up for the first time
1. Rename `app/src/deadlink.config.docker.inc.php` to `app/src/deadlink.config.local.inc.php`
1. Define your configuration values, leaving the preconfigured values alone
1. Goto http://localhost:8080/ to complete bot setup
1. When the bot is set up, you can execute the bot from within Docker's environment, by running `docker-compose exec iabot php deadlink.php`

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
1. Copy `app/src/deadlink.config.inc.php` to `deadlink.config.local.inc.php`
1. Define your configuration values.  If you did steps 8 and 9, you need to define `$publicHTMLPath` as the relative path, relative to the location of the config file, to the `html` folder of the webserver.  Otherwise, you can just leave it as is.
1. Open a browser to your webserver to complete bot setup
1. When the bot is set up, you can execute the bot by running `php deadlink.php`
