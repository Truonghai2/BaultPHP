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
 * A file watcher designed to run in a separate Swoole process.
 * It polls the filesystem for changes and reloads the Swoole server.
 * This method is reliable inside Docker containers on macOS and Windows.
 */
class FileWatcher
{
    private array $config;
    private LoggerInterface $logger;
    private SwooleHttpServer $server;
    private array $fileStates = [];
    private ?Process $process = null;

    public function __construct(SwooleHttpServer $server, array $config, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Creates the file watcher process.
     *
     * @return \Swoole\Process|null
     */
    public function createProcess(): ?Process
    {
        // Chỉ chạy watcher nếu polling được bật (cần thiết cho Docker)
        if (!($this->config['use_polling'] ?? false)) {
            $this->logger->info('File watcher is disabled because polling is not enabled in config/server.php.');
            return null;
        }

        $this->process = new Process(function (Process $worker) {
            try {
                $logPath = storage_path('logs/watcher.log');
                $watcherLogger = new Logger('watcher');
                $watcherLogger->pushProcessor(new ProcessIdProcessor());
                $watcherLogger->pushHandler(new StreamHandler($logPath, Logger::DEBUG));
                $this->logger = $watcherLogger;
                $this->logger->info('File watcher process started.', ['pid' => $worker->pid]);
            } catch (\Throwable $e) {
                $this->logger = new \Psr\Log\NullLogger();
            }

            $this->fileStates = $this->scanFiles();

            $interval = $this->config['interval'] ?? 1000;

            \Swoole\Timer::tick($interval, function () {
                $this->checkForChanges();
            });
        }, false, 0);

        return $this->process;
    }

    /**
     * Stops the file watcher process.
     */
    public function stop(): void
    {
        if ($this->process) {
            try {
                $this->logger->info('Stopping file watcher process...');
            } catch (\Throwable $e) {
                // ignore
            }
            Process::kill($this->process->pid);
        }
    }

    private function checkForChanges(): void
    {
        // Clear PHP's internal file stat cache to ensure we get fresh mtime.
        clearstatcache();

        $newStates = $this->scanFiles();

        $createdOrModified = array_diff_assoc($newStates, $this->fileStates);
        $deleted = array_diff_key($this->fileStates, $newStates);

        if (!empty($createdOrModified) || !empty($deleted)) {
            try {
                $this->logger->info('Change detected. Reloading Swoole server...');
            } catch (\Throwable $e) {
                // ignore
            }
            $this->server->reload();
            $this->fileStates = $newStates;
        }
    }

    /**
     * Creates and configures the Finder instance.
     * This is done once when the watcher starts to avoid re-parsing config on every tick.
     */
    private function createFinder(): Finder
    {
        $directories = $this->config['directories'] ?? [];
        $ignorePatterns = $this->config['ignore'] ?? [];

        $finder = new Finder();

        $filesToWatch = [];
        $dirsToWatch = [];
        foreach ($directories as $path) {
            if (is_file($path)) {
                $filesToWatch[] = $path;
            } elseif (is_dir($path)) {
                $dirsToWatch[] = $path;
            }
        }

        if (!empty($dirsToWatch)) {
            $finder->in($dirsToWatch);
        }

        if (!empty($filesToWatch)) {
            $finder->append($filesToWatch);
        }

        $finder->files()
               ->ignoreDotFiles(true)
               ->exclude($ignorePatterns);

        return $finder;
    }

    /**
     * Scans the filesystem using the pre-configured Finder instance.
     *
     * @return array<string, int>
     */
    private function scanFiles(): array
    {
        $finder = $this->createFinder();

        $fileStates = [];
        foreach ($finder as $file) {
            $realPath = $file->getRealPath();
            if (false !== $realPath) {
                $fileStates[$realPath] = $file->getMTime();
            }
        }
        return $fileStates;
    }
}
