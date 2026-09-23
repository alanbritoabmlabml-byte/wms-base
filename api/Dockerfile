# Carmen WMS — imagen para Render (o cualquier host con Docker).
# PHP 8.4 + Apache sirviendo public/, extensiones para PostgreSQL y MySQL.
FROM php:8.4-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev libzip-dev libicu-dev unzip git \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql pdo_mysql zip intl opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-carmen.ini

WORKDIR /var/www/html

# Dependencias primero (capa cacheable)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
 && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod +x docker/entrypoint.sh

EXPOSE 10000
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["apache2-foreground"]
