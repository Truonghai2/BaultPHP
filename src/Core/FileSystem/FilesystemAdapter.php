<?php

namespace Core\Filesystem;

use Core\Contracts\Filesystem\Filesystem as FilesystemContract;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\FilesystemAdapter as FlysystemAdapterInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class FilesystemAdapter implements FilesystemContract
{
    protected Flysystem $driver;
    protected FlysystemAdapterInterface $adapter;
    protected array $config;

    public function __construct(Flysystem $driver, FlysystemAdapterInterface $adapter, array $config)
    {
        $this->driver = $driver;
        $this->adapter = $adapter;
        $this->config = $config;
    }

    public function exists(string $path): bool
    {
        return $this->driver->fileExists($path);
    }

    public function get(string $path): string
    {
        return $this->driver->read($path);
    }

    public function readStream(string $path)
    {
        return $this->driver->readStream($path);
    }

    /**
     * @param string $path
     * @param string|resource|\Psr\Http\Message\StreamInterface $contents
     * @param array $config
     */
    public function put(string $path, $contents, array $config = []): void
    {
        if ($contents instanceof StreamInterface) {
            $this->writeStream($path, $contents->detach(), $config);
        } elseif (is_resource($contents)) {
            $this->writeStream($path, $contents, $config);
        } else {
            $this->driver->write($path, $contents, $config);
        }
    }

    public function writeStream(string $path, $resource, array $config = []): void
    {
        $this->driver->writeStream($path, $resource, $config);
    }

    public function delete(string $path): void
    {
        $this->driver->delete($path);
    }

    public function url(string $path): string
    {
        if (isset($this->config['url'])) {
            return rtrim($this->config['url'], '/') . '/' . ltrim($path, '/');
        }

        throw new RuntimeException('This disk does not have a configured URL.');
    }

    public function __call($method, $parameters)
    {
        return $this->driver->$method(...$parameters);
    }
}
