FROM phpstorm/php-73-apache-xdebug-27
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends git zlib1g-dev libicu-dev g++ \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl
WORKDIR /var/www
COPY composer.json composer.lock ./
ENV COMPOSER_VENDOR_DIR /vendor
RUN composer install
