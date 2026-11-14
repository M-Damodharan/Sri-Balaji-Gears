# Sri Balaji Gears - Production Dockerfile for Render Web Service
FROM php:8.2-apache

# Install required system libraries and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_sqlite zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable essential Apache modules
RUN a2enmod rewrite headers deflate expires

# Allow .htaccess overrides in document root
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Production PHP settings
RUN echo "upload_max_filesize = 15M\npost_max_size = 20M\nmemory_limit = 128M\ndisplay_errors = Off\nlog_errors = On" > /usr/local/etc/php/conf.d/production.ini

WORKDIR /var/www/html

# Copy all project files into web root
COPY . /var/www/html/

# Copy and setup entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Setup data and uploads directories with permissions
RUN mkdir -p /var/www/html/data /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/data /var/www/html/uploads

# Expose Render default ports
EXPOSE 80 10000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
