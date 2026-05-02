#!/bin/sh
set -e

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache

until php -r '
try {
    new PDO(
        sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: 5432, getenv("DB_DATABASE")),
        getenv("DB_USERNAME"),
        getenv("DB_PASSWORD"),
    );
} catch (Throwable $exception) {
    exit(1);
}
' >/dev/null 2>&1; do
    echo "Waiting for database..."
    sleep 1
done

php artisan view:clear
php artisan migrate --force
php artisan db:seed --force

php artisan serve --host=0.0.0.0 --port=8000
