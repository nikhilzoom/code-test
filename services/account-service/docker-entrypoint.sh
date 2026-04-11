#!/bin/sh
set -e

echo "[account-service] Running Doctrine migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[account-service] Starting PHP-FPM..."
exec php-fpm
