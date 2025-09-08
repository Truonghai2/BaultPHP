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

            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            switch ($driver) {
                case 'mysql':
                    $sql = "INSERT INTO {$this->table} (id, payload, last_activity, ip_address, user_agent, user_id)
                            VALUES (:id, :payload, :last_activity, :ip_address, :user_agent, :user_id)
                            ON DUPLICATE KEY UPDATE
                            payload = VALUES(payload), last_activity = VALUES(last_activity),
                            ip_address = VALUES(ip_address), user_agent = VALUES(user_agent), user_id = VALUES(user_id)";
                    break;
                case 'pgsql':
                    $sql = "INSERT INTO {$this->table} (id, payload, last_activity, ip_address, user_agent, user_id)
                            VALUES (:id, :payload, :last_activity, :ip_address, :user_agent, :user_id)
                            ON CONFLICT (id) DO UPDATE SET
                            payload = EXCLUDED.payload, last_activity = EXCLUDED.last_activity,
                            ip_address = EXCLUDED.ip_address, user_agent = EXCLUDED.user_agent, user_id = EXCLUDED.user_id";
                    break;
                case 'sqlite':
                    $sql = "INSERT OR REPLACE INTO {$this->table} (id, payload, last_activity, ip_address, user_agent, user_id)
                            VALUES (:id, :payload, :last_activity, :ip_address, :user_agent, :user_id)";
                    break;
                default:
                    throw new \RuntimeException("Database driver '{$driver}' is not supported by DatabaseSessionHandler's upsert logic.");
            }

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
        $isSwooleCoroutine = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;

        if ($isSwooleCoroutine && class_exists(SwoolePdoPool::class) && SwoolePdoPool::isInitialized()) {
            return SwoolePdoPool::get();
        }

        return $this->app->make(PDO::class);
    }

    /**
     * Release the database connection back to the pool.
     *
     * @param PDO $pdo
     */
    protected function releaseConnection(PDO $pdo): void
    {
        $isSwooleCoroutine = extension_loaded('swoole') && \Swoole\Coroutine::getCid() > 0;

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
        try {
            $data = unserialize($payload, ['allowed_classes' => false]);
        } catch (\Throwable $e) {
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
