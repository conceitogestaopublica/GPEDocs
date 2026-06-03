#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "ERRO: APP_KEY não está definida. Defina no .env antes de iniciar." >&2
    exit 1
fi

# Garante a estrutura de storage (volume nomeado é montado vazio na 1a execução)
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Executa migrations (idempotente — sem efeito se não há pendências)
#php artisan migrate --force

# Em dev (APP_ENV != production), LIMPA caches em vez de criá-los — assim
# alterações em config/routes/views refletem sem precisar reiniciar o container.
# Em produção, gera os caches "compilados" para performance máxima.
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
fi

# Garante que o link simbólico de storage existe
php artisan storage:link --force 2>/dev/null || true

exec "$@"
