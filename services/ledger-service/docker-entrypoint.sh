#!/bin/sh
set -e

echo "[ledger-service] Running Doctrine migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[ledger-service] Starting PHP-FPM..."
exec php-fpm
