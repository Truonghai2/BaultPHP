<?php

namespace Core\Filesystem;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * A simple filesystem utility class.
 * This class provides a convenient, object-oriented wrapper around common
 * PHP filesystem functions, making them easier to use and test.
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
     * Get the contents of a file.
     *
     * @param string $path
     * @return string
     *
     * @throws \RuntimeException If the file does not exist or cannot be read.
     */
    public function get(string $path): string
    {
        if (!$this->isFile($path)) {
            throw new RuntimeException("File does not exist at path {$path}.");
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Could not get contents of file at path {$path}.");
        }

        return $contents;
    }

    /**
     * Write the contents to a file.
     *
     * @param  string  $path
     * @param  string  $contents
     * @param  bool  $lock
     * @return int|false
     */
    public function put(string $path, string $contents, bool $lock = false): int|false
    {
        $this->ensureDirectoryExists(dirname($path));

        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    /**
     * Get the file's last modification time.
     *
     * @param  string  $path
     * @return int|false
     */
    public function lastModified(string $path): int|false
    {
        return @filemtime($path);
    }

    /**
     * Delete the file at a given path.
     *
     * @param string|string[] $paths
     * @return bool
     */
    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : func_get_args();
        $success = true;

        foreach ($paths as $path) {
            if (@unlink($path)) {
                clearstatcache(true, $path);
            } else {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Move a directory from one location to another.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function moveDirectory(string $from, string $to): bool
    {
        if (!$this->isDirectory($from)) {
            return false;
        }

        return rename($from, $to);
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
     * Ensure a directory exists before writing a file.
     *
     * @param  string  $path
     * @param  int  $mode
     * @return void
     */
    public function ensureDirectoryExists(string $path, int $mode = 0755): void
    {
        if (!$this->isDirectory($path)) {
            // Use `makeDirectory` with `recursive` and `force` to ensure the
            // entire path is created safely.
            $this->makeDirectory($path, $mode, true, true);
        }
    }

    /**
     * Get all of the files from the given directory (recursive).
     *
     * @param  string  $directory
     * @return \SplFileInfo[]
     */
    public function allFiles(string $directory): array
    {
        if (!$this->isDirectory($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
