FROM php:8.3-cli-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
        git \
        zip \
        unzip \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        postgresql-dev \
        nodejs \
        npm \
        sqlite \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        gd \
        pdo \
        pdo_sqlite \
        pdo_pgsql \
        bcmath \
        zip \
        opcache \
        mbstring \
        dom \
        fileinfo \
        tokenizer \
        ctype \
        pcntl \
        exif

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy dependency files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-dev --no-interaction --no-scripts

COPY package.json package-lock.json ./
RUN npm ci

# Copy the rest of the application
COPY . .

# Create .env from example so artisan commands work during build
RUN cp .env.example .env \
    && php artisan key:generate \
    && composer run-script post-autoload-dump \
    && npm run build

# Set storage/cache permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

# At runtime: ensure SQLite file exists, run migrations, then start the server.
# Railway env vars (APP_KEY, APP_URL, etc.) override .env values automatically.
CMD sh -c "touch database/database.sqlite && php artisan migrate --force && php artisan config:cache && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"
