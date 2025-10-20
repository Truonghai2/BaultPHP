<?php

namespace Core\Server\Development;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\ProcessIdProcessor;
use Psr\Log\LoggerInterface;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Process;
use Symfony\Component\Finder\Finder;

/**
 * Docker-optimized file watcher with enhanced polling and error handling
 */
class DockerFileWatcher
{
    private array $config;
    private LoggerInterface $logger;
    private SwooleHttpServer $server;
    private array $fileStates = [];
    private ?Process $process = null;
    private int $pollingInterval;
    private array $lastScanTime = [];

    public function __construct(SwooleHttpServer $server, array $config, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->config = $config;
        $this->logger = $logger;

        // Optimize polling interval for Docker
        $this->pollingInterval = $this->config['interval'] ?? 500; // Faster polling for Docker
    }

    /**
     * Creates the file watcher process with Docker optimizations
     */
    public function createProcess(): ?Process
    {
        // Always enable polling for Docker
        if (!($this->config['use_polling'] ?? true)) {
            $this->logger->warning('File watcher polling is disabled. This may not work properly in Docker.');
        }

        $this->process = new Process(function (Process $worker) {
            try {
                $logPath = storage_path('logs/watcher.log');
                $watcherLogger = new Logger('docker-watcher');
                $watcherLogger->pushProcessor(new ProcessIdProcessor());
                $watcherLogger->pushHandler(new StreamHandler($logPath, Logger::DEBUG));
                $this->logger = $watcherLogger;

                $this->logger->info('Docker file watcher process started.', [
                    'pid' => $worker->pid,
                    'polling_interval' => $this->pollingInterval,
                    'directories' => $this->config['directories'] ?? [],
                ]);
            } catch (\Throwable $e) {
                $this->logger = new \Psr\Log\NullLogger();
                error_log('Failed to initialize watcher logger: ' . $e->getMessage());
            }

            // Initial scan
            $this->fileStates = $this->scanFiles();
            $this->logger->info('Initial file scan completed.', [
                'files_count' => count($this->fileStates),
            ]);

            // Start polling with optimized interval
            \Swoole\Timer::tick($this->pollingInterval, function () {
                $this->checkForChanges();
            });
        }, false, 0);

        return $this->process;
    }

    /**
     * Enhanced change detection with Docker-specific optimizations
     */
    private function checkForChanges(): void
    {
        try {
            // Clear PHP's internal file stat cache
            clearstatcache(true);

            $newStates = $this->scanFiles();
            $changes = $this->detectChanges($newStates);

            if (!empty($changes)) {
                $this->logger->info('File changes detected, reloading server...', [
                    'changes' => $changes,
                    'total_files' => count($newStates),
                ]);

                $this->server->reload();
                $this->fileStates = $newStates;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error in file watcher: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Detect changes with better performance for Docker
     */
    private function detectChanges(array $newStates): array
    {
        $changes = [];

        // Check for modified/new files
        foreach ($newStates as $filePath => $mTime) {
            if (!isset($this->fileStates[$filePath])) {
                $changes[] = ['type' => 'created', 'file' => $filePath];
            } elseif ($this->fileStates[$filePath] !== $mTime) {
                $changes[] = ['type' => 'modified', 'file' => $filePath];
            }
        }

        // Check for deleted files
        foreach ($this->fileStates as $filePath => $mTime) {
            if (!isset($newStates[$filePath])) {
                $changes[] = ['type' => 'deleted', 'file' => $filePath];
            }
        }

        return $changes;
    }

    /**
     * Optimized file scanning for Docker environments
     */
    private function scanFiles(): array
    {
        $fileStates = [];
        $directories = $this->config['directories'] ?? [];
        $ignorePatterns = $this->config['ignore'] ?? [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            try {
                $finder = new Finder();
                $finder->files()
                       ->in($directory)
                       ->ignoreDotFiles(true)
                       ->exclude($ignorePatterns)
                       ->name('*.php')
                       ->name('*.blade.php')
                       ->name('*.js')
                       ->name('*.css')
                       ->name('*.env');

                foreach ($finder as $file) {
                    $realPath = $file->getRealPath();
                    if ($realPath !== false) {
                        $fileStates[$realPath] = $file->getMTime();
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning("Failed to scan directory: {$directory}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $fileStates;
    }

    /**
     * Stop the file watcher process
     */
    public function stop(): void
    {
        if ($this->process) {
            try {
                $this->logger->info('Stopping Docker file watcher process...');
            } catch (\Throwable $e) {
                // ignore
            }
            Process::kill($this->process->pid);
        }
    }

    /**
     * Get watcher status for debugging
     */
    public function getStatus(): array
    {
        return [
            'is_running' => $this->process !== null,
            'polling_interval' => $this->pollingInterval,
            'files_watched' => count($this->fileStates),
            'directories' => $this->config['directories'] ?? [],
            'use_polling' => $this->config['use_polling'] ?? true,
        ];
    }
}
