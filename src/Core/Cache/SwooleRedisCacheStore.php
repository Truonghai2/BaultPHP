<?php

namespace Core\Cache;

use Amp\Future;
use Amp\Redis\SetOptions;
use Core\Redis\FiberRedisManager;
use DateInterval;
use DateTimeInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Một Cache Store implement PSR-16, được tối ưu cho môi trường Swoole
 * bằng cách sử dụng FiberRedisManager và connection pool bất đồng bộ.
 */
class SwooleRedisCacheStore implements CacheInterface
{
    protected string $prefix;

    /**
     * @param FiberRedisManager $redisManager The asynchronous Redis connection pool manager.
     * @param string $prefix A prefix for all cache keys.
     */
    public function __construct(protected FiberRedisManager $redisManager, string $prefix = '')
    {
        $this->setPrefix($prefix);
    }

    protected function setPrefix(string $prefix): void
    {
        $this->prefix = !empty($prefix) ? $prefix . ':' : '';
    }

    public function get($key, $default = null)
    {
        $redis = null;
        try {
            $redis = $this->redisManager->get();
            $value = Future\await($redis->get($this->prefix . $key));

            if ($value === null) {
                return $default;
            }

            return unserialize($value);
        } finally {
            if ($redis) {
                $this->redisManager->put($redis);
            }
        }
    }

    public function set($key, $value, $ttl = null)
    {
        $redis = null;
        try {
            $redis = $this->redisManager->get();
            $serialized = serialize($value);
            $seconds = $this->getSeconds($ttl);

            if ($seconds <= 0) {
                return Future\await($redis->delete($this->prefix . $key)) > 0;
            }

            $options = SetOptions::default()->withTtl($seconds);
            Future\await($redis->set($this->prefix . $key, $serialized, $options));

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            if ($redis) {
                $this->redisManager->put($redis);
            }
        }
    }

    public function delete($key)
    {
        $redis = null;
        try {
            $redis = $this->redisManager->get();
            return Future\await($redis->delete($this->prefix . $key)) > 0;
        } catch (Throwable) {
            return false;
        } finally {
            if ($redis) {
                $this->redisManager->put($redis);
            }
        }
    }

    public function clear()
    {
        $redis = null;
        try {
            $redis = $this->redisManager->get();
            $iterator = $redis->scan($this->prefix . '*');
            $keysToDelete = [];
            foreach ($iterator as $key) {
                $keysToDelete[] = $key;
            }

            if (!empty($keysToDelete)) {
                Future\await($redis->delete(...$keysToDelete));
            }

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            if ($redis) {
                $this->redisManager->put($redis);
            }
        }
    }

    public function getMultiple($keys, $default = null)
    {
        $keys = is_array($keys) ? $keys : iterator_to_array($keys);
        if (empty($keys)) {
            return [];
        }

        $redis = null;
        try {
            $redis = $this->redisManager->get();
            $prefixedKeys = array_map(fn ($k) => $this->prefix . $k, $keys);
            $values = Future\await($redis->getMultiple($prefixedKeys));

            $results = array_fill_keys($keys, $default);
            $prefixLength = strlen($this->prefix);

            foreach ($values as $prefixedKey => $value) {
                if ($value !== null) {
                    $originalKey = substr($prefixedKey, $prefixLength);
                    $results[$originalKey] = unserialize($value);
                }
            }

            return $results;
        } finally {
            if ($redis) {
                $this->redisManager->put($redis);
            }
        }
    }

    public function setMultiple($values, $ttl = null)
    {
        $values = is_array($values) ? $values : iterator_to_array($values);
        if (empty($values)) {
            return true;
        }

        $redis = null;
        try {
            $redis = $this->redisManager->get();
            $seconds = $this->getSeconds($ttl);
            $data = [];

            foreach ($values as $key => $value) {
                $data[$this->prefix . $key] = serialize($value);
            }

            if ($seconds > 0) {
                Future\await($redis->setMultiple($data, SetOptions::default()->withTtl($seconds)));
            } else {
                Future\await($redis->delete(...array_keys($data)));
            }

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            if ($redis) {
                $this->redisManager->put($redis);
            }
        }
    }

    public function deleteMultiple($keys)
    {
        $keys = is_array($keys) ? $keys : iterator_to_array($keys);
        if (empty($keys)) {
            return true;
        }

        $redis = null;
        try {
            $redis = $this->redisManager->get();
            $prefixedKeys = array_map(fn ($k) => $this->prefix . $k, $keys);
            Future\await($redis->delete(...$prefixedKeys));
            return true;
        } catch (Throwable) {
            return false;
        } finally {
            if ($redis) {
                $this->redisManager->put($redis);
            }
        }
    }

    public function has($key)
    {
        $redis = null;
        try {
            $redis = $this->redisManager->get();
            return Future\await($redis->exists($this->prefix . $key)) > 0;
        } catch (Throwable) {
            return false;
        } finally {
            if ($redis) {
                $this->redisManager->put($redis);
            }
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
            return 31536000;
        }

        return (int) $ttl;
    }
}
