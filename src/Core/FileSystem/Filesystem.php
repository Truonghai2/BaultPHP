<?php

namespace Core\Filesystem;

use Core\Filesystem\Exceptions\FileNotFoundException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A simple filesystem utility class.
 * This class is being built out to support the framework's needs.
 */
class Filesystem
{
    /**
     * Determine if a file or directory exists.
     *
     * @param  string  $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Determine if the given path is a file.
     *
     * @param  string  $file
     * @return bool
     */
    public function isFile(string $file): bool
    {
        return is_file($file);
    }

    /**
     * Get the contents of a file.
     *
     * @param  string  $path
     * @return string
     *
     * @throws \Core\Filesystem\Exceptions\FileNotFoundException
     */
    public function get(string $path): string
    {
        if ($this->isFile($path)) {
            return file_get_contents($path);
        }

        throw new FileNotFoundException("File does not exist at path {$path}.");
    }

    /**
     * Get the returned value of a file.
     *
     * This method is required by the Illuminate\Translation\FileLoader.
     *
     * @param  string  $path
     * @return mixed
     *
     * @throws \Core\Filesystem\Exceptions\FileNotFoundException
     */
    public function getRequire(string $path): mixed
    {
        if ($this->isFile($path)) {
            return require $path;
        }

        throw new FileNotFoundException("File does not exist at path {$path}.");
    }

    /**
     * Delete the file at a given path.
     *
     * @param  string  $path
     * @return bool
     */
    public function delete(string $path): bool
    {
        return @unlink($path);
    }

    /**
     * Ghi nội dung vào một file.
     *
     * Phương thức này sẽ tự động tạo thư mục cho file nếu nó chưa tồn tại.
     *
     * @param  string  $path Đường dẫn đến file.
     * @param  string|resource  $contents Nội dung cần ghi.
     * @param  bool  $lock Có khóa file trong khi ghi hay không.
     * @return int|false Số byte đã được ghi vào file, hoặc `false` nếu thất bại.
     */
    public function put(string $path, mixed $contents, bool $lock = false): int|false
    {
        $this->ensureDirectoryExists(dirname($path));

        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    /**
     * Create a directory.
     *
     * @param  string  $path
     * @param  int  $mode
     * @param  bool  $recursive
     * @param  bool  $force
     * @return bool
     */
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false): bool
    {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }

    /**
     * Ensure a directory exists.
     *
     * @param  string  $path
     * @param  int  $mode
     * @param  bool  $recursive
     * @return void
     */
    public function ensureDirectoryExists(string $path, int $mode = 0755, bool $recursive = true): void
    {
        if (! $this->exists($path)) {
            $this->makeDirectory($path, $mode, $recursive);
        }
    }

    /**
     * Determine if the given path is a directory.
     *
     * @param  string  $directory
     * @return bool
     */
    public function isDirectory(string $directory): bool
    {
        return is_dir($directory);
    }

    /**
     * Get the file's last modification time.
     *
     * @param  string  $path
     * @return int|false The Unix timestamp of the last modification, or false on failure.
     */
    public function lastModified(string $path): int|false
    {
        // The @ operator suppresses warnings if the file does not exist,
        // which is a safe way to handle this in the context of the view compiler.
        return @filemtime($path);
    }

    /**
     * Get all of the files from the given directory (recursive).
     *
     * @param  string  $directory
     * @return \SplFileInfo[]
     */
    public function allFiles(string $directory): array
    {
        return iterator_to_array(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)),
        );
    }

    /**
     * Recursively delete a directory.
     *
     * The directory itself may be optionally preserved.
     *
     * @param  string  $directory
     * @param  bool  $preserve
     * @return bool
     */
    public function deleteDirectory(string $directory, bool $preserve = false): bool
    {
        if (! $this->isDirectory($directory)) {
            return false;
        }

        $items = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);

        foreach ($items as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                $this->deleteDirectory($item->getPathname());
            } else {
                $this->delete($item->getPathname());
            }
        }

        return $preserve ?: rmdir($directory);
    }
}
