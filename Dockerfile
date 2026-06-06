FROM serversideup/php:8.3-cli

USER root

# Install Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy dependency files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-dev --no-interaction --no-scripts

COPY package.json package-lock.json ./
RUN npm ci

# Copy the rest of the application
COPY . .

# Create .env and build frontend assets
RUN cp .env.example .env \
    && php artisan key:generate \
    && composer run-script post-autoload-dump \
    && npm run build

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# Railway injects DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD automatically.
CMD sh -c "php artisan migrate --force && php artisan config:cache && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"
