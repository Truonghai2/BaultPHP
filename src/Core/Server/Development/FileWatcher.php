<?php

namespace Core\Server\Development;

use Psr\Log\LoggerInterface;
use Spatie\Watcher\Watch;
use Swoole\Http\Server as SwooleHttpServer;
use Symfony\Component\Process\Process;

/**
 * Manages the file watcher for hot-reloading during development.
 * This class encapsulates the logic for starting, monitoring, and stopping
 * the file watcher process, and triggering a graceful "rolling restart"
 * of the Swoole workers when a file change is detected.
 */
class FileWatcher
{
    protected ?Process $process = null;
    protected ?int $timerId = null;
    protected bool $reloading = false;

    public function __construct(
        protected SwooleHttpServer $server,
        protected array $watchConfig,
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * Starts the file watcher process in a separate coroutine.
     */
    public function start(): void
    {
        $this->logger->info('Custom hot-reload is enabled. Starting file watcher...');

        \Swoole\Coroutine::create(function () {
            try {
                $paths = $this->getWatchablePaths();
                if (empty($paths)) {
                    $this->logger->warning('Hot-reload enabled, but no valid directories to watch.');
                    return;
                }

                $this->process = Watch::paths($paths)
                    ->ignorePaths($this->getIgnoredPaths())
                    ->getProcess();

                $this->process->start();

                $this->logger->info('File watcher process started.', ['pid' => $this->process->getPid()]);

                // Use a Swoole timer to check for watcher output asynchronously.
                $this->timerId = \Swoole\Timer::tick(500, [$this, 'checkForChanges']);
            } catch (\Throwable $e) {
                $this->logger->error('File watcher failed to start.', ['exception' => $e]);
            }
        });
    }

    /**
     * Stops the file watcher process and clears the timer.
     */
    public function stop(): void
    {
        if ($this->process && $this->process->isRunning()) {
            $this->process->stop();
            $this->logger->info('File watcher process stopped.');
        }
        if ($this->timerId) {
            \Swoole\Timer::clear($this->timerId);
            $this->timerId = null;
        }
    }

    /**
     * Periodically checks for output from the watcher process.
     * This method is called by the Swoole timer.
     */
    protected function checkForChanges(): void
    {
        try {
            if (!$this->process || !$this->process->isRunning()) {
                $this->logger->warning('File watcher process has stopped unexpectedly.');
                $this->stop();
                return;
            }

            $output = $this->process->getIncrementalOutput();
            if (!empty($output)) {
                $lines = explode(PHP_EOL, trim($output));
                foreach ($lines as $line) {
                    if (empty($line)) {
                        continue;
                    }
                    $eventData = json_decode($line, true);
                    if (is_array($eventData) && isset($eventData['path'])) {
                        $this->handleFileChange($eventData['path']);
                        // Only need one event to trigger a reload, so break early.
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error checking for file changes.', ['exception' => $e]);
        }
    }

    /**
     * Handles the file change event by triggering a rolling restart of workers.
     */
    protected function handleFileChange(string $path): void
    {
        if ($this->reloading) {
            return;
        }

        $this->reloading = true;
        $this->logger->info('File change detected, starting rolling reload...', ['path' => $path]);

        $workers = $this->server->workers;
        if (empty($workers)) {
            $this->reloading = false;
            return;
        }

        // Perform a "rolling restart" in a new coroutine to avoid blocking.
        \Swoole\Coroutine::create(function () use ($workers) {
            foreach ($workers as $workerId => $workerPid) {
                $this->logger->debug("Reloading worker #{$workerId} (PID: {$workerPid})");
                if (\Swoole\Process::kill($workerPid, SIGUSR1)) {
                    \Swoole\Coroutine::sleep(0.5); // Wait 500ms between worker reloads
                } else {
                    $this->logger->warning("Failed to send reload signal to worker #{$workerId}");
                }
            }
            $this->logger->info('Rolling reload completed.');
            \Swoole\Coroutine::sleep(1); // Wait 1 second before allowing another reload.
            $this->reloading = false;
        });
    }

    /**
     * Gets the list of valid directories to watch from the configuration.
     */
    protected function getWatchablePaths(): array
    {
        $paths = [];
        $directories = $this->watchConfig['directories'] ?? [];
        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                $paths[] = $dir;
            }
        }
        return $paths;
    }

    /**
     * Gets the list of paths to ignore from the configuration.
     * @return array
     */
    protected function getIgnoredPaths(): array
    {
        return $this->watchConfig['ignore'] ?? [];
    }
}
