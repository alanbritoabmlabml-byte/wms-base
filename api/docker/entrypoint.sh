#!/bin/sh
# Arranque en Render: puerto, clave, caché, migraciones y datos iniciales.
set -e
cd /var/www/html

export PORT="${PORT:-10000}"
echo "Listen ${PORT}" > /etc/apache2/ports.conf

# Render genera APP_KEY como base64 de 256 bits; Laravel espera el prefijo base64:.
if [ -n "$APP_KEY" ] && [ "${APP_KEY#base64:}" = "$APP_KEY" ]; then
  export APP_KEY="base64:${APP_KEY}"
fi
if [ -z "$APP_KEY" ]; then
  export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
  echo "AVISO: APP_KEY no definida; se generó una temporal (las sesiones se reinician en cada despliegue)."
fi

php artisan config:clear >/dev/null 2>&1 || true

echo "Esperando la base de datos..."
i=0
until php artisan migrate:status >/dev/null 2>&1 || [ "$i" -ge 30 ]; do i=$((i+1)); sleep 2; done

php artisan migrate --force

# Primera vez (tablas vacías): cargar los datos de Carmen WMS y los usuarios.
php artisan carmen:seed-if-empty

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache
exec "$@"
