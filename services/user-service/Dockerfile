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

# Install dependencies first (cached layer)
COPY services/user-service/composer.json ./
RUN composer update --no-scripts --no-interaction

# Copy application source (excluding vendor via .dockerignore)
COPY services/user-service/src ./src
COPY services/user-service/config ./config
COPY services/user-service/migrations ./migrations
COPY services/user-service/public ./public
COPY services/user-service/bin ./bin
COPY services/user-service/.env ./.env

# Regenerate autoload after all source is in place
RUN composer dump-autoload --optimize --no-dev

# Configure PHP-FPM to listen on all interfaces, keep daemonize=no so container stays alive
RUN printf '[global]\ndaemonize = no\n\n[www]\nlisten = 0.0.0.0:9000\n' > /usr/local/etc/php-fpm.d/zz-docker.conf

RUN php bin/console cache:clear --env=prod --no-debug || true

COPY services/user-service/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9001
ENTRYPOINT ["docker-entrypoint.sh"]
