<?php

namespace Core\Cache;

use Core\Contracts\Cache\Store;
use Redis;

class RedisStore implements Store
{
    protected Redis $redis;
    protected string $prefix;

    /**
     * Tạo một instance RedisStore mới.
     *
     * @param  \Redis  $redis
     * @param  string  $prefix
     */
    public function __construct(Redis $redis, string $prefix = '')
    {
        $this->redis = $redis;
        $this->setPrefix($prefix);
    }

    public function get($key)
    {
        $value = $this->redis->get($this->prefix . $key);
        return $value !== false ? unserialize($value) : null;
    }

    public function put($key, $value, $seconds)
    {
        return $this->redis->setex(
            $this->prefix . $key,
            $seconds,
            serialize($value),
        );
    }

    public function forget($key)
    {
        return (bool) $this->redis->del($this->prefix . $key);
    }

    public function flush()
    {
        $this->redis->flushDB();
        return true;
    }

    /**
     * Đặt tiền tố cho các key cache.
     *
     * @param  string  $prefix
     * @return void
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = !empty($prefix) ? $prefix . ':' : '';
    }
}
