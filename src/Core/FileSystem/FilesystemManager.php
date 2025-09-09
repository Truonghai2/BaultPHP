<?php

namespace Core\Filesystem;

use Core\Contracts\Filesystem\Factory as FilesystemFactory;
use Core\Contracts\Filesystem\Filesystem;
use InvalidArgumentException;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class FilesystemManager implements FilesystemFactory
{
    protected $app;
    protected array $disks = [];

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function disk($name = null): Filesystem
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->disks[$name] = $this->get($name);
    }

    protected function get(string $name): Filesystem
    {
        return $this->disks[$name] ?? $this->resolve($name);
    }

    protected function resolve(string $name): Filesystem
    {
        $config = $this->getConfig($name);

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (!method_exists($this, $driverMethod)) {
            throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
        }

        return $this->{$driverMethod}($config);
    }

    protected function createLocalDriver(array $config): Filesystem
    {
        $adapter = new LocalFilesystemAdapter(
            $config['root'],
        );

        return new FilesystemAdapter(new Flysystem($adapter, $config), $adapter, $config);
    }

    protected function getConfig(string $name): array
    {
        return $this->app['config']["filesystems.disks.{$name}"];
    }

    public function getDefaultDriver(): string
    {
        return $this->app['config']['filesystems.default'];
    }

    public function __call($method, $parameters)
    {
        return $this->disk()->$method(...$parameters);
    }
}
