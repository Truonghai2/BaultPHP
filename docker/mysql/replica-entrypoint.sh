#!/bin/bash
# docker/mysql/replica-entrypoint.sh


# Dừng script ngay khi có lỗi và in ra lệnh gây lỗi để debug.
set -ex

# --- Hàm dọn dẹp ---
# Đảm bảo tiến trình mysqld nền được tắt nếu script gặp lỗi giữa chừng.
cleanup() {
    echo "[Replica Entrypoint] Dọn dẹp do có lỗi..."
    if kill -0 "$pid" 2>/dev/null; then
        kill "$pid"
        wait "$pid"
    fi
}

# Đăng ký hàm cleanup để chạy khi script thoát (EXIT) hoặc bị ngắt (INT, TERM).
trap cleanup EXIT INT TERM

echo "[Replica Entrypoint] Bắt đầu..."

# MySQL 8+ sẽ bỏ qua các file config có quyền 'world-writable'.
# Khi mount từ host Windows, file có thể nhận quyền 777.
# Lệnh này đảm bảo file config có quyền an toàn trước khi MySQL khởi động.
if [ -f /etc/mysql/conf.d/replica.cnf ]; then
    echo "[Replica Entrypoint] Sửa quyền sở hữu cho file /etc/mysql/conf.d/replica.cnf..."
    # Giống như primary, chúng ta cần đảm bảo user 'mysql' sở hữu file config.
    chown mysql:mysql /etc/mysql/conf.d/replica.cnf
else
    echo "[Replica Entrypoint] Cảnh báo: Không tìm thấy file replica.cnf."
fi

# 1. Khởi động server MySQL local trong nền.
# Chạy entrypoint gốc của MySQL để nó thực hiện các tác vụ khởi tạo ban đầu.
# Dấu '&' đưa nó vào nền và chúng ta lấy PID của nó.
echo "[Replica Entrypoint] Khởi động server MySQL local trong nền..."
/usr/local/bin/docker-entrypoint.sh "$@" &
pid="$!"

# 2. Chờ server local sẵn sàng.
echo "[Replica Entrypoint] Đang chờ server local khởi động..."
# Sử dụng `mysqladmin ping` là cách chuẩn để kiểm tra xem server có sẵn sàng hay không.
# Lệnh `until` sẽ lặp lại cho đến khi `mysql` trả về exit code 0 (thành công).
# Việc này không chỉ kiểm tra server có chạy không, mà còn xác thực credentials,
# làm cho việc chờ đợi trở nên đáng tin cậy hơn so với `mysqladmin ping`.
# Sử dụng localhost để kết nối qua socket, vì user root mặc định chỉ có quyền truy cập từ localhost.
until mysql -h"localhost" -u"root" -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1"; do
    echo "[Replica Entrypoint] Server local chưa sẵn sàng, đang chờ..."
    sleep 2
done
echo "[Replica Entrypoint] Server local đã sẵn sàng."

# 3. Chờ server primary sẵn sàng.
PRIMARY_HOST="db"
echo "[Replica Entrypoint] Đang chờ server primary '${PRIMARY_HOST}'..."
# Áp dụng logic chờ tương tự cho server primary.
until mysql -h"${PRIMARY_HOST}" -u"root" -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1"; do
    echo "[Replica Entrypoint] Server primary '${PRIMARY_HOST}' chưa sẵn sàng, đang chờ..."
    sleep 2
done
echo "[Replica Entrypoint] Server primary đã sẵn sàng."

# 4. Cấu hình Replication.
echo "[Replica Entrypoint] Bắt đầu cấu hình replication..."

# Sử dụng heredoc để tạo chuỗi lệnh SQL, giúp dễ đọc và bảo trì hơn.
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

# Thực thi lệnh SQL trên server local.
# Truyền mật khẩu trực tiếp, an toàn trong ngữ cảnh container này.
mysql -h"localhost" -u"root" -p"${MYSQL_ROOT_PASSWORD}" -e "${SQL_COMMAND}"

echo "[Replica Entrypoint] Cấu hình replication đã được áp dụng. Chờ 5 giây rồi kiểm tra trạng thái..."
sleep 5
mysql -h"localhost" -u"root" -p"${MYSQL_ROOT_PASSWORD}" -e "SHOW REPLICA STATUS\G"

# 5. Đưa tiến trình server MySQL ra tiền cảnh.
echo "[Replica Entrypoint] Cấu hình hoàn tất. Bàn giao quyền kiểm soát cho server MySQL (PID: $pid)..."
# Hủy trap dọn dẹp vì chúng ta muốn tiến trình mysqld tiếp tục chạy.
trap - EXIT INT TERM
wait "$pid"