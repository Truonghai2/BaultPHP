# --- Stage 1: Install dependencies with Composer ---
# Use the official Composer image.
# Name this stage "vendor" to be able to reference it later.
FROM composer:2 AS vendor

ARG APP_ENV=production
WORKDIR /app

# Copy only the files necessary for composer installation.
# This takes advantage of Docker's cache, only re-running when these files change.
COPY composer.json composer.lock ./ 

# Install system dependencies and the `sockets` extension required by `spiral/goridge`.
# The composer:2 image is based on Alpine Linux, so we use `apk`.
# The `linux-headers` package is equivalent to `linux-libc-dev` on Debian.
RUN apk update && apk add --no-cache linux-headers \
    && docker-php-ext-install sockets

RUN --mount=type=cache,target=/root/.composer/cache \
    export COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_PROCESS_TIMEOUT=600 && \
    composer config -g github-protocols https && \
    if [ "${APP_ENV}" = "production" ]; then \
        composer install --no-interaction --no-plugins --no-scripts --no-dev --optimize-autoloader; \
    else \
        composer install --no-interaction --no-plugins --no-scripts; \
    fi

# --- Stage 2: Build the main application image ---
# Start from the official Swoole image.
FROM phpswoole/swoole:5.1.3-php8.2
 
# Set environment variables for easy management
ENV APP_USER=appuser \
    APP_GROUP=appgroup \
    APP_UID=1000 \
    APP_GID=1000 \
    APP_HOME=/app
 
# Create a non-root user and group to enhance security
RUN groupadd --gid $APP_GID $APP_GROUP && \
    useradd --uid $APP_UID --gid $APP_GID --create-home --shell /bin/bash $APP_USER
 
# Install dependencies and extensions.
# Remove build-time packages in the same layer to reduce image size.
ARG DEBIAN_FRONTEND=noninteractive
RUN buildDeps=" \
        autoconf \
        automake \
        libtool \
        make \
        g++ \
        unzip \
    " && \
    apt-get update && apt-get install -y --no-install-recommends $buildDeps \
        python3 \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        linux-libc-dev \
        zlib1g-dev \
        gosu \
        supervisor \
        default-mysql-client \
        postgresql-client \
    && pecl install redis apcu \
    && docker-php-ext-enable redis apcu \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo_mysql pdo_pgsql pcntl bcmath sockets \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false $buildDeps && apt-get clean && rm -rf /var/lib/apt/lists/*
 
# [IMPORTANT] Use resources from the framework:
# Copy custom configuration files into the image.
# Grouping COPY commands into one layer makes the image slightly smaller.
COPY docker/php/conf.d/apcu.ini /usr/local/etc/php/conf.d/zz-apcu.ini
COPY docker/php/conf.d/custom.ini /usr/local/etc/php/conf.d/zz-bault-custom.ini
COPY docker/supervisor/app.conf /etc/supervisor/conf.d/app.conf
 
# Create an alias 'bault' for 'php /app/cli' for more convenience when working inside the container.
RUN echo "alias bault='php /app/cli'" >> /etc/bash.bashrc
 
WORKDIR $APP_HOME
 
# Copy the vendor directory installed from the "vendor" stage.
COPY --from=vendor --chown=$APP_USER:$APP_GROUP $APP_HOME/vendor $APP_HOME/vendor
 
# Copy the entrypoint script first to better leverage the cache.
# If only the application code changes, this layer will not need to be rebuilt.
COPY --chmod=0755 ./docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

# Copy the entire application source code.
# IMPORTANT: Make sure you have created a .dockerignore file.
COPY --chown=$APP_USER:$APP_GROUP . .
 
# Grant ownership of the application directory to the `appuser` user so that this user can
# create subdirectories within it (e.g., storage).
RUN chown $APP_USER:$APP_GROUP $APP_HOME

# Switch to the application user to create necessary directories with the correct ownership.
# This helps reduce the logic that needs to be handled in the entrypoint script.
USER $APP_USER
RUN mkdir -p storage/logs storage/framework/sessions storage/framework/views storage/framework/cache bootstrap/cache
 
# Switch back to root. The entrypoint will use `gosu` to switch to $APP_USER when running the application.
USER root
 
# [OPTIONAL] Add a Health Check so Docker can check the container's status.
# This healthcheck matches the configuration in docker-compose.yml.
HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=5 \
  CMD curl -f http://localhost:9501/ping || exit 1

# Set the entrypoint to run our script.
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
# The default CMD will be passed to the entrypoint.
# Run supervisord. It will automatically read the main configuration file /etc/supervisord.conf,
# which will include all files .conf in /etc/supervisor/conf.d/, 
# including our app.conf.
CMD ["/usr/bin/supervisord", "-n"]
