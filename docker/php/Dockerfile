# local php

FROM php:8.3-fpm-alpine

# composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# memory_limit
COPY ./default.ini /usr/local/etc/php/conf.d/default.ini

# zip for web-driver
RUN apk add --no-cache --update libzip-dev \
    autoconf gcc g++ make linux-headers \
    && docker-php-ext-install zip

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug
