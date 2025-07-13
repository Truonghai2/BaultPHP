<?php

use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\Worker;

ini_set('display_errors', 'stderr');
require __DIR__ . '/vendor/autoload.php';

// Load .env để lấy chuỗi bí mật
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$rpcSecret = $_ENV['RPC_SECRET_TOKEN'] ?? null;
if ($rpcSecret === null) {
    // Thoát ngay nếu không có token, tránh chạy worker trong trạng thái không an toàn
    fwrite(STDERR, "Error: RPC_SECRET_TOKEN is not defined in .env file.\n");
    exit(1);
}

// Worker này kết nối đến RPC của RoadRunner server để có thể điều khiển các worker khác
$rpc = RPC::create(Environment::fromGlobals()->getRPCAddress());

// Tạo worker instance sớm hơn để có thể sử dụng cho việc ghi log
$worker = new Worker(null, Mode::MODE_RPC);

/**
 * Đăng ký phương thức 'resetter.reset'.
 *
 * @param string $token Token bí mật để xác thực.
 * @param array  $services Mảng các worker pool cần reset, ví dụ: ['http', 'centrifugo'].
 * @param string $reason (Tùy chọn) Lý do reset để ghi log.
 * @return array Kết quả.
 */
$rpc->register('resetter.reset', function (string $token, array $services, string $reason = 'Deployment') use ($rpc, $rpcSecret, $worker) {
    // BƯỚC BẢO MẬT QUAN TRỌNG
    // So sánh token được gửi đến với token trên server.
    // Sử dụng hash_equals để chống lại tấn công timing attack.
    if (!hash_equals($rpcSecret, $token)) {
        // Ghi log qua worker và từ chối yêu cầu
        $worker->error("RPC reset call rejected: Invalid token.");
        return ['status' => 'error', 'message' => 'Unauthorized. Invalid token.'];
    }

    $worker->info("RPC call received to reset workers. Reason: " . $reason);

    try {
        foreach ($services as $service) {
            $worker->info("Resetting worker pool: {$service}");
            $rpc->call('resetter.Reset', $service);
        }
        return ['status' => 'ok', 'message' => 'Workers are being reset: ' . implode(', ', $services)];
    } catch (\Throwable $e) {
        $worker->error("Failed to reset workers: " . $e->getMessage());
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
});

$worker->registerRpc('resetter', $rpc);
$worker->serve();