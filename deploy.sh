#!/bin/sh

# Skynet Fiber FieldOps - Coolify Deployment Script
# This script runs on container startup

set -e

echo "🚀 Starting Skynet Fiber FieldOps deployment..."

# 0. Fix directory structures
echo "🔐 Fixing directory structures..."
mkdir -p /app/storage/logs /app/storage/framework/views /app/storage/framework/cache /app/storage/framework/sessions /app/bootstrap/cache

# 1. Run migrations
echo "📦 Running database migrations..."
php artisan migrate --force --isolated

# 2. Create storage link (ignore if exists)
echo "🔗 Creating storage symlink..."
php artisan storage:link || true

# 3. Cache optimization
echo "⚡ Optimizing configuration cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components || true

echo "✅ Deployment scripting complete. Passing control to Nixpacks..."

# Final permission fix for any files created by artisan
chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache
