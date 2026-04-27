# Skynet Fiber FieldOps

Multi-project Laravel + Filament app for ODC/ODP field documentation, approval workflow, official asset records, dashboard metrics, and CSV exports.

## Local setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Admin seed user:

```txt
admin@skynet.local / password
```

Technician seed user:

```txt
tech@skynet.local / password
```

## Docker

```bash
docker compose up --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Open:

```txt
http://localhost:8000/admin
```

## Notes

This app separates technician submissions from official assets. A submission only becomes official ODC/ODP data after an admin approves it.
