#!/bin/bash

# Script để đồng bộ dữ liệu từ CSDL chính (db) sang CSDL đọc (db_read).
# Cảnh báo: Thao tác này sẽ GHI ĐÈ toàn bộ dữ liệu trên `db_read`.

set -e # Dừng script ngay khi có lỗi

# Lấy đường dẫn đến thư mục chứa script này
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# Đọc các biến môi trường từ file .env để lấy thông tin CSDL
# Giả sử file .env nằm cùng cấp với script này
if [ -f "$DIR/.env" ]; then
  export $(grep -v '^#' "$DIR/.env" | xargs)
fi

# Sử dụng biến từ .env hoặc giá trị mặc định
DB_PASSWORD=${DB_ROOT_PASSWORD:-secret}
DB_DATABASE=${DB_DATABASE:-bault}

echo "Dumping database from 'bault_db'..."

# Dump CSDL từ container 'db' và pipe trực tiếp vào container 'db_read'
# Sử dụng --single-transaction để không khóa bảng (chỉ hoạt động với InnoDB).
# Chỉ dump CSDL của ứng dụng (--databases), không dùng --all-databases để tránh các vấn đề với replication.
docker exec bault_db mysqldump --databases "$DB_DATABASE" --single-transaction -u root -p"$DB_PASSWORD" | docker exec -i bault_db_read mysql -u root -p"$DB_PASSWORD"

echo "✅ Replica 'bault_db_read' has been successfully synchronized with the primary database."
echo
echo "⚠️  NOTE: This operation breaks replication. You must restart the 'db_read' container to re-establish it:"
echo "   docker-compose restart db_read"
