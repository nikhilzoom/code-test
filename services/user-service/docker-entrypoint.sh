#!/bin/sh
set -e

echo "[user-service] Running Doctrine migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[user-service] Starting PHP-FPM..."
exec php-fpm
