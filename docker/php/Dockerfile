# Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/docker/php/Dockerfile

# Use the official PHP 8.2 Apache image
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    libzip-dev \
    libicu-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions from your requirements
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo_mysql gd zip intl

# Install Redis extension (for caching/sessions)
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer (PHP package manager)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- START OF FIX ---

# 1. Enable Apache rewrite AND headers modules
RUN a2enmod rewrite headers

# 2. Copy our custom Apache config to allow .htaccess files
COPY ./docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf

# --- END OF FIX ---

# Set the working directory
WORKDIR /var/www/html