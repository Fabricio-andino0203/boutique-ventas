# syntax=docker/dockerfile:1

# --- Etapa 1: dependencias PHP (vendor) con Composer ---
# El tema de Filament importa CSS que vive en vendor/; Vite lo necesita para
# resolver esos @import durante el build de assets.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --ignore-platform-reqs

# --- Etapa 2: build de assets de frontend (Vite) ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci || npm install
COPY . .
# vendor/ (solo para que el tema de Filament resuelva sus @import de CSS).
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# --- Etapa 3: runtime PHP con FrankenPHP ---
FROM dunglas/frankenphp:1-php8.4 AS app

# Extensiones PHP necesarias (PostgreSQL/Supabase, etc.)
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    gd \
    bcmath \
    opcache \
    pcntl

WORKDIR /app

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dependencias PHP (solo producción), en capa cacheable
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --optimize-autoloader

# Código de la aplicación
COPY . .

# Assets compilados desde la etapa de build
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev

# Permisos de escritura para Laravel
RUN chmod -R ug+rw storage bootstrap/cache

# Afinamiento de PHP (OPcache / realpath cache)
COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

# Configuración del servidor y arranque
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
