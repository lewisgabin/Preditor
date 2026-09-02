FROM composer:2.10 AS composer

FROM php:8.4-fpm-alpine AS build
RUN apk add --no-cache icu-dev libzip-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) bcmath intl opcache pcntl pdo_mysql zip \
    && apk del $PHPIZE_DEPS linux-headers
WORKDIR /var/www/html
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist
COPY apps/api/ ./
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && php artisan package:discover --ansi

FROM php:8.4-fpm-alpine AS runtime
RUN apk add --no-cache icu-libs libzip \
    && addgroup -g 1000 quiniela \
    && adduser -D -u 1000 -G quiniela quiniela
WORKDIR /var/www/html
COPY --from=build /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=build /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d
COPY --from=build --chown=quiniela:quiniela /var/www/html /var/www/html
COPY --chown=quiniela:quiniela infra/docker/api/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && sed -i 's/^user = www-data/user = quiniela/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^group = www-data/group = quiniela/' /usr/local/etc/php-fpm.d/www.conf
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr TZ=America/Santo_Domingo
USER quiniela
EXPOSE 9000
HEALTHCHECK --interval=10s --timeout=3s --retries=6 CMD php -r '$s=@fsockopen("127.0.0.1",9000); exit($s ? 0 : 1);'
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]

FROM nginxinc/nginx-unprivileged:1.28-alpine AS nginx
COPY infra/nginx/api.conf /etc/nginx/conf.d/default.conf
COPY --from=build /var/www/html/public /var/www/html/public
EXPOSE 8080
HEALTHCHECK --interval=10s --timeout=3s --retries=12 CMD wget -q --spider http://127.0.0.1:8080/api/health || exit 1
