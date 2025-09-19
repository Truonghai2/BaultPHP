#!/bin/sh
#
# This script is executed when the container starts.
# It's responsible for setting up the environment before the main application process runs.

# Exit immediately if a command exits with a non-zero status.
set -e

# 1. Create necessary directories as root.
# This is safe because we will immediately fix their ownership.
echo "Ensuring storage directories exist..."
mkdir -p /app/storage/framework/sessions /app/storage/framework/views /app/storage/framework/cache /app/storage/logs
mkdir -p /app/bootstrap/cache

# 2. Set initial ownership.
# This ensures the parent directories are writable by www-data BEFORE any
# application logic (which might create files) is run. The user is defined by $APP_USER from the Dockerfile.
echo "Setting initial ownership for storage and cache..."
chown -R $APP_USER:$APP_GROUP /app/storage /app/bootstrap/cache

# 3. Run setup commands AS THE APPLICATION USER.
# This is the key to solving the permission issue. We use `su-exec` (a lightweight
# alternative to `su` or `sudo`) to execute the commands as the application user.
# This ensures any files created (logs, cache) are owned by the correct user from the start.
echo "Running application setup as $APP_USER user..."
if [ "${APP_ENV}" = "production" ]; then
    echo "Running in production mode. Caching configuration and routes..."
    gosu $APP_USER php /app/cli config:cache
    gosu $APP_USER php /app/cli route:cache
else
    echo "Running in development mode. Clearing caches..."
    gosu $APP_USER php /app/cli config:clear
    gosu $APP_USER php /app/cli route:clear
    gosu $APP_USER php /app/cli view:clear
fi

# 4. Wait for the database to be ready.
# Use DB_HOST and DB_PORT from the environment to ensure this script
# checks the same database the application will connect to.
if [ "$DB_CONNECTION" = "pgsql" ]; then
    echo "Waiting for PostgreSQL database connection at ${DB_HOST}:${DB_PORT}..."
    
    # Export password for pg_isready to use
    export PGPASSWORD="${DB_PASSWORD}"
    
    until pg_isready -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" -q; do
        echo "PostgreSQL is unavailable - sleeping"
        sleep 2
    done
    
    unset PGPASSWORD
    echo "PostgreSQL is up - continuing..."
elif [ "$DB_CONNECTION" = "mysql" ]; then
    echo "Waiting for MySQL database connection at ${DB_HOST}:${DB_PORT}..."

    until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent; do
        echo "MySQL is unavailable - sleeping"
        sleep 2
    done
    echo "MySQL is up - continuing..."
else
    echo "Unsupported DB_CONNECTION: ${DB_CONNECTION}. Skipping database wait."
fi

# 5. Run Database Migrations AS THE APPLICATION USER.
echo "Running database migrations as $APP_USER user..."
gosu $APP_USER php /app/cli ddd:migrate --force

if [ "${RUN_SEEDERS}" = "true" ]; then
    echo "RUN_SEEDERS is true. Running database seeder..."
    gosu $APP_USER php /app/cli db:seed
fi

echo "Starting main process: $@"

exec "$@"
