# =============================================================================
# Base stage: PHP 8.4 CLI Alpine with required extensions
# =============================================================================
FROM php:8.4-cli-alpine AS base

RUN docker-php-ext-install opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# =============================================================================
# Dev stage: full dev dependencies, xdebug available
# =============================================================================
FROM base AS dev

RUN apk add --no-cache linux-headers $PHPIZE_DEPS \
    && pecl install xdebug && docker-php-ext-enable xdebug

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --no-scripts

COPY . .
RUN composer run-script post-install-cmd --no-interaction || true

CMD ["php", "bin/console"]

# =============================================================================
# Production dependencies: install in a throwaway layer with Composer
# =============================================================================
FROM base AS build

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --optimize-autoloader

COPY . .
RUN composer run-script post-install-cmd --no-interaction || true \
    && composer dump-autoload --optimize --classmap-authoritative \
    && rm -rf /usr/bin/composer

# =============================================================================
# Production stage: minimal runtime image
# =============================================================================
FROM php:8.4-cli-alpine AS production

RUN docker-php-ext-install opcache

WORKDIR /app
ENV APP_ENV=prod

COPY --from=build /app /app

CMD ["php", "bin/console"]
