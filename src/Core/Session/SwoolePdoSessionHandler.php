<?php

namespace Core\Session;

use Core\Database\Swoole\SwoolePdoPool;
use PDO;
use SessionHandlerInterface;
use Throwable;

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
            // First, try to update an existing session
            $sql = "UPDATE {$this->table} SET payload = :data, last_activity = :time, lifetime = :lifetime WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
            $stmt->bindParam(':data', $data, PDO::PARAM_STR);
            $stmt->bindValue(':time', time(), PDO::PARAM_INT);
            $stmt->bindValue(':lifetime', $this->lifetime, PDO::PARAM_INT);
            $stmt->execute();

            // If no rows were affected, it means the session ID doesn't exist, so insert it.
            if ($stmt->rowCount() === 0) {
                try {
                    $sql = "INSERT INTO {$this->table} (id, payload, last_activity, lifetime) VALUES (:id, :data, :time, :lifetime)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
                    $stmt->bindParam(':data', $data, PDO::PARAM_STR);
                    $stmt->bindValue(':time', time(), PDO::PARAM_INT);
                    $stmt->bindValue(':lifetime', $this->lifetime, PDO::PARAM_INT);
                    $stmt->execute();
                } catch (Throwable) {
                    // Handle potential race conditions where another process inserted the session between our UPDATE and INSERT.
                    // We can simply retry the update.
                    return $this->write($sessionId, $data);
                }
            }

            return true;
        });
    }

    public function destroy(string $sessionId): bool
    {
        return $this->withConnection(function (PDO $pdo) use ($sessionId) {
            $sql = "DELETE FROM {$this->table} WHERE sess_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
            $stmt->execute();

            return true;
        });
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->withConnection(function (PDO $pdo) use ($max_lifetime) {
            $sql = "DELETE FROM {$this->table} WHERE sess_time + sess_lifetime < :time";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':time', time() - $max_lifetime, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        });
    }
}
