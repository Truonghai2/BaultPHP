#!/bin/bash

# Dừng script ngay khi có lỗi và in ra lệnh đang chạy để debug dễ dàng hơn.
set -ex

echo "[Primary Entrypoint] Bắt đầu..."

# File config được mount từ host có thể thuộc sở hữu của 'root'.
# Container MySQL chạy với user 'mysql' và có thể không đọc được file này.
# Lệnh `chown` sẽ sửa lỗi quyền truy cập.
if [ -f /etc/mysql/conf.d/primary.cnf ]; then
    echo "[Primary Entrypoint] Sửa quyền cho file /etc/mysql/conf.d/primary.cnf..."
    chown mysql:mysql /etc/mysql/conf.d/primary.cnf
else
    echo "[Primary Entrypoint] Cảnh báo: Không tìm thấy file primary.cnf."
fi

echo "[Primary Entrypoint] Bàn giao cho entrypoint gốc của MySQL..."

# Phần quan trọng nhất:
# Lệnh 'exec' thay thế tiến trình shell hiện tại bằng entrypoint gốc của image.
# Điều này đảm bảo server mysql (mysqld) trở thành tiến trình chính của container,
# giữ cho container tiếp tục chạy.
# "$@" sẽ truyền tất cả các tham số ban đầu (ví dụ: 'mysqld') cho entrypoint gốc.
exec /usr/local/bin/docker-entrypoint.sh "$@"