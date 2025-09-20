<?php

namespace Core\Patches;

/**
 * Class SwooleStreamWrapper
 *
 * This class acts as a "wrapper" for PHP's native file stream wrapper.
 * Its primary purpose is to intercept the loading of a specific file,
 * `Psy\Readline\Hoa\Stream.php`, and patch it in-memory to remove a call
 * to `register_shutdown_function()`. This call is incompatible with the
 * long-running nature of a Swoole server and causes deprecation warnings.
 *
 * This approach avoids modifying the `vendor` directory directly and is only
 * active when the Swoole server is running. It is a robust implementation
 * that proxies all file operations to the underlying resource to ensure
 * full compatibility and prevent side effects.
 */
class SwooleStreamWrapper
{
    public $context;
    /** @var resource|null */
    private $resource;

    /**
     * Registers this wrapper for the 'file' protocol.
     */
    public static function wrap(): void
    {
        @stream_wrapper_unregister('file');
        @stream_wrapper_register('file', self::class);
    }

    /**
     * This method is called when a file is opened (e.g., via require, include, fopen).
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->unregister(); // Avoid recursion

        $targetFile = 'vendor/psy/psysh/src/Readline/Hoa/Stream.php';

        $realPath = @realpath($path);
        if ($realPath && str_ends_with(str_replace('\\', '/', $realPath), $targetFile)) {
            $originalContent = file_get_contents($realPath);

            if ($originalContent === false) {
                $this->register(); // Re-register before failing
                return false;
            }

            // Remove the problematic shutdown function registration.
            $patchedContent = preg_replace(
                "/\\r?\\n\\s*\\\\register_shutdown_function\(\[.*Stream::class,.*'_Hoa_Stream'\]\);/m",
                "\n// Shutdown function disabled for Swoole compatibility by BaultFrame.",
                $originalContent,
            );

            // Open an in-memory stream with the patched content.
            $this->resource = fopen('php://memory', 'r+');
            fwrite($this->resource, $patchedContent);
            rewind($this->resource);
        } else {
            // For all other files, use the native file handler.
            // Suppress errors as fopen() will emit a warning if the file does not exist.
            $this->resource = @fopen($path, $mode, ($options & STREAM_USE_PATH) > 0);
        }

        $this->register(); // CRITICAL: Re-register self for the next file operation

        return $this->resource !== false;
    }

    /**
     * Unregisters this stream wrapper and restores the native 'file' wrapper.
     */
    private function unregister(): void
    {
        @stream_wrapper_restore('file');
    }

    /**
     * Registers this class as the 'file' stream wrapper.
     * This is the counterpart to unregister() and ensures the wrapper stays active.
     */
    private function register(): void
    {
        // Suppress errors in case it's already registered.
        @stream_wrapper_unregister('file');
        @stream_wrapper_register('file', self::class);
    }

    // --- The following methods are proxies to the underlying stream resource ---

    public function stream_read(int $count)
    {
        return is_resource($this->resource) ? fread($this->resource, $count) : false;
    }

    public function stream_write(string $data): int
    {
        return is_resource($this->resource) ? fwrite($this->resource, $data) : 0;
    }

    public function stream_tell(): int
    {
        return is_resource($this->resource) ? ftell($this->resource) : 0;
    }

    public function stream_eof(): bool
    {
        return !is_resource($this->resource) || feof($this->resource);
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        return is_resource($this->resource) && fseek($this->resource, $offset, $whence) === 0;
    }

    public function stream_stat(): array|false
    {
        return is_resource($this->resource) ? fstat($this->resource) : false;
    }

    public function stream_close(): void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
    }

    public function stream_truncate(int $size): bool
    {
        return is_resource($this->resource) && ftruncate($this->resource, $size);
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return is_resource($this->resource) && stream_set_option($this->resource, $option, $arg1, $arg2);
    }

    public function stream_lock(int $operation): bool
    {
        return is_resource($this->resource) && flock($this->resource, $operation);
    }

    public function stream_flush(): bool
    {
        return is_resource($this->resource) && fflush($this->resource);
    }

    public function url_stat(string $path, int $flags): array|false
    {
        $this->unregister();
        try {
            return ($flags & STREAM_URL_STAT_LINK) ? @lstat($path) : @stat($path);
        } finally {
            $this->register();
        }
    }

    public function unlink(string $path): bool
    {
        $this->unregister();
        try {
            return unlink($path);
        } finally {
            $this->register();
        }
    }

    public function rename(string $path_from, string $path_to): bool
    {
        $this->unregister();
        try {
            return rename($path_from, $path_to);
        } finally {
            $this->register();
        }
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        $this->unregister();
        try {
            // The STREAM_MKDIR_RECURSIVE flag is part of the options bitmask
            return mkdir($path, $mode, ($options & STREAM_MKDIR_RECURSIVE) > 0);
        } finally {
            $this->register();
        }
    }

    public function rmdir(string $path, int $options): bool
    {
        $this->unregister();
        try {
            return rmdir($path);
        } finally {
            $this->register();
        }
    }
}
