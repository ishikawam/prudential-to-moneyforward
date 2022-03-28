# local php

FROM php:8.1-fpm-alpine

# composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# memory_limit
COPY ./default.ini /usr/local/etc/php/conf.d/default.ini

# zip for web-driver
RUN apk add --no-cache --update libzip-dev \
    && docker-php-ext-install zip
