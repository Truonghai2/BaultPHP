<?php

namespace Core\Session;

use Core\Contracts\Session\SessionHandlerInterface;

class FileSessionHandler implements SessionHandlerInterface
{
    public function __construct(protected string $path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $file = $this->path . '/' . $id;
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        return '';
    }

    public function write(string $id, string $data): bool
    {
        return file_put_contents($this->path . '/' . $id, $data) !== false;
    }

    public function destroy(string $id): bool
    {
        $file = $this->path . '/' . $id;
        if (file_exists($file)) {
            unlink($file);
        }
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $count = 0;
        foreach (glob($this->path . '/*') as $file) {
            if (filemtime($file) + $max_lifetime < time() && file_exists($file)) {
                unlink($file);
                $count++;
            }
        }
        return $count;
    }
}
