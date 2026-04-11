#!/bin/sh
set -e

echo "[transaction-service] Running Doctrine migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[transaction-service] Starting PHP-FPM..."
exec php-fpm
