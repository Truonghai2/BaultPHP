document.addEventListener('DOMContentLoaded', () => {
    // Tạo một container cho các thông báo toast
    const toastContainer = document.createElement('div');
    toastContainer.id = 'toast-container';
    toastContainer.style.position = 'fixed';
    toastContainer.style.top = '20px';
    toastContainer.style.right = '20px';
    toastContainer.style.zIndex = '1050';
    document.body.appendChild(toastContainer);

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;

        // Basic styling
        toast.style.padding = '15px';
        toast.style.marginBottom = '10px';
        toast.style.color = '#fff';
        toast.style.borderRadius = '5px';
        toast.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        toast.style.opacity = '0.9';
        toast.style.transition = 'opacity 0.5s ease';
        
        const colors = {
            info: '#007bff',
            success: '#28a745',
            error: '#dc3545'
        };
        toast.style.backgroundColor = colors[type] || colors.info;

        toastContainer.appendChild(toast);

        // Auto-dismiss
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 500);
        }, 7000); // Hiển thị trong 7 giây
    }

    function connectWebSocket() {
        // Lấy JWT token đã được lưu sau khi người dùng đăng nhập.
        // Giả sử token được lưu trong localStorage với key là 'jwt_token'.
        const token = localStorage.getItem('jwt_token');

        if (!token) {
            console.error('Authentication token not found. WebSocket connection aborted.');
            showToast('Authentication failed. Please log in again.', 'error');
            return;
        }

        // Gửi token qua query parameter
        const socket = new WebSocket(`ws://${window.location.host}/ws?token=${token}`);

        socket.onopen = () => {
            console.log('WebSocket connection established.');
        };

        socket.onmessage = (event) => {
            const message = JSON.parse(event.data);
            if (message.event === 'new_module_detected') {
                showToast(`New module detected: ${message.data.name} (v${message.data.version})`, 'success');
            } else if (message.event === 'user_specific_alert') {
                // Xử lý sự kiện mới dành riêng cho người dùng
                showToast(message.data.greeting, 'info');
            }
        };

        socket.onclose = (event) => {
            // Kiểm tra mã lỗi. Nếu là 4001 (Unauthorized), không kết nối lại.
            if (event.code === 4001) {
                console.error(`WebSocket connection closed by server: ${event.reason} (Code: ${event.code}). Won't reconnect.`);
                showToast('Session expired or invalid. Please log in again.', 'error');
            } else {
                console.log('WebSocket connection closed. Reconnecting in 5 seconds...');
                setTimeout(connectWebSocket, 5000);
            }
        };
    }

    connectWebSocket();
});