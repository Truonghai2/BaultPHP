<?php

namespace Core\Support\Facades;

/**
 * @method static \Core\Contracts\Filesystem\Filesystem disk(string|null $name = null)
 * @method static bool exists(string $path)
 * @method static string get(string $path)
 * @method static void put(string $path, string|resource|\Psr\Http\Message\StreamInterface $contents, mixed $options = [])
 * @method static string url(string $path)
 * @method static void delete(string|array $paths)
 *
 * @see \Core\Filesystem\FilesystemManager
 */
class Storage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'filesystem';
    }
}
