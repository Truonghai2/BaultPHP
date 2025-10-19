<?php

namespace Core\Session;

use Core\Contracts\Session\SessionHandlerInterface;
use Core\Database\Swoole\SwooleRedisPool;
use Core\Logging\Log;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Redis;

/**
 * A Redis-based session handler compatible with Swoole's coroutine environment.
 *
 * This handler acquires a connection from the SwooleRedisPool for each operation
 * and releases it immediately, making it safe for concurrent requests.
 */
class SwooleRedisSessionHandler implements SessionHandlerInterface
{
    /**
     * The prefix for session keys in Redis.
     */
    private string $prefix = 'session:';

    private LoggerInterface $logger;

    /**
     * @param string $connectionName The name of the Redis pool connection.
     * @param int $lifetime The session lifetime in seconds.
     */
    public function __construct(
        private string $connectionName,
        private int $lifetime,
    ) {
        $this->logger = Log::channel('session');
    }

    /**
     * A helper method to execute a command within a get/put block.
     *
     * @param callable $callback The callback to execute with the Redis connection.
     * @return mixed
     */
    private function withConnection(callable $callback): mixed
    {
        $redis = null;
        try {
            $redis = SwooleRedisPool::get($this->connectionName);
            return $callback($redis);
        } catch (\Throwable $e) {
            $this->logger->error('Session Redis Error: ' . $e->getMessage(), ['exception' => $e]);
            throw new \RuntimeException('Failed to execute Redis session command.', 0, $e);
        } finally {
            if ($redis) {
                SwooleRedisPool::put($redis, $this->connectionName);
            }
        }
    }

    public function open(string $path, string $name): bool
    {
        $this->logger->info('Opening session.', ['path' => $path, 'name' => $name]);
        return true;
    }

    public function close(): bool
    {
        $this->logger->info('Closing session.');
        return true;
    }

    public function read(string $sessionId): string|false
    {
        return $this->withConnection(function (Redis $redis) use ($sessionId) {
            $data = $redis->hGet($this->prefix . $sessionId, 'payload');
            $this->logger->info('Reading session.', ['session_id' => $sessionId, 'has_data' => !empty($data)]);
            return $data === false ? '' : $data;
        });
    }

    public function write(string $sessionId, string $data): bool
    {
        return $this->withConnection(function (Redis $redis) use ($sessionId, $data) {
            $key = $this->prefix . $sessionId;

            $attributes = @unserialize($data);
            $userId = null;
            if (is_array($attributes)) {
                foreach ($attributes as $attrKey => $value) {
                    if (str_starts_with($attrKey, 'login_web_') && is_int($value)) {
                        $userId = $value;
                        break;
                    }
                }
            }

            $ipAddress = null;
            if (function_exists('app') && app()->has(ServerRequestInterface::class)) {
                $request = app(ServerRequestInterface::class);
                $serverParams = $request->getServerParams();
                $ipAddress = $serverParams['remote_addr'] ?? $serverParams['REMOTE_ADDR'] ?? null;
            }

            // Sử dụng pipeline để gửi nhiều lệnh cùng lúc, giảm độ trễ mạng.
            $redis->multi();
            $redis->hMSet($key, [
                'payload' => $data,
                'last_activity' => time(),
                'user_id' => $userId,
                'ip_address' => $ipAddress,
            ]);
            $redis->expire($key, $this->lifetime);
            $results = $redis->exec();

            // Chỉ log ở mức debug để tránh làm đầy log trong môi trường production
            $this->logger->debug('Writing session.', ['session_id' => $sessionId, 'user_id' => $userId, 'ip_address' => $ipAddress]);

            return is_array($results);
        });
    }

    public function destroy(string $sessionId): bool
    {
        return (bool) $this->withConnection(function (Redis $redis) use ($sessionId) {
            $this->logger->info('Destroying session.', ['session_id' => $sessionId]);
            return $redis->del($this->prefix . $sessionId);
        });
    }

    public function gc(int $max_lifetime): int|false
    {
        $this->logger->info('Garbage collection.', ['max_lifetime' => $max_lifetime]);
        return 0;
    }
}
