FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    libmemcached-dev \
    zlib1g-dev \
    libzip-dev \
    unzip \
    git \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && docker-php-ext-install mysqli \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
