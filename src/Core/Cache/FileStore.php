<?php

namespace Core\Cache;

use Core\Contracts\Cache\Store;
use Illuminate\Filesystem\Filesystem;

class FileStore implements Store
{
    protected Filesystem $files;
    protected string $directory;

    public function __construct(Filesystem $files, string $directory)
    {
        $this->files = $files;
        $this->directory = $directory;
    }

    public function get($key)
    {
        $path = $this->path($key);

        if (!$this->files->exists($path)) {
            return null;
        }

        try {
            $contents = $this->files->get($path, true);
            $data = unserialize($contents);

            if (time() >= $data['expire']) {
                $this->forget($key);
                return null;
            }

            return $data['value'];
        } catch (\Exception $e) {
            $this->forget($key);
            return null;
        }
    }

    public function put($key, $value, $seconds)
    {
        $path = $this->path($key);
        $this->ensureCacheDirectoryExists($path);

        $contents = serialize([
            'value' => $value,
            'expire' => time() + $seconds,
        ]);

        return $this->files->put($path, $contents, true) !== false;
    }

    public function forget($key)
    {
        $path = $this->path($key);

        if ($this->files->exists($path)) {
            return $this->files->delete($path);
        }

        return true;
    }

    public function flush()
    {
        return $this->files->deleteDirectory($this->directory, true);
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
            $this->files->makeDirectory($directory, 0777, true, true);
        }
    }
}
