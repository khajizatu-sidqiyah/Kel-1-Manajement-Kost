
# Tahap 1: Build frontend Laravel menggunakan Vite
FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build


# Tahap 2: Menjalankan Laravel menggunakan PHP dan Apache
FROM php:8.2-apache

# Instal kebutuhan sistem dan ekstensi PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        bcmath \
        exif \
        pcntl \
        gd \
        zip \
        intl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Instal Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Salin source code Laravel
COPY . .

# Salin hasil build Vite
COPY --from=frontend /app/public/build ./public/build

# Siapkan folder Laravel dan instal dependency
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache \
    && composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

# Atur Apache agar mengarah ke folder public Laravel
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' \
    /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/' \
    /etc/apache2/apache2.conf
    
ENV PORT=10000

EXPOSE 10000

CMD ["apache2-foreground"]
