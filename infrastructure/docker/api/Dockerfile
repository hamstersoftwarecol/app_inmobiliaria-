# syntax=docker/dockerfile:1.7
# Hitlease API — PHP-FPM image (also used for queue worker and scheduler).

FROM composer:2.8 AS vendor
WORKDIR /app
COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache icu-libs libpq libzip fcgi \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libpq-dev libzip-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql pgsql zip opcache pcntl bcmath \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps \
 && rm -rf /tmp/pear

COPY infrastructure/docker/api/php.ini /usr/local/etc/php/conf.d/zz-hitlease.ini
COPY infrastructure/docker/api/www.conf /usr/local/etc/php-fpm.d/zz-hitlease.conf

WORKDIR /var/www/html
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY apps/api/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
 && rm -f .env \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY infrastructure/docker/api/entrypoint.sh /usr/local/bin/hitlease-entrypoint
RUN chmod +x /usr/local/bin/hitlease-entrypoint

USER www-data
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1
ENTRYPOINT ["hitlease-entrypoint"]
CMD ["php-fpm"]
