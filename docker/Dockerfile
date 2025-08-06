# STAGE 1: Builder - Môi trường để build ứng dụng
# Stage này chỉ dùng để cài đặt các dependency của Composer
FROM php:8.2-fpm AS builder

# Cài đặt các công cụ cần thiết cho Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Cài đặt Composer toàn cục
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Cài đặt các dependency của Composer
# Chỉ sao chép các file cần thiết để tận dụng cache của Docker
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-dev --no-scripts --prefer-dist

# Sao chép toàn bộ mã nguồn ứng dụng
COPY . .

# Chạy lại composer install để thực thi các script (nếu có) và tạo autoloader tối ưu
RUN composer install --no-interaction --no-dev && composer dump-autoload --optimize --no-dev --classmap-authoritative

# STAGE 2: Final Image - Image cuối cùng để chạy ứng dụng
FROM php:8.2-fpm-alpine AS final

# Tạo một user và group không phải root để chạy ứng dụng
# Điều này tăng cường bảo mật (nguyên tắc đặc quyền tối thiểu)
RUN addgroup -g 1000 -S bault && \
    adduser -u 1000 -S bault -G bault

# Cài đặt các gói build tạm thời, các extension PHP, và các thư viện runtime.
# Sau đó xóa các gói build để giữ image nhỏ gọn.
RUN apk update && apk add --no-cache --virtual .build-deps \
        build-base \
        autoconf \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        openssl-dev \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql zip intl sockets posix \
    && pecl install swoole-5.1.3 redis \
    && docker-php-ext-enable swoole redis \
    && apk del .build-deps

# Cài đặt các thư viện runtime cần thiết
RUN apk add --no-cache \
    gosu \
    icu-libs \
    libzip \
    libpng \
    libjpeg-turbo \
    freetype \
    openssl

# Sao chép và cấp quyền thực thi cho entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Mở port 9501 cho Swoole server
EXPOSE 9501
WORKDIR /var/www/html

# Sao chép mã nguồn và vendor từ stage builder
COPY --from=builder /var/www/html .

# Cấp quyền sở hữu cho user 'bault'
# Entrypoint script sẽ xử lý quyền chi tiết hơn cho các thư mục storage/bootstrap
RUN chown -R bault:bault /var/www/html

# Entrypoint sẽ chạy trước CMD để sửa lỗi quyền và các tác vụ khởi động khác
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Lệnh để khởi động Swoole server khi container chạy
CMD ["php", "cli", "serve"]
