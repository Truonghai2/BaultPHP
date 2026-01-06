<?php

namespace Core\Session;

use Core\Contracts\Session\SessionHandlerInterface;
use Core\Database\Swoole\SwoolePdoPool;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A Swoole-compatible PDO session handler.
 *
 * This handler is a standalone implementation that directly interacts with the database
 * using a connection from the SwoolePdoPool. It is synchronized with other custom handlers
 * in the application to ensure consistent session data handling, including storing
 * user ID and IP address information.
 */
class SwooleCompatiblePdoSessionHandler implements SessionHandlerInterface
{
    private string $table;
    private int $lifetime;

    /**
     * @param string $connectionName The name of the PDO pool connection.
     * @param array $options Session options, including 'db_table' and 'db_lifetime'.
     */
    public function __construct(
        private string $connectionName,
        #[\SensitiveParameter] private array $options = [],
    ) {
        $this->table = $this->options['db_table'] ?? 'sessions';
        $this->lifetime = $this->options['db_lifetime'] ?? (int) ini_get('session.gc_maxlifetime');
    }

    /**
     * Executes a callback with a temporary PDO connection from the pool.
     *
     * @param \Closure $callback The operation to perform.
     * @return mixed The result of the callback.
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
            $user_id = null;

            if (is_array($attributes)) {
                foreach ($attributes as $key => $value) {
                    if (str_starts_with($key, 'login_') && is_int($value)) {
                        $user_id = $value;
                        break;
                    }
                }
            }

            $ip_address = null;
            $user_agent = null;
            
            if (function_exists('app') && app()->has(ServerRequestInterface::class)) {
                $request = app(ServerRequestInterface::class);
                $serverParams = $request->getServerParams();
                $ip_address = $serverParams['remote_addr'] ?? $serverParams['REMOTE_ADDR'] ?? null;
                
                $user_agent = $request->getHeaderLine('User-Agent') ?: null;
            }

            $sql = "INSERT INTO {$this->table} (id, user_id, ip_address, user_agent, payload, last_activity, lifetime)
                    VALUES (:id, :user_id, :ip_address, :user_agent, :data, :time, :lifetime)
                    ON DUPLICATE KEY UPDATE
                    payload = VALUES(payload),
                    last_activity = VALUES(last_activity),
                    lifetime = VALUES(lifetime),
                    user_id = VALUES(user_id),
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent)";

            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $sessionId, PDO::PARAM_STR);
            $stmt->bindParam(':data', $data, PDO::PARAM_LOB);
            $stmt->bindValue(':time', time(), PDO::PARAM_INT);
            $stmt->bindValue(':lifetime', $this->lifetime, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $user_id, $user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':ip_address', $ip_address, PDO::PARAM_STR);
            $stmt->bindValue(':user_agent', $user_agent, PDO::PARAM_STR);

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
