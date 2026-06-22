# syntax=docker/dockerfile:1
# PHP-FPM image for local development of Watcha (Symfony 7.4 / PHP 8.3).
FROM php:8.3-fpm-alpine

# System packages required to build PHP extensions and run Composer.
RUN apk add --no-cache \
        git unzip icu-dev oniguruma-dev libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip opcache \
    && apk del $PHPIZE_DEPS

# Sensible PHP defaults for development.
RUN { \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=16M'; \
        echo 'post_max_size=16M'; \
        echo 'date.timezone=Europe/Paris'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

# Composer (copied from the official image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Match the host UID/GID in most setups to avoid permission issues on mounted volumes.
ARG UID=1000
RUN adduser -D -u ${UID} app || true
USER app

EXPOSE 9000
CMD ["php-fpm"]
