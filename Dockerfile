# syntax=docker/dockerfile:1

# --- Etapa 1: build de assets de frontend (Vite) ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci || npm install
COPY . .
RUN npm run build

# --- Etapa 2: runtime PHP con FrankenPHP ---
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

# Configuración del servidor y arranque
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
