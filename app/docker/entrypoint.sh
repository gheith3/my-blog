#!/bin/sh

# Laravel Docker Entrypoint Script v2
echo "🚀 Starting blog backend..."

# Create storage directories FIRST (required for artisan commands)
echo "📁 Setting up storage directories..."
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/app/public
mkdir -p /var/www/html/bootstrap/cache

# Set proper permissions
echo "🔒 Setting permissions..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Wait for database based on connection type
case "$DB_CONNECTION" in
    sqlite)
        echo "📦 Using SQLite database - no connection wait needed"
        if [ -n "$DB_DATABASE" ] && [ ! -f "$DB_DATABASE" ]; then
            echo "📝 Creating SQLite database file..."
            touch "$DB_DATABASE"
            chown www-data:www-data "$DB_DATABASE"
        fi
        ;;
    mysql|mariadb)
        if [ -n "$DB_HOST" ]; then
            echo "⏳ Waiting for MariaDB/MySQL connection..."
            until nc -z "$DB_HOST" "${DB_PORT:-3306}"; do
                echo "Database not ready - sleeping..."
                sleep 2
            done
            echo "✅ MariaDB/MySQL connection established"
        fi
        ;;
    pgsql)
        if [ -n "$DB_HOST" ]; then
            echo "⏳ Waiting for PostgreSQL connection..."
            until nc -z "$DB_HOST" "${DB_PORT:-5432}"; do
                echo "Database not ready - sleeping..."
                sleep 2
            done
            echo "✅ PostgreSQL connection established"
        fi
        ;;
    *)
        echo "⚠️ Unknown database connection type: $DB_CONNECTION"
        ;;
esac

# Create storage symlink for public files
echo "🔗 Creating storage symlink..."
php artisan storage:link --force 2>/dev/null || true

# Passport's RSA keypair signs the MCP OAuth/API tokens (the auth:api guard).
# Create them only when missing: passport:keys exits non-zero if they already
# exist, so its exit code can't be used to detect failure on every boot. The
# keys persist in the storage volume. Without them every OAuth/API-token
# request fails, so stop the boot if creation fails.
if [ -f storage/oauth-private.key ] && [ -f storage/oauth-public.key ]; then
    echo "🔑 Passport encryption keys already exist."
else
    echo "🔑 Creating Passport encryption keys..."
    if ! php artisan passport:keys; then
        echo "❌ Passport keys could not be created. Aborting."
        exit 1
    fi
fi
# passport:keys sets 600/660 only at creation. The blanket "chmod -R 775"
# above loosens them again on every boot, and league/oauth2-server refuses
# keys with loose permissions, so re-tighten them each time.
if [ -f storage/oauth-private.key ]; then
    chmod 600 storage/oauth-private.key
    chmod 660 storage/oauth-public.key
    chown www-data:www-data storage/oauth-private.key storage/oauth-public.key
fi

# Run database migrations if requested. Seeding is never run here: the
# seeders create a known default admin login, so they must not run on a live
# server. Run them by hand only in local development.
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "🗄️ Running database migrations..."
    if ! php artisan migrate --force --no-interaction; then
        echo "❌ Migrations failed. Aborting so the app does not run against a stale schema."
        exit 1
    fi

    # Backfill excerpts and baseline revisions for posts that predate Blog MCP
    # v2. The command is idempotent (posts with an excerpt and any revision are
    # skipped), so it is safe to run on every boot. A failure here is not worth
    # blocking the boot: v2 works without the backfill, it just means old posts
    # have no restore point until their first v2 save.
    echo "📝 Backfilling Blog MCP v2 data for existing posts..."
    php artisan blog:backfill-v2 --no-interaction || echo "⚠️ v2 backfill failed - continuing boot without it."
fi

# Clear and cache configuration (only if not already cached)
echo "⚡ Optimizing application..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Cache for production
php artisan livewire:publish --assets
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache

# The artisan commands above run as root and can leave root-owned files in
# storage and bootstrap/cache, which PHP-FPM (www-data) then cannot rewrite.
# chown leaves permission bits alone, so the Passport key modes stay 600/660.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

echo "✅ Laravel initialization complete!"

# Ensure Supervisor log directory exists
echo "🎯 Ensuring supervisor log directory exists..."
mkdir -p /var/log/supervisor
chown -R www-data:www-data /var/log/supervisor

# Ensure Nginx log directory exists
echo "📁 Ensuring nginx log directory exists..."
mkdir -p /var/log/nginx
chown -R www-data:www-data /var/log/nginx

# Start supervisor to manage all processes
echo "🎯 Starting services with supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf