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

# Remise à zéro COMPLÈTE (irréversible) : efface toutes les tables et les fichiers
# envoyés, puis ne recharge que les données de démo. Valeur exacte exigée pour éviter
# un déclenchement accidentel. Retirer RESET_DEMO aussitôt après le redéploiement.
# Elle passe AVANT « migrate » : migrate:fresh recrée tout le schéma, et une base
# laissée incohérente par un reset interrompu (table présente mais migration non
# enregistrée) ferait échouer « migrate » et bloquerait le démarrage.
if [ "${RESET_DEMO:-}" = "EFFACER-TOUT" ]; then
    php artisan demo:reinitialiser --confirmer=EFFACER-TOUT
else
    php artisan migrate --force
fi

# Données de démonstration (SCRUM-89, SCRUM-188) : le Shell Render n'existe pas en
# offre gratuite. Mettre SEED_DEMO=true dans les variables Render, redéployer, puis
# retirer la variable. DemoProductionSeeder (comptes de démo + cours, PDF indexés,
# discussion, annonces…) est idempotent : le relancer ne crée pas de doublons.
if [ "${SEED_DEMO:-false}" = "true" ]; then
    php artisan db:seed --class=DemoProductionSeeder --force
fi

php artisan config:cache \
    && exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
