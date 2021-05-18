# Contact
Email: mark@archive.org

# Installation and Requirements
IABot requires the following to run:

* PHP 7.2.9 or higher with intl, curl, mysqli, mysqlnd, json, pcntl, and tideways/xhprof (optional)
* A tor package from HomeBrew, apt, or some other package handler
* A SQL DB (latest MariaDB recommended)
* Composer from getcomposer.org
* A webserver like Apache

## Using Docker
Using Docker is the quickest and easiest way to install InternetArchiveBot.  If you expect to run the bot on a multitude of wikis, it may be better to break up the install to a dedicated execution VM and a dedicated MariaDB VM.

Docker automatically provides IABot with the needed PHP and MariaDB environment, but does not come with Tor support.

1. Clone this repo to your desired directory.
1. Run `docker-compose build` to build the IABot image
1. Run `docker-compose up`, it will take a few minutes for the containers to come up for the first time
1. Rename `app/src/deadlink.config.docker.inc.php` to `app/src/deadlink.config.local.inc.php`
1. Define your configuration values, leaving the preconfigured values alone
1. Goto http://localhost:8080/ to complete bot setup
1. When the bot is set up, you can execute the bot from within Docker's environment, by running `docker-compose exec php /var/www/deadlink.php`

The Docker image is preloaded with xDebug.  It is recommended to use PHPStorm when developing, or debugging, InternetArchiveBot.  PHPStorm comes with Docker support, as well as VCS management, Composer support, and xDebug support.

## Manual install
Manually installing offers more flexibility, but is more complicated to set up.  This is the recommended method when deploying to a large wikifarm.

1. Decide on whether or not to run the DB on a separate host.
2. Install PHP with required extensions.  You can run `php -m` to check for installed modules, and `php -v` to check its version.
3. You may optionally install a tor package from your host's package manager.  Tor will work right out of the box, if installed, and shouldn't require any further setup.
4. Install your DB server (MariaDB is recommended) on your desired host.
5. Install your webserver on your host to run IABot.
6. Clone this repo.  For easiest setup, if your webserver loads content from /var/www/html, you can copy the contents of the repo to /var/www.  Refer to step 7 and 8 if you opt to not go this route, else skip to 9.
7. If you opt not to go this route, you may symlink, or move, the html folder of the this repor to the html folder of the webserver.
8. Create `setpath.php` file in the html folder with `<?php $path='/path/to/src/folder/';`
9. cd into the root folder of your clone.
10. Install composer and run a composer install.
11. Navigate to the 'app/src/' folder and copy deadlink.config.inc.php to deadlink.config.local.inc.php
12. Define your configuration values.  If you did steps 8 and 9, you need to define `$publicHTMLPath` as the relative path, relative to the location of the config file, to the html folder of the webserver.  Otherwise, you can just leave it as is.
13. Open a webbrowser to your web server to complete bot setup.
14. When the bot is set up, you can execute the bot by running `php deadlink.php`
