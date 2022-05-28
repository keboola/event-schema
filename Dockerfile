FROM composer:2.3 AS composer

FROM php:8.1-alpine

RUN apk add --no-cache \
        libzip-dev \
        libzip \
  && docker-php-ext-install zip \
  && apk del libzip-dev

COPY --from=composer /usr/bin/composer /usr/bin/composer
