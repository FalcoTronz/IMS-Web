FROM php:8.0-apache

# Install PostgreSQL extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Copy your app files
COPY . /var/www/html/

# Enable Apache mod_rewrite (optional but useful)
RUN a2enmod rewrite
