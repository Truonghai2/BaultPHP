<?php

namespace Core\Session;

use Core\Database\Swoole\SwoolePdoPool;
use PDO;
use SessionHandlerInterface;

class DatabaseSessionHandler implements SessionHandlerInterface
{
    protected \Core\Application $app;
    protected string $table;
    protected int $minutes;

    public function __construct(\Core\Application $app, string $table, int $minutes)
    {
        $this->app = $app;
        $this->table = $table;
        $this->minutes = $minutes;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $pdo = $this->getConnection();
        try {
            $stmt = $pdo->prepare(
                "SELECT payload FROM {$this->table} WHERE id = :id",
            );
            $stmt->execute(['id' => $id]);

            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session && isset($session['payload'])) {
                return base64_decode($session['payload']);
            }

            return '';
        } finally {
            $this->releaseConnection($pdo);
        }
    }

    public function write(string $id, string $data): bool
    {
        $pdo = $this->getConnection();
        try {
            $payload = [
                'id' => $id,
                'payload' => base64_encode($data),
                'last_activity' => time(),
                'ip_address' => $this->getIpAddress(),
                'user_agent' => $this->getUserAgent(),
                'user_id' => $this->getUserId($data),
            ];

            // Sử dụng cú pháp "upsert" (update or insert) để hiệu quả
            // Cải tiến: Sử dụng INSERT ... ON DUPLICATE KEY UPDATE thay vì REPLACE.
            // Lệnh này hiệu quả hơn và ít phá hủy hơn (không thực hiện DELETE + INSERT),
            // giúp giữ lại các giá trị không thay đổi và tránh kích hoạt trigger DELETE không mong muốn.
            // Lưu ý: Cú pháp này dành riêng cho MySQL/MariaDB.
            $sql = "INSERT INTO {$this->table} (id, payload, last_activity, ip_address, user_agent, user_id)
                    VALUES (:id, :payload, :last_activity, :ip_address, :user_agent, :user_id)
                    ON DUPLICATE KEY UPDATE
                    payload = VALUES(payload), last_activity = VALUES(last_activity),
                    ip_address = VALUES(ip_address), user_agent = VALUES(user_agent), user_id = VALUES(user_id)";

            $stmt = $pdo->prepare($sql);

            return $stmt->execute($payload);
        } finally {
            $this->releaseConnection($pdo);
        }
    }

    public function destroy(string $id): bool
    {
        $pdo = $this->getConnection();
        try {
            $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } finally {
            $this->releaseConnection($pdo);
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        $pdo = $this->getConnection();
        try {
            $old = time() - $max_lifetime;
            $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE last_activity <= :old");
            $stmt->execute(['old' => $old]);
            return $stmt->rowCount();
        } finally {
            $this->releaseConnection($pdo);
        }
    }

    /**
     * Get a database connection from the pool or create a new one.
     *
     * @return \PDO|\Swoole\Database\PDOProxy
     */
    protected function getConnection(): mixed
    {
        // The check must be performed here, inside the method, not in the constructor.
        // This ensures we check the context for every session operation, not just once at worker startup.
        $isSwooleCoroutine = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;

        // If running in Swoole and the pool is enabled, get a connection from the pool.
        if ($isSwooleCoroutine && class_exists(SwoolePdoPool::class) && SwoolePdoPool::isInitialized()) {
            return SwoolePdoPool::get();
        }

        // Fallback for non-Swoole environments (e.g., CLI commands).
        return $this->app->make(PDO::class);
    }

    /**
     * Release the database connection back to the pool.
     *
     * @param PDO $pdo
     */
    protected function releaseConnection(PDO $pdo): void
    {
        // We must perform the same check here to ensure we only put connections back that came from the pool.
        $isSwooleCoroutine = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;

        // Only release the connection back to the pool if it came from there.
        if ($isSwooleCoroutine && class_exists(SwoolePdoPool::class) && SwoolePdoPool::isInitialized()) {
            SwoolePdoPool::put($pdo);
        }
    }

    protected function getIpAddress(): ?string
    {
        if ($this->app->has(\Psr\Http\Message\ServerRequestInterface::class)) {
            return $this->app->make(\Psr\Http\Message\ServerRequestInterface::class)->getServerParams()['REMOTE_ADDR'] ?? null;
        }
        return null;
    }

    protected function getUserAgent(): ?string
    {
        if ($this->app->has(\Psr\Http\Message\ServerRequestInterface::class)) {
            return $this->app->make(\Psr\Http\Message\ServerRequestInterface::class)->getHeaderLine('User-Agent');
        }
        return null;
    }

    protected function getUserId(string $payload): ?int
    {
        // Sử dụng unserialize() trên dữ liệu không đáng tin cậy là một rủi ro bảo mật.
        // Chúng ta có thể giảm thiểu rủi ro này bằng cách ngăn chặn việc khởi tạo đối tượng.
        // Khối try-catch được sử dụng để xử lý các lỗi unserialize tiềm ẩn
        // từ dữ liệu session bị hỏng hoặc không hợp lệ, an toàn hơn việc dùng toán tử @.
        try {
            $data = unserialize($payload, ['allowed_classes' => false]);
        } catch (\Throwable $e) {
            // Nếu unserialize thất bại, chúng ta không thể lấy được user ID.
            // Ghi log sự kiện này nếu có logger.
            if ($this->app->has(\Psr\Log\LoggerInterface::class)) {
                $this->app->make(\Psr\Log\LoggerInterface::class)->warning('Failed to unserialize session payload.', ['exception' => $e]);
            }
            return null;
        }
        if (isset($data['_sf2_attributes']['_user_id'])) {
            return $data['_sf2_attributes']['_user_id'];
        }

        return null;
    }
}
