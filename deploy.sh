#!/bin/sh

# Skynet Fiber FieldOps - Coolify Deployment Script
# This script runs on container startup

set -e

echo "🚀 Starting Skynet Fiber FieldOps deployment..."

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
