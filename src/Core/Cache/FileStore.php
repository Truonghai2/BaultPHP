<?php

namespace Core\Cache;

use Core\Contracts\Cache\Store;
use Core\Filesystem\Filesystem;
use DateInterval;
use DateTimeInterface;

class FileStore implements Store
{
    protected Filesystem $files;
    protected string $directory;

    public function __construct(Filesystem $files, string $directory)
    {
        $this->files = $files;
        $this->directory = $directory;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        $path = $this->path($key);

        if (!$this->files->exists($path)) {
            return $default;
        }

        try {
            $contents = $this->files->get($path);
            $data = unserialize($contents);

            if (time() >= $data['expire']) {
                $this->delete($key);
                return $default;
            }

            return $data['value'];
        } catch (\Throwable) {
            $this->delete($key);
            return $default;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null): bool
    {
        $seconds = $this->getSeconds($ttl);

        if ($seconds <= 0) {
            return $this->delete($key);
        }

        $path = $this->path($key);
        $this->ensureCacheDirectoryExists($path);

        $contents = serialize([
            'value' => $value,
            'expire' => time() + $seconds,
        ]);

        // The third argument `true` enables file locking for safe concurrent writes.
        return $this->files->put($path, $contents, true) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key): bool
    {
        $path = $this->path($key);

        if ($this->files->exists($path)) {
            return $this->files->delete($path);
        }

        // PSR-16 specifies that deleting a non-existent key should return true.
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        // The second argument `true` preserves the top-level directory.
        return $this->files->deleteDirectory($this->directory, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key): bool
    {
        $marker = new \stdClass();
        return $this->get($key, $marker) !== $marker;
    }

    /**
     * Lấy đường dẫn đầy đủ đến file cache.
     *
     * @param  string  $key
     * @return string
     */
    protected function path(string $key): string
    {
        $hash = sha1($key);
        $parts = array_slice(str_split($hash, 2), 0, 2);

        return $this->directory . '/' . implode('/', $parts) . '/' . $hash;
    }

    /**
     * Đảm bảo thư mục cache tồn tại.
     *
     * @param  string  $path
     * @return void
     */
    protected function ensureCacheDirectoryExists(string $path): void
    {
        $directory = dirname($path);

        if (!$this->files->isDirectory($directory)) {
            // The last `true` is the `force` flag.
            $this->files->makeDirectory($directory, 0777, true, true);
        }
    }

    /**
     * Get the number of seconds for the given TTL.
     *
     * @param  \DateInterval|int|null  $ttl
     * @return int
     */
    protected function getSeconds($ttl): int
    {
        if ($ttl instanceof DateInterval) {
            return (new \DateTime('now'))->add($ttl)->getTimestamp() - time();
        }

        if ($ttl instanceof DateTimeInterface) {
            return $ttl->getTimestamp() - time();
        }

        return is_null($ttl)
            ? 315360000 // A very large value (10 years), effectively "forever".
            : (int) $ttl;
    }
}
