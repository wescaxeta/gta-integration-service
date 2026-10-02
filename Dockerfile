FROM php:8.4-cli-alpine

RUN apk add --no-cache libpq libxml2 \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libxml2-dev linux-headers \
    && docker-php-ext-install pdo_pgsql soap \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
