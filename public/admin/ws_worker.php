<?php

use Spiral\RoadRunner\WebSocket\WebSocket;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\Environment;

ini_set('display_errors', 'stderr');
// The autoload file is in the project root, not relative to this script's directory.
require __DIR__ . '/../../vendor/autoload.php';

// --- Khởi tạo Application để có thể dùng các service khác ---
// Quan trọng: Điều này đảm bảo worker có thể truy cập vào các thành phần của framework.
// như Auth, Cache, v.v.
$app = (new \Core\AppKernel())->getApplication();

// --- Thiết lập Worker và các thành phần WebSocket ---
$worker = Worker::create();
$ws = new WebSocket($worker);

// --- Thiết lập RPC Server để lắng nghe lệnh từ HTTP worker ---
$rpc = \Spiral\Goridge\RPC\RPC::create(Environment::fromGlobals()->getRPCAddress());

// --- Bộ nhớ lưu trữ các kết nối của client ---
// SplObjectStorage hiệu quả hơn mảng cho việc lưu trữ object
$connections = new \SplObjectStorage();

/**
 * Đăng ký một phương thức RPC tên là 'informer.broadcast'.
 * HTTP worker sẽ gọi phương thức này.
 *
 * @param string $message Dữ liệu JSON cần gửi đi
 */
$rpc->register('informer.broadcast', static function (string $message) use ($ws, $worker): array {
    $worker->info("Broadcasting message: {$message}");

    // Gửi tin nhắn đến TẤT CẢ các client đang kết nối.
    // Việc truyền một mảng rỗng [] sẽ ra lệnh cho server RoadRunner (viết bằng Go)
    // thực hiện broadcast, hiệu năng cao hơn nhiều so với việc lặp qua từng kết nối trong PHP.
    $ws->send([], $message);

    return ['status' => 'ok'];
});

$worker->info('WebSocket worker started.');

// --- Vòng lặp chính của Worker ---
while (true) {
    // Kiểm tra xem có lệnh RPC nào không.
    // Tham số timeout (ms) để worker không bị block hoàn toàn khi chờ RPC.
    if ($rpc->hasPendingRequest(1)) {
        try {
            $rpc->serveNext();
        } catch (\Throwable $e) {
            $worker->error((string)$e);
        }
        continue; // Quay lại đầu vòng lặp để ưu tiên xử lý RPC
    }

    // Nếu không có RPC, kiểm tra xem có request WebSocket nào không.
    if (null === $request = $ws->waitRequest()) {
        $worker->info('WebSocket worker stopped gracefully.');
        break; // Thoát vòng lặp nếu server ra lệnh dừng
    }

    try {
        // --- Xử lý các sự kiện WebSocket ---
        foreach ($request->getConnections() as $connection) {
            // 1. Khi có kết nối mới (OnConnect)
            if (!$connections->contains($connection)) {
                // --- BẮT ĐẦU LOGIC XÁC THỰC ---
                try {
                    // Lấy token từ query string, ví dụ: /ws?token=...
                    $token = $connection->request->query['token'] ?? null;
                    if (!$token) {
                        throw new \RuntimeException('Authentication token not provided.');
                    }

                    // Sử dụng AuthManager của framework để xác thực token và lấy user.
                    // Giả sử bạn có một guard tên 'jwt_ws' đã được cấu hình.
                    $user = \Core\Support\Facades\Auth::guard('jwt_ws')->userFromToken($token);

                    if (!$user) {
                        throw new \RuntimeException('Invalid authentication token.');
                    }

                    // Lưu thông tin user đã xác thực vào kết nối để sử dụng sau này.
                    $connections->offsetSet($connection, ['user' => $user]); // Lưu thông tin user
                    $connections->attach($connection); // Thêm vào danh sách quản lý

                    $worker->info("Authenticated connection from user: {$user->id} (Connection ID: {$connection->id})");
                } catch (\Throwable $e) {
                    // Ghi log lỗi xác thực một cách chi tiết.
                    $worker->error(sprintf(
                        "Authentication failed for connection %s: %s",
                        $connection->id,
                        $e->getMessage()
                    ));
                    // Đóng kết nối với mã lỗi tùy chỉnh (4001: Unauthorized)
                    $ws->close($connection->id, 4001, 'Unauthorized');
                }
                // --- KẾT THÚC LOGIC XÁC THỰC ---
                continue; // Chuyển sang kết nối tiếp theo
            }

            // 2. Khi kết nối bị đóng (OnClose)
            if ($connection->isClosed()) {
                if ($connections->contains($connection)) {
                    $userInfo = $connections->offsetGet($connection);
                    $userId = $userInfo['user']->id ?? 'unknown';
                    $worker->info("Connection closed for user: {$userId} (Connection ID: {$connection->id})");
                    $connections->detach($connection);
                }
                continue;
            }
        }

        // 3. Khi nhận được tin nhắn (OnMessage) - Hiện tại chúng ta không cần xử lý
        // foreach ($request->getMessages() as $message) {
        //     // Logic xử lý tin nhắn từ client
        // }

    } catch (\Throwable $e) {
        // Bắt các lỗi không mong muốn trong vòng lặp và báo cho RoadRunner.
        $worker->error((string)$e);
    }
}