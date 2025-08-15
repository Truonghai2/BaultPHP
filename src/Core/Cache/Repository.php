<?php

namespace Core\Cache;

use Closure;
use Core\Contracts\Cache\Store as StoreContract;

/**
 * This class acts as a decorator for a cache store, implementing the Store contract
 * and providing some convenience methods.
 *
 * It's crucial that the methods defined in Psr\SimpleCache\CacheInterface
 * have signatures that are compatible with the interface. This means not adding
 * parameter type hints where the original interface does not have them.
 */
class Repository implements StoreContract
{
    /**
     * The underlying cache store instance.
     *
     * @var \Core\Contracts\Cache\Store
     */
    protected StoreContract $store;

    /**
     * The default number of seconds to cache items.
     * Can be null to store forever.
     *
     * @var int|null
     */
    protected ?int $default = 3600; // 1 hour

    /**
     * Create a new cache repository instance.
     *
     * @param \Core\Contracts\Cache\Store $store
     */
    public function __construct(StoreContract $store)
    {
        $this->store = $store;
    }

    /**
     * Lấy một item từ cache, hoặc thực thi Closure và lưu kết quả.
     *
     * @param  string  $key
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @param  \Closure  $callback
     * @return mixed
     */
    public function remember(string $key, $ttl, Closure $callback)
    {
        // Đầu tiên, thử lấy giá trị từ cache.
        $value = $this->get($key);

        // Nếu giá trị tồn tại (không phải null), trả về ngay lập tức.
        if (! is_null($value)) {
            return $value;
        }

        // Nếu không, thực thi callback để lấy giá trị mới.
        $value = $callback();

        // Lưu giá trị mới vào cache với thời gian sống (TTL) đã định.
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        return $this->store->get($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $seconds = is_null($ttl) ? $this->default : $ttl;
        return $this->store->set($key, $value, $seconds);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        return $this->store->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->store->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        return $this->store->getMultiple($keys, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        $seconds = is_null($ttl) ? $this->default : $ttl;
        return $this->store->setMultiple($values, $seconds);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        return $this->store->deleteMultiple($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        return $this->store->has($key);
    }
}
