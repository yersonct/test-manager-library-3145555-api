FROM composer:2 AS dependencies
WORKDIR /app
COPY composer.json ./
COPY . .
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --ignore-platform-req=ext-pdo_pgsql

FROM php:8.3-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpq-dev \
    && docker-php-ext-install mbstring pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=dependencies /app /app
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/covers \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
