# =============================================================================
# Base stage: PHP 8.4 CLI with required extensions
# =============================================================================
FROM php:8.4-cli AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install \
        intl \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# =============================================================================
# Dev stage: full dev dependencies, xdebug available
# =============================================================================
FROM base AS dev

RUN pecl install xdebug && docker-php-ext-enable xdebug

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --no-scripts

COPY . .
RUN composer run-script post-install-cmd --no-interaction || true

CMD ["php", "bin/console"]

# =============================================================================
# Production stage: optimised autoloader, no dev dependencies
# =============================================================================
FROM base AS production

ENV APP_ENV=prod

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --optimize-autoloader

COPY . .
RUN composer run-script post-install-cmd --no-interaction || true \
    && composer dump-autoload --optimize --classmap-authoritative

CMD ["php", "bin/console"]
