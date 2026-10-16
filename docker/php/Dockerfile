FROM php:8.3-fpm-alpine

# Extensions nécessaires à Symfony, Doctrine (PostgreSQL), EasyAdmin et Dompdf.
RUN apk add --no-cache icu-libs libpq libzip freetype libpng libjpeg-turbo \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libpq-dev libzip-dev \
        freetype-dev libpng-dev libjpeg-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql zip gd opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-shortcut.ini

# Le conteneur tourne avec l'uid de l'utilisateur hôte (voir compose.yaml) :
# Composer a besoin d'un HOME accessible en écriture.
ENV COMPOSER_HOME=/tmp/composer

WORKDIR /app
