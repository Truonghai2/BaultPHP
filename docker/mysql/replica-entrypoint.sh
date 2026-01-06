#!/bin/bash
# docker/mysql/replica-entrypoint.sh


set -ex

cleanup() {
    echo "[Replica Entrypoint] Dọn dẹp do có lỗi..."
    if kill -0 "$pid" 2>/dev/null; then
        kill "$pid"
        wait "$pid"
    fi
}

trap cleanup EXIT INT TERM

echo "[Replica Entrypoint] Bắt đầu..."

if [ -f /etc/mysql/conf.d/replica.cnf ]; then
    echo "[Replica Entrypoint] Sửa quyền sở hữu cho file /etc/mysql/conf.d/replica.cnf..."
    chmod 644 /etc/mysql/conf.d/replica.cnf
    chown mysql:mysql /etc/mysql/conf.d/replica.cnf
else
    echo "[Replica Entrypoint] Cảnh báo: Không tìm thấy file replica.cnf."
fi

echo "[Replica Entrypoint] Dọn dẹp thư mục dữ liệu cũ của MySQL..."
rm -rf /var/lib/mysql/*

echo "[Replica Entrypoint] Khởi động server MySQL local trong nền..."
/usr/local/bin/docker-entrypoint.sh "$@" &
pid="$!"

echo "[Replica Entrypoint] Đang chờ server local khởi động..."
until mysql -h"localhost" -u"root" -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1"; do
    echo "[Replica Entrypoint] Server local chưa sẵn sàng, đang chờ..."
    sleep 2
done
echo "[Replica Entrypoint] Server local đã sẵn sàng."

PRIMARY_HOST="db"
echo "[Replica Entrypoint] Đang chờ server primary '${PRIMARY_HOST}'..."
until mysql -h"${PRIMARY_HOST}" -u"root" -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1"; do
    echo "[Replica Entrypoint] Server primary '${PRIMARY_HOST}' chưa sẵn sàng, đang chờ..."
    sleep 2
done
echo "[Replica Entrypoint] Server primary đã sẵn sàng."

echo "[Replica Entrypoint] Bắt đầu cấu hình replication..."

SQL_COMMAND=$(cat <<-EOF
    -- Dừng replica để thay đổi cấu hình.
    STOP REPLICA;
    -- Reset toàn bộ trạng thái replica để đảm bảo bắt đầu sạch.
    -- Quan trọng trong môi trường dev để tránh lỗi từ lần chạy trước.
    RESET REPLICA ALL;
    -- Cấu hình nguồn replication.
    CHANGE REPLICATION SOURCE TO
        SOURCE_HOST='${PRIMARY_HOST}',
        SOURCE_USER='${MYSQL_REPLICATION_USER}',
        SOURCE_PASSWORD='${MYSQL_REPLICATION_PASSWORD}',
        SOURCE_AUTO_POSITION=1;
    -- Bắt đầu replication.
    START REPLICA;
EOF
)

echo "[Replica Entrypoint] Đang thực thi các lệnh SQL sau:"
echo "${SQL_COMMAND}"

mysql -h"localhost" -u"root" -p"${MYSQL_ROOT_PASSWORD}" -e "${SQL_COMMAND}"

echo "[Replica Entrypoint] Cấu hình replication đã được áp dụng. Chờ 5 giây rồi kiểm tra trạng thái..."
sleep 5
mysql -h"localhost" -u"root" -p"${MYSQL_ROOT_PASSWORD}" -e "SHOW REPLICA STATUS\G"

echo "[Replica Entrypoint] Cấu hình hoàn tất. Bàn giao quyền kiểm soát cho server MySQL (PID: $pid)..."
trap - EXIT INT TERM
wait "$pid"