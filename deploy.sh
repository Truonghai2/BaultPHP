#!/bin/bash

# === BaultPHP Deployment Script ===
#
# Script này sẽ tự động hóa các bước để triển khai phiên bản mới của ứng dụng.
# Hướng dẫn sử dụng:
# 1. Đảm bảo script này có quyền thực thi: `chmod +x deploy.sh`
# 2. Chạy script từ thư mục gốc của dự án: `./deploy.sh`
#

# Dừng script ngay lập tức nếu có bất kỳ lệnh nào thất bại
set -e

echo "🚀 Bắt đầu quá trình triển khai..."

# 1. Bật chế độ bảo trì (Tùy chọn, nhưng khuyến khích)
# Điều này sẽ hiển thị một trang thông báo cho người dùng trong khi bạn cập nhật.
# Bạn sẽ cần tự implment logic này trong file public/index.php.
# echo "Bật chế độ bảo trì..."
# touch storage/framework/maintenance.flag

# 2. Lấy code mới nhất từ Git repository
echo "🔄 Kéo code mới nhất từ Git..."
git pull origin main # Hoặc `master`, `develop` tùy vào branch của bạn

# 3. Cài đặt các dependency của Composer
# --no-dev: Không cài các gói chỉ dành cho môi trường phát triển.
# --optimize-autoloader: Tối ưu hóa autoloader của Composer để tăng tốc.
echo "📦 Cài đặt các gói Composer..."
composer install --no-dev --optimize-autoloader

# 3.1 Đảm bảo các thư mục cần thiết tồn tại
echo "📁 Tạo các thư mục cần thiết..."
mkdir -p storage/logs

# 3.5 Đồng bộ các module từ filesystem vào CSDL
echo "Đồng bộ modules..."
php cli module:sync

# 4. Chạy database migrations
# Đảm bảo schema của CSDL luôn được cập nhật.
echo "🗄️ Chạy database migrations..."
php cli ddd:migrate # Thêm --force nếu lệnh của bạn có bước xác nhận

# 5. Xóa các file cache cũ
# Đây là bước quan trọng để đảm bảo không có cấu hình cũ nào được sử dụng.
echo "🧹 Xóa các file cache cũ..."
php cli config:clear
php cli route:clear   # Giả sử bạn có lệnh route:clear
php cli module:clear
rm -f storage/cache/cms_blocks.php # Xóa cache của các block trong CMS

# 6. Tạo các file cache mới đã được tối ưu hóa
# Đây là bước quan trọng nhất để tăng tốc ứng dụng trong môi trường production.
echo "⚡ Tạo cache mới cho cấu hình và routes..."
php cli config:cache
php cli module:cache
php cli route:cache

# 7. Đặt lại quyền cho các thư mục cần ghi
# Web server (ví dụ: www-data) cần có quyền ghi vào các thư mục này.
echo "🔒 Đặt lại quyền cho thư mục..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 8. Tắt chế độ bảo trì
# echo "Tắt chế độ bảo trì..."
# rm -f storage/framework/maintenance.flag

# 9. Khởi động lại các worker của Swoole (Graceful Reload)
# Lệnh này sẽ gửi tín hiệu SIGUSR1 đến master process,
# yêu cầu nó khởi động lại tất cả các worker một cách tuần tự
# mà không làm gián đoạn dịch vụ (zero-downtime).
echo "🔄 Gửi tín hiệu reload đến Swoole server..."
php cli server:reload

echo "✅ Quá trình triển khai hoàn tất!"