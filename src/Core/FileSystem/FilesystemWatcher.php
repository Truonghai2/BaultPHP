<?php

namespace Core\FileSystem;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FilesystemWatcher
{
    protected array $paths = [];
    protected array $ignore = [];
    protected array $extensions = [];
    protected ?Closure $loopCondition = null;
    protected ?Closure $onStateChangeCallback = null;
    protected array $initialFileStates = [];

    public function paths(array $paths): self
    {
        $this->paths = $paths;
        return $this;
    }

    public function ignore(array $ignore): self
    {
        $this->ignore = $ignore;
        return $this;
    }

    public function extensions(array $extensions): self
    {
        $this->extensions = array_map(fn ($ext) => ltrim($ext, '.'), $extensions);
        return $this;
    }

    public function setLoopCondition(Closure $loopCondition): self
    {
        $this->loopCondition = $loopCondition;
        return $this;
    }

    public function onStateChange(Closure $callback): self
    {
        $this->onStateChangeCallback = $callback;
        return $this;
    }

    public function start(): void
    {
        $this->initialFileStates = $this->getCurrentFileStates();

        while (($this->loopCondition)() === true) {
            usleep(500000); // Check every 0.5 seconds

            $currentFileStates = $this->getCurrentFileStates();

            $changedFile = $this->getChangedFile($currentFileStates);

            if ($changedFile !== false) {
                ($this->onStateChangeCallback)('modified', $changedFile); // Pass the actual changed file path
                $this->initialFileStates = $currentFileStates;
            }
        }
    }

    protected function getCurrentFileStates(): array
    {
        $fileStates = [];
        foreach ($this->paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $this->shouldWatch($file->getPathname())) {
                    $fileStates[$file->getPathname()] = $file->getMTime();
                }
            }
        }
        return $fileStates;
    }

    protected function shouldWatch(string $filePath): bool
    {
        // Check extensions
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!empty($this->extensions) && !in_array($extension, $this->extensions)) {
            return false;
        }

        // Check ignore paths
        foreach ($this->ignore as $ignorePath) {
            $realIgnorePath = realpath($ignorePath);
            if ($realIgnorePath && str_starts_with($filePath, $realIgnorePath)) {
                return false;
            }
        }

        return true;
    }

    protected function getChangedFile(array $currentFileStates): string|false
    {
        // Check for modified or new files
        foreach ($currentFileStates as $filePath => $mTime) {
            if (!isset($this->initialFileStates[$filePath]) || $this->initialFileStates[$filePath] !== $mTime) {
                return $filePath;
            }
        }

        // Check for deleted files
        foreach ($this->initialFileStates as $filePath => $mTime) {
            if (!isset($currentFileStates[$filePath])) {
                return $filePath;
            }
        }

        return false;
    }
}
