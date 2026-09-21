#!/bin/sh
set -e

# Laravel writable paths. storage/logs must exist before supervisord starts:
# supervisord validates every stdout_logfile directory up front and aborts if
# one is missing, which happens on a fresh clone before the app is installed.
mkdir -p /var/www/storage/logs \
         /var/www/storage/framework/cache \
         /var/www/storage/framework/sessions \
         /var/www/storage/framework/views \
         /var/www/bootstrap/cache
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Create PHPMyAdmin sessions folder if needed
mkdir -p /sessions
chmod 777 /sessions

# Start Supervisor to run queue:work + php-fpm
exec /usr/bin/supervisord -n -c /etc/supervisord.conf
