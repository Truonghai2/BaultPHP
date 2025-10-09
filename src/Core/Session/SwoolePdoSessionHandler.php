<?php

namespace Core\Session;

use Core\Database\Swoole\SwoolePdoPool;
use PDO;
use SessionHandlerInterface;

/**
 * A PDO-based session handler compatible with Swoole's coroutine environment.
 *
 * This handler does not hold a persistent PDO connection. Instead, it acquires
 * a connection from the SwoolePdoPool for each operation and releases it
 * immediately, making it safe for concurrent requests in a long-running application.
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
     * A helper method to execute a query within a get/put block.
     *
     * @param callable $callback The callback to execute with the PDO connection.
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

    public function open(string $path, string $name): bool
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
            $sql = "SELECT payload FROM {$this->table} WHERE id = :id AND lifetime + last_activity >= :time";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
            $stmt->bindValue(':time', time(), PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? $result['payload'] : '';
        });
    }

    public function write(string $sessionId, string $data): bool
    {
        return $this->withConnection(function (PDO $pdo) use ($sessionId, $data) {
            $sql = "INSERT INTO {$this->table} (id, payload, last_activity, lifetime, user_id, ip_address) 
                    VALUES (:id, :data, :time, :lifetime, :user_id, :ip_address)
                    ON DUPLICATE KEY UPDATE 
                    payload = VALUES(payload), last_activity = VALUES(last_activity), lifetime = VALUES(lifetime)";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
            $stmt->bindParam(':data', $data, PDO::PARAM_LOB);
            $stmt->bindValue(':time', time(), PDO::PARAM_INT);
            $stmt->bindValue(':lifetime', $this->lifetime, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', null, PDO::PARAM_INT);
            $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null, PDO::PARAM_STR);
            $stmt->execute();

            return true;
        });
    }

    public function destroy(string $sessionId): bool
    {
        return $this->withConnection(function (PDO $pdo) use ($sessionId) {
            $sql = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
            $stmt->execute();

            return true;
        });
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->withConnection(function (PDO $pdo) use ($max_lifetime) {
            $sql = "DELETE FROM {$this->table} WHERE last_activity < :time";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':time', time() - $max_lifetime, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        });
    }
}
