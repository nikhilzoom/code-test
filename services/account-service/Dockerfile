FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install pdo pdo_mysql intl opcache zip sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY shared/php-common /shared/php-common

COPY services/account-service/composer.json ./
RUN composer update --no-scripts --no-interaction

COPY services/account-service/src ./src
COPY services/account-service/config ./config
COPY services/account-service/migrations ./migrations
COPY services/account-service/public ./public
COPY services/account-service/bin ./bin
COPY services/account-service/.env ./.env

RUN composer dump-autoload --optimize --no-dev

RUN printf '[global]\ndaemonize = no\n\n[www]\nlisten = 0.0.0.0:9000\n' > /usr/local/etc/php-fpm.d/zz-docker.conf

RUN php bin/console cache:clear --env=prod --no-debug || true

COPY services/account-service/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9002
ENTRYPOINT ["docker-entrypoint.sh"]
