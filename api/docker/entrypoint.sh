#!/bin/sh
set -e

# Runtime caches must be built after the container's environment variables are
# available. Building them at image-build time would freeze deployment values
# (APP_KEY, DB_*, SENTRY_LARAVEL_DSN, ...) into the image.
php artisan config:cache

exec "$@"