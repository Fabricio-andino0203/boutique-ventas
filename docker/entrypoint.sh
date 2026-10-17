#!/bin/sh
set -e

# Cachés de producción (se regeneran en cada arranque con el entorno de Railway).
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Aplica migraciones pendientes. Es un no-op si la base (Supabase) ya está al día.
php artisan migrate --force || echo "migrate: omitido o sin cambios"

# Enlace de almacenamiento público (para logos/imágenes).
php artisan storage:link || true

# Arranca FrankenPHP sirviendo la aplicación.
exec frankenphp run --config /etc/caddy/Caddyfile
