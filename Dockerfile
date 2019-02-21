FROM php:7.3.2-cli-alpine3.9

ADD https://github.com/composer/composer/releases/download/1.8.4/composer.phar /usr/local/bin/composer
RUN chmod 0755 /usr/local/bin/composer

WORKDIR /app

COPY . /app
RUN COMPOSER_HOME=/tmp/composer COMPOSER_CACHE_DIR=/tmp/composer/cache composer install

ENTRYPOINT ["/app/bin/console"]