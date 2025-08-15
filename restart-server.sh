#!/bin/bash

# --- Cấu hình ---
# Đổi cổng này nếu bạn dùng cổng khác 9501
PORT=9501
# Lệnh để chạy ứng dụng PHP của bạn
PHP_COMMAND="php cli serve:start"

# --- Script ---
echo "Đang kiểm tra cổng $PORT..."

# Tìm Process ID (PID) của tiến trình đang lắng nghe trên cổng
# `lsof -t -i:PORT` sẽ chỉ trả về PID, rất tiện lợi
PID=$(lsof -t -i:$PORT)

# Kiểm tra xem biến PID có nội dung hay không
if [ -n "$PID" ]; then
  echo "Tìm thấy tiến trình cũ đang chạy trên cổng $PORT với PID: $PID. Đang dừng..."
  kill -9 "$PID"
  sleep 1 # Chờ 1 giây để đảm bảo tiến trình đã được tắt hoàn toàn
  echo "Đã dừng tiến trình cũ."
else
  echo "Cổng $PORT đã sẵn sàng."
fi

echo "Đang khởi động server BaultPHP..."
$PHP_COMMAND
