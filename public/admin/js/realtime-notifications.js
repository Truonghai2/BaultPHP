document.addEventListener("DOMContentLoaded", () => {
  // Các hằng số để dễ dàng cấu hình
  const TOAST_VISIBLE_DURATION = 7000; // 7 giây
  const RECONNECT_DELAY = 5000; // 5 giây

  // Tạo một container cho các thông báo toast
  const toastContainer = document.createElement("div");
  toastContainer.id = "toast-container";
  document.body.appendChild(toastContainer);

  function showToast(message, type = "info") {
    const toast = document.createElement("div");
    // Sử dụng class từ file CSS ngoài
    toast.className = `toast toast-${type}`;
    toast.textContent = message;

    toastContainer.appendChild(toast);

    // Auto-dismiss
    setTimeout(() => {
      toast.classList.add("hide");
      // Xóa element khỏi DOM sau khi animation kết thúc
      toast.addEventListener("transitionend", () => toast.remove());
    }, TOAST_VISIBLE_DURATION);
  }

  function connectWebSocket() {
    // Lấy JWT token đã được lưu sau khi người dùng đăng nhập.
    // Giả sử token được lưu trong localStorage với key là 'jwt_token'.
    const token = localStorage.getItem("jwt_token");

    if (!token) {
      console.error(
        "Authentication token not found. WebSocket connection aborted.",
      );
      showToast("Authentication failed. Please log in again.", "error");
      return;
    }

    // Gửi token qua query parameter
    const socket = new WebSocket(
      `ws://${window.location.host}/ws?token=${token}`,
    );

    socket.onopen = () => {
      console.log("WebSocket connection established.");
    };

    socket.onmessage = (event) => {
      const message = JSON.parse(event.data);
      if (message.event === "new_module_detected") {
        showToast(
          `New module detected: ${message.data.name} (v${message.data.version})`,
          "success",
        );
      } else if (message.event === "user_specific_alert") {
        // Xử lý sự kiện mới dành riêng cho người dùng
        showToast(message.data.greeting, "info");
      }
    };

    socket.onclose = (event) => {
      // Kiểm tra mã lỗi. Nếu là 4001 (Unauthorized), không kết nối lại.
      if (event.code === 4001) {
        console.error(
          `WebSocket connection closed by server: ${event.reason} (Code: ${event.code}). Won't reconnect.`,
        );
        showToast("Session expired or invalid. Please log in again.", "error");
      } else {
        console.log(
          "WebSocket connection closed. Reconnecting in 5 seconds...",
        );
        setTimeout(connectWebSocket, RECONNECT_DELAY);
      }
    };
  }

  connectWebSocket();
});
