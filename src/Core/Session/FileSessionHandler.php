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

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string|false
    {
        if (!$this->isValidId($sessionId)) {
            return false;
        }

        $file = $this->path . '/' . $sessionId;

        if (is_readable($file)) {
            $content = file_get_contents($file);
            return $content === false ? '' : $content;
        }

        return '';
    }

    public function write(string $sessionId, string $data): bool
    {
        if (!$this->isValidId($sessionId)) {
            return false;
        }

        $file = $this->path . '/' . $sessionId;

        return file_put_contents($file, $data, LOCK_EX) !== false;
    }

    public function destroy(string $sessionId): bool
    {
        if (!$this->isValidId($sessionId)) {
            return false;
        }

        $file = $this->path . '/' . $sessionId;
        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function gc(int $maxLifetime): int|false
    {
        $count = 0;
        foreach (glob($this->path . '/*') as $file) {
            if (!$this->isValidId(basename($file))) {
                continue;
            }

            if (filemtime($file) + $maxLifetime < time() && file_exists($file)) {
                unlink($file);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Kiểm tra xem Session ID có hợp lệ không để tránh tấn công path traversal.
     */
    protected function isValidId(string $id): bool
    {
        return preg_match('/^[a-zA-Z0-9,-]+$/', $id) === 1;
    }
}
