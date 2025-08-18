# --- Stage 1: Cài đặt dependencies với Composer ---
# Sử dụng image chính thức của Composer.
# Đặt tên stage này là "vendor" để có thể tham chiếu sau này.
FROM composer:2 AS vendor

ARG APP_ENV=production
WORKDIR /app

# Sao chép chỉ các file cần thiết cho việc cài đặt composer.
# Điều này tận dụng cache của Docker, chỉ chạy lại khi các file này thay đổi.
COPY composer.json composer.lock ./

# Cài đặt dependencies hệ thống và extension `sockets` mà `spiral/goridge` yêu cầu.
# Image composer:2 dựa trên Alpine Linux, vì vậy chúng ta dùng `apk`.
# Gói `linux-headers` là tương đương với `linux-libc-dev` trên Debian.
RUN apk update && apk add --no-cache linux-headers \
    && docker-php-ext-install sockets

# Sử dụng --mount=type=cache để tăng tốc độ cài đặt ở các lần build sau.
RUN --mount=type=cache,target=/root/.composer/cache \
    if [ "${APP_ENV}" = "production" ]; then \
        composer install --no-interaction --no-plugins --no-scripts --no-dev --optimize-autoloader; \
    else \
        composer install --no-interaction --no-plugins --no-scripts; \
    fi

# --- Stage 2: Build image ứng dụng chính ---
# Bắt đầu từ image Swoole chính thức.
FROM phpswoole/swoole:5.1.3-php8.2
 
# Thiết lập các biến môi trường để dễ quản lý
ENV APP_USER=appuser \
    APP_GROUP=appgroup \
    APP_UID=1000 \
    APP_GID=1000 \
    APP_HOME=/app
 
# Tạo user và group không phải root để tăng cường bảo mật
RUN groupadd --gid $APP_GID $APP_GROUP && \
    useradd --uid $APP_UID --gid $APP_GID --create-home --shell /bin/bash $APP_USER
 
# Cài đặt dependencies và extensions.
# Gỡ bỏ các gói build-time trong cùng một layer để giảm kích thước image.
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
    && pecl install redis apcu \
    && docker-php-ext-enable redis apcu \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo_mysql pcntl bcmath sockets \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false $buildDeps && apt-get clean && rm -rf /var/lib/apt/lists/*
 
# [QUAN TRỌNG] Sử dụng tài nguyên từ framework:
# Sao chép các file cấu hình tùy chỉnh vào image.
# Việc gộp các lệnh COPY vào một layer giúp image gọn hơn một chút.
COPY docker/php/conf.d/apcu.ini /usr/local/etc/php/conf.d/zz-apcu.ini
COPY docker/php/conf.d/custom.ini /usr/local/etc/php/conf.d/zz-bault-custom.ini
COPY docker/supervisor/app.conf /etc/supervisor/conf.d/app.conf
 
# Tạo alias 'bault' cho 'php /app/cli' để tiện lợi hơn khi làm việc trong container.
RUN echo "alias bault='php /app/cli'" >> /etc/bash.bashrc
 
WORKDIR $APP_HOME
 
# Sao chép thư mục vendor đã được cài đặt từ stage "vendor".
COPY --from=vendor --chown=$APP_USER:$APP_GROUP $APP_HOME/vendor $APP_HOME/vendor
 
# Sao chép entrypoint script trước để tận dụng cache tốt hơn.
# Nếu chỉ thay đổi code ứng dụng, layer này sẽ không cần build lại.
COPY --chmod=0755 ./docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

# Sao chép toàn bộ mã nguồn ứng dụng.
# QUAN TRỌNG: Hãy đảm bảo bạn đã tạo file .dockerignore.
COPY --chown=$APP_USER:$APP_GROUP . .
 
# Cấp quyền sở hữu thư mục ứng dụng cho user `appuser` để user này có thể
# tạo các thư mục con bên trong nó (ví dụ: storage).
RUN chown $APP_USER:$APP_GROUP $APP_HOME

# Chuyển sang user ứng dụng để tạo các thư mục cần thiết với quyền sở hữu đúng.
# Điều này giúp giảm bớt logic cần xử lý trong entrypoint script.
USER $APP_USER
RUN mkdir -p storage/logs storage/framework/sessions storage/framework/views storage/framework/cache bootstrap/cache
 
# Chuyển về lại root. Entrypoint sẽ sử dụng `gosu` để chuyển về $APP_USER khi chạy ứng dụng.
USER root
 
# [TÙY CHỌN] Thêm Health Check để Docker có thể kiểm tra tình trạng của container.
# Healthcheck này khớp với cấu hình trong docker-compose.yml.
HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=5 \
  CMD curl -f http://localhost:9501/ping || exit 1

# Thiết lập entrypoint để chạy script của chúng ta.
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
# CMD mặc định sẽ được truyền vào entrypoint.
# Chạy supervisord. Nó sẽ tự động đọc file cấu hình chính /etc/supervisord.conf,
# file này sẽ bao gồm tất cả các file .conf trong /etc/supervisor/conf.d/,
# bao gồm cả app.conf của chúng ta.
CMD ["/usr/bin/supervisord", "-n"]
