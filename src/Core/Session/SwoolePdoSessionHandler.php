<?php

namespace Core\Session;

use Core\Contracts\Session\SessionHandlerInterface;
use Core\Database\Swoole\SwoolePdoPool;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Trình xử lý session dựa trên PDO, tương thích với môi trường coroutine của Swoole.
 *
 * Handler này không giữ kết nối PDO cố định. Thay vào đó, nó lấy một kết nối
 * từ SwoolePdoPool cho mỗi hoạt động và trả lại ngay lập tức, giúp nó an toàn
 * cho các request đồng thời trong một ứng dụng chạy dài hạn.
 */
class SwoolePdoSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private string $connectionName,
        private string $table,
        private int $lifetime,
    ) {
    }

    /**
     * Phương thức helper để thực thi một truy vấn trong một khối get/put connection.
     *
     * @param callable $callback Callback để thực thi với kết nối PDO.
     * @return mixed
     */
    private function withConnection(callable $callback): mixed
    {
        $pdo = null;
        try {
            $pdo = SwoolePdoPool::get($this->connectionName);
            return $callback($pdo);
        } finally {
            if ($pdo) {
                SwoolePdoPool::put($pdo, $this->connectionName);
            }
        }
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string|false
    {
        return $this->withConnection(function (PDO $pdo) use ($sessionId) {
            $sql = "SELECT payload FROM {$this->table} WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['payload'] ?? '';
        });
    }

    public function write(string $sessionId, string $data): bool
    {
        return $this->withConnection(function (PDO $pdo) use ($sessionId, $data) {
            $attributes = @unserialize($data);
            $userId = null;

            // Extract user_id from session payload
            if (is_array($attributes)) {
                foreach ($attributes as $key => $value) {
                    if (str_starts_with($key, 'login_web_') && is_int($value)) {
                        $userId = $value;
                        break;
                    }
                }
            }

            $ipAddress = null;
            $userAgent = null;
            
            if (function_exists('app') && app()->has(ServerRequestInterface::class)) {
                $request = app(ServerRequestInterface::class);
                $serverParams = $request->getServerParams();
                $ipAddress = $serverParams['remote_addr'] ?? $serverParams['REMOTE_ADDR'] ?? null;
                
                // Lấy user agent từ request header
                $userAgent = $request->getHeaderLine('User-Agent') ?: null;
            }

            $sql = "INSERT INTO {$this->table} (id, user_id, ip_address, user_agent, payload, last_activity, lifetime)
                    VALUES (:id, :user_id, :ip_address, :user_agent, :data, :time, :lifetime)
                    ON DUPLICATE KEY UPDATE
                    payload = VALUES(payload), last_activity = VALUES(last_activity), lifetime = VALUES(lifetime),
                    user_id = VALUES(user_id), ip_address = VALUES(ip_address), user_agent = VALUES(user_agent)";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
            $stmt->bindParam(':data', $data, PDO::PARAM_LOB);
            $stmt->bindValue(':time', time(), PDO::PARAM_INT);
            $stmt->bindValue(':lifetime', $this->lifetime, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
            $stmt->bindValue(':user_agent', $userAgent, PDO::PARAM_STR);

            return $stmt->execute();
        });
    }

    public function destroy(string $sessionId): bool
    {
        return $this->withConnection(function (PDO $pdo) use ($sessionId) {
            $sql = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
            return $stmt->execute();
        });
    }

    public function gc(int $maxLifetime): int|false
    {
        return $this->withConnection(function (PDO $pdo) use ($maxLifetime) {
            $sql = "DELETE FROM {$this->table} WHERE last_activity < :time";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':time', time() - $maxLifetime, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        });
    }
}
