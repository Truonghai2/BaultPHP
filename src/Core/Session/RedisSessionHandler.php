<?php

namespace Core\Session;

use Core\Database\Swoole\SwooleRedisPool;
use SessionHandlerInterface;
use Throwable;

class RedisSessionHandler implements SessionHandlerInterface
{
    protected int $lifetimeInSeconds;
    protected string $prefix = 'session:';

    /**
     * @param array $config Mảng cấu hình session.
     */
    public function __construct(array $config)
    {
        // Chuyển đổi lifetime từ phút sang giây
        $this->lifetimeInSeconds = (int) ($config['lifetime'] ?? 120) * 60;
    }

    /**
     * Không cần làm gì ở đây vì connection pool đã quản lý kết nối.
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * Không cần làm gì ở đây vì connection pool đã quản lý kết nối.
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Đọc dữ liệu session từ Redis.
     */
    public function read(string $id): string|false
    {
        $redis = SwooleRedisPool::get();
        try {
            $data = $redis->get($this->prefix . $id);
            return $data === false ? '' : $data;
        } finally {
            SwooleRedisPool::put($redis);
        }
    }

    /**
     * Ghi dữ liệu session vào Redis với thời gian hết hạn (TTL).
     */
    public function write(string $id, string $data): bool
    {
        $redis = SwooleRedisPool::get();
        try {
            // Sử dụng SETEX để ghi key và tự động đặt thời gian hết hạn.
            return $redis->setex($this->prefix . $id, $this->lifetimeInSeconds, $data);
        } catch (Throwable $e) {
            // Ghi log lỗi nếu cần
            return false;
        } finally {
            SwooleRedisPool::put($redis);
        }
    }

    /**
     * Xóa một session khỏi Redis.
     */
    public function destroy(string $id): bool
    {
        $redis = SwooleRedisPool::get();
        try {
            $redis->del($this->prefix . $id);
            return true;
        } catch (Throwable $e) {
            return false;
        } finally {
            SwooleRedisPool::put($redis);
        }
    }

    /**
     * Dọn dẹp các session đã hết hạn.
     * Với Redis và lệnh SETEX, việc này được tự động xử lý.
     * Do đó, chúng ta không cần implement logic ở đây.
     */
    public function gc(int $max_lifetime): int|false
    {
        return 0; // Trả về 0 vì không có session nào được dọn dẹp thủ công.
    }
}
