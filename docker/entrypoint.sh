#!/bin/sh
set -e

# Đường dẫn đến file PID của Swoole
PID_FILE="/var/www/html/storage/logs/swoole.pid"

# 1. Dọn dẹp file PID cũ nếu có
# Điều này ngăn lỗi "server is running" khi container khởi động lại không sạch
if [ -f "$PID_FILE" ]; then
    echo "Removing stale PID file: $PID_FILE"
    rm -f "$PID_FILE"
fi

# 2. Sửa lỗi quyền cho các thư mục được mount từ host
# Lệnh này chạy với quyền root trước khi chuyển sang user www-data
echo "Fixing storage & cache permissions..."

# Đảm bảo thư mục cache tồn tại trước khi thay đổi quyền sở hữu
mkdir -p /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 3. Thực thi câu lệnh chính (CMD) của container với user www-data
# gosu là một công cụ nhẹ và an toàn để chuyển đổi user
echo "Executing command as www-data user: $@"
exec gosu www-data "$@"
