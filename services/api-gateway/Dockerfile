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

COPY services/api-gateway/composer.json ./
RUN composer update --no-scripts --no-interaction

COPY services/api-gateway/src ./src
COPY services/api-gateway/config ./config
COPY services/api-gateway/public ./public
COPY services/api-gateway/bin ./bin
COPY services/api-gateway/.env ./.env

RUN composer dump-autoload --optimize --no-dev

RUN printf '[global]\ndaemonize = no\n\n[www]\nlisten = 0.0.0.0:9000\n' > /usr/local/etc/php-fpm.d/zz-docker.conf

RUN php bin/console cache:clear --env=prod --no-debug || true

EXPOSE 9000
CMD ["php-fpm"]
