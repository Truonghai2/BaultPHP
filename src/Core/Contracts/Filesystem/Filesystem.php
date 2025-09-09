<?php

namespace Core\Contracts\Filesystem;

interface Filesystem
{
    public function exists(string $path): bool;

    public function get(string $path): string;

    public function readStream(string $path);

    public function put(string $path, $contents, array $config = []): void;

    public function writeStream(string $path, $resource, array $config = []): void;

    public function delete(string $path): void;

    public function url(string $path): string;
}
