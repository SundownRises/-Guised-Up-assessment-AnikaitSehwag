FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-install pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .
RUN composer install --no-dev --optimize-autoloader

EXPOSE 8000

CMD php artisan config:clear && php artisan migrate --force && (php artisan db:seed --force || true) && php artisan serve --host=0.0.0.0 --port=8000
