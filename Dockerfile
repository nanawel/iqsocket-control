# iqsocket-control Dockerfile
# Author: nanawel@gmail.com

FROM php:7.4-cli-alpine3.11

RUN apk add net-snmp-dev
RUN docker-php-ext-install -j$(nproc) snmp && rm -rf /tmp/*

ADD https://github.com/composer/composer/releases/download/1.10.0/composer.phar /usr/local/bin/composer
RUN chmod 0755 /usr/local/bin/composer

WORKDIR /app

COPY . /app
COPY .env.docker /app/.env
RUN COMPOSER_HOME=/tmp/composer COMPOSER_CACHE_DIR=/tmp/composer/cache composer install

ENTRYPOINT ["/app/bin/console"]
