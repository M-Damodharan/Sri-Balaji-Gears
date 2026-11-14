#!/bin/sh
set -e

# Render binds to $PORT (usually 10000 or 80)
PORT=${PORT:-80}

# Dynamically bind Apache to Render's allocated PORT
sed -i "s/Listen [0-9]*/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Ensure runtime data and uploads folders exist with write permissions for www-data
mkdir -p /var/www/html/data /var/www/html/uploads
chown -R www-data:www-data /var/www/html/data /var/www/html/uploads
chmod -R 775 /var/www/html/data /var/www/html/uploads

# Start Apache
exec apache2-foreground
