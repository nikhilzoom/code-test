FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install pdo pdo_mysql intl opcache zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY shared/php-common /shared/php-common

COPY services/ledger-service/composer.json ./
RUN composer update --no-scripts --no-interaction

COPY services/ledger-service/src ./src
COPY services/ledger-service/config ./config
COPY services/ledger-service/migrations ./migrations
COPY services/ledger-service/public ./public
COPY services/ledger-service/bin ./bin
COPY services/ledger-service/.env ./.env

RUN composer dump-autoload --optimize --no-dev

RUN printf '[global]\ndaemonize = no\n\n[www]\nlisten = 0.0.0.0:9000\n' > /usr/local/etc/php-fpm.d/zz-docker.conf

RUN php bin/console cache:clear --env=prod --no-debug || true

COPY services/ledger-service/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9004
ENTRYPOINT ["docker-entrypoint.sh"]
