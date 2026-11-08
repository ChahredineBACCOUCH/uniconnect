#!/bin/sh
#
# Point d'entrée du conteneur de production (SCRUM-30).
# Applique les migrations, met la configuration en cache, puis délègue
# le lancement de php-fpm, nginx, du worker et du scheduler à Supervisor.
set -e

cd /var/www/html

# Les dossiers d'écriture doivent appartenir à www-data (php-fpm et workers).
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan migrate --force \
    && php artisan config:cache \
    && exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
