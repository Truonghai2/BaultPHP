<?php

namespace Core\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Class Repository
 * Một lớp bao bọc (wrapper) quanh một implementation của PSR-16 cache,
 * cung cấp các phương thức tiện ích như `remember`.
 * Nó cũng implement CacheInterface, cho phép nó được sử dụng ở bất cứ đâu
 * mong đợi một cache tuân thủ PSR-16.
 */
class Repository implements CacheInterface
{
    /**
     * Instance của cache store bên dưới.
     *
     * @var \Psr\SimpleCache\CacheInterface
     */
    protected CacheInterface $store;

    /**
     * Tạo một instance cache repository mới.
     *
     * @param  \Psr\SimpleCache\CacheInterface  $store
     */
    public function __construct(CacheInterface $store)
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
    public function remember(string $key, $ttl, \Closure $callback)
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
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store->get($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        return $this->store->set($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return $this->store->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        return $this->store->clear();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->store->getMultiple($keys, $default);
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        return $this->store->setMultiple($values, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return $this->store->deleteMultiple($keys);
    }

    public function has(string $key): bool
    {
        return $this->store->has($key);
    }
}
