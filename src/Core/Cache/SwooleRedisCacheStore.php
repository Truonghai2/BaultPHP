<?php

namespace Core\Cache;

use Core\Database\Swoole\SwooleRedisPool;
use DateInterval;
use DateTimeInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Một Cache Store implement PSR-16, được tối ưu cho môi trường Swoole
 * bằng cách sử dụng connection pool.
 */
class SwooleRedisCacheStore implements CacheInterface
{
    protected string $prefix;

    public function __construct(string $prefix = '')
    {
        $this->setPrefix($prefix);
    }

    protected function setPrefix(string $prefix): void
    {
        $this->prefix = !empty($prefix) ? $prefix . ':' : '';
    }

    public function get($key, $default = null)
    {
        $redis = SwooleRedisPool::get();
        try {
            $value = $redis->get($this->prefix . $key);
            if ($value === false || $value === null) {
                return $default;
            }
            // Dữ liệu được lưu dưới dạng chuỗi, cần unserialize để lấy lại giá trị gốc.
            return unserialize($value);
        } finally {
            SwooleRedisPool::put($redis);
        }
    }

    public function set($key, $value, $ttl = null)
    {
        $redis = SwooleRedisPool::get();
        try {
            $serialized = serialize($value);
            $seconds = $this->getSeconds($ttl);

            if ($seconds <= 0) {
                // Theo chuẩn PSR-16, TTL <= 0 có nghĩa là xóa key.
                return $this->delete($key);
            }

            return $redis->setex($this->prefix . $key, $seconds, $serialized);
        } finally {
            SwooleRedisPool::put($redis);
        }
    }

    public function delete($key)
    {
        $redis = SwooleRedisPool::get();
        try {
            return $redis->del($this->prefix . $key) > 0;
        } finally {
            SwooleRedisPool::put($redis);
        }
    }

    public function clear()
    {
        // Cảnh báo: Thao tác này có thể chậm trên CSDL lớn.
        // Sử dụng SCAN để tránh block server Redis.
        $redis = SwooleRedisPool::get();
        try {
            $iterator = null;
            do {
                $keys = $redis->scan($iterator, $this->prefix . '*');
                if (!empty($keys)) {
                    // Xóa các key tìm thấy
                    $redis->del($keys);
                }
            } while ($iterator > 0);
            return true;
        } finally {
            SwooleRedisPool::put($redis);
        }
    }

    public function getMultiple($keys, $default = null)
    {
        $keys = is_array($keys) ? $keys : iterator_to_array($keys);
        if (empty($keys)) {
            return [];
        }

        $redis = SwooleRedisPool::get();
        try {
            $prefixedKeys = array_map(fn ($k) => $this->prefix . $k, $keys);
            $values = $redis->mget($prefixedKeys);
            $results = [];
            foreach ($keys as $index => $key) {
                $results[$key] = (isset($values[$index]) && $values[$index] !== false)
                    ? unserialize($values[$index])
                    : $default;
            }
            return $results;
        } finally {
            SwooleRedisPool::put($redis);
        }
    }

    public function setMultiple($values, $ttl = null)
    {
        $values = is_array($values) ? $values : iterator_to_array($values);
        if (empty($values)) {
            return true;
        }

        $redis = SwooleRedisPool::get();
        try {
            $redis->multi(\Redis::PIPELINE);
            $seconds = $this->getSeconds($ttl);
            foreach ($values as $key => $value) {
                if ($seconds > 0) {
                    $redis->setex($this->prefix . $key, $seconds, serialize($value));
                } else {
                    $redis->del($this->prefix . $key);
                }
            }
            $redis->exec();
            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            SwooleRedisPool::put($redis);
        }
    }

    public function deleteMultiple($keys)
    {
        $keys = is_array($keys) ? $keys : iterator_to_array($keys);
        if (empty($keys)) {
            return true;
        }

        $redis = SwooleRedisPool::get();
        try {
            $prefixedKeys = array_map(fn ($k) => $this->prefix . $k, $keys);
            $redis->del($prefixedKeys);
            return true;
        } finally {
            SwooleRedisPool::put($redis);
        }
    }

    public function has($key)
    {
        $redis = SwooleRedisPool::get();
        try {
            return (bool) $redis->exists($this->prefix . $key);
        } finally {
            SwooleRedisPool::put($redis);
        }
    }

    protected function getSeconds($ttl): int
    {
        if ($ttl instanceof DateInterval) {
            return (new \DateTime('now'))->add($ttl)->getTimestamp() - time();
        }

        if ($ttl instanceof DateTimeInterface) {
            return $ttl->getTimestamp() - time();
        }

        if (is_null($ttl)) {
            // Giá trị mặc định rất lớn, coi như là "vĩnh viễn".
            return 31536000; // 1 năm
        }

        return (int) $ttl;
    }
}
