# syntax=docker/dockerfile:1
#
# Image de production de l'API UniConnect (SCRUM-30).
# Un seul conteneur : nginx + php-fpm + worker de queue + scheduler,
# orchestrés par Supervisor. Cible : Render (service web Docker).
#
# Note : la tâche demandait php:8.3-fpm, mais backend/composer.lock verrouille
# Symfony 8.1 qui exige PHP >= 8.4.1 (voir README et CI backend). On utilise donc
# PHP 8.4 pour que `composer install` et l'application fonctionnent.

FROM php:8.4-fpm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    APP_ENV=production

# Dépendances système :
# - nginx / supervisor : serveur web et superviseur de processus
# - libpq-dev          : en-têtes PostgreSQL pour compiler pdo_pgsql
# - unzip / curl       : outils requis par Composer et le healthcheck
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libpq-dev \
        unzip \
        curl \
        ca-certificates \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pcntl opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer officiel (même binaire que l'image composer:2)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# On copie le code puis on installe uniquement les dépendances de production.
COPY . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# Configuration nginx (on retire le site par défaut) et Supervisor.
RUN rm -f /etc/nginx/sites-enabled/default

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start.sh /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS http://127.0.0.1/api/health || exit 1

ENTRYPOINT ["/usr/local/bin/start.sh"]
