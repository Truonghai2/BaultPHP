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

# 4. Wait for the database to be ready before running migrations.
# This prevents race conditions during startup where the app container starts
# faster than the db container is ready to accept network connections.
echo "Waiting for database connection at ${DB_HOST}..."
# Use the environment variables passed from docker-compose.
# The 'until' loop will try to connect until it succeeds.
until mysqladmin ping -h"${DB_HOST}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent; do
    echo "Database is unavailable - sleeping"
    sleep 2
done
echo "Database is up - continuing..."


# 5. Run Database Migrations AS THE APPLICATION USER.
echo "Running database migrations as $APP_USER user..."
gosu $APP_USER php /app/cli ddd:migrate --force

# 6. Execute the main command passed from the Dockerfile (CMD).
# The `exec "$@"` command replaces the current shell process with the command
# passed as arguments to the script (the CMD from the Dockerfile). This is important because
# it allows the main application (e.g., supervisord) to become PID 1 inside the container,
# which means it can correctly receive signals like SIGTERM for graceful shutdown.
echo "Starting main process: $@"

exec "$@"
