FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl-dev \
    git \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    sqlite-dev \
    unzip \
    && docker-php-ext-install \
    bcmath \
    curl \
    intl \
    mbstring \
    pdo \
    pdo_sqlite \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
