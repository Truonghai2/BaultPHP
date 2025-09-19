<?php

namespace Core\Console\Commands\Server;

use Core\Console\Contracts\BaseCommand;
use Core\FileSystem\FilesystemWatcher;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ServeWatchCommand extends BaseCommand
{
    /**
     * Tên của command, dùng để gọi từ CLI.
     * @var string
     */
    protected string $signature = 'serve:watch {--host= : The host to bind the server to} {--port= : The port to bind the server to}';

    /**
     * Mô tả về command, sẽ hiển thị khi chạy "php cli list".
     * @var string
     */
    protected string $description = 'Run the Swoole server and watch for file changes (hot-reload).';

    /**
     * @var Process|null
     */
    private ?Process $serverProcess = null;

    /**
     * @var string|false
     */
    private string|false $phpPath;

    private bool $shouldQuit = false;

    private int $lastReloadTime = 0;

    public function __construct()
    {
        parent::__construct();
        $this->phpPath = (new PhpExecutableFinder())->find();
        if ($this->phpPath === false) {
            $this->error('Could not find the PHP executable.');
            exit(1);
        }
    }

    public function signature(): string
    {
        return $this->signature;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function handle(): int
    {
        $this->info('Starting server with hot-reload enabled...');

        $this->registerSignalHandler();

        $this->restartServer();

        $watcher = new FileSystemWatcher();

        $watcher
            ->paths($this->getWatchPaths())
            ->ignore($this->getIgnorePaths())
            ->extensions(config('server.watch.extensions', ['php', 'env', 'blade.php']))
            ->setLoopCondition(fn () => ! $this->shouldQuit)
            ->onStateChange(function (string $type, string $path) {
                // Debounce: Chỉ reload mỗi 500ms để tránh bão reload khi lưu nhiều file.
                $now = (int) (microtime(true) * 1000);
                if ($now - $this->lastReloadTime < 500) {
                    return;
                }

                $this->lastReloadTime = $now;

                $relativePath = str_replace(base_path() . '/', '', $path);
                $this->line(sprintf(
                    '<fg=yellow>[%s]</> File %s: <fg=gray>%s</>',
                    strtoupper($type),
                    ucfirst($type),
                    $relativePath,
                ));
                $this->info('Reloading server...');
                $this->restartServer();
            })
            ->start();

        $this->info('Hot-reload server stopped.');
        return self::SUCCESS;
    }

    private function restartServer(): void
    {
        if ($this->serverProcess && $this->serverProcess->isRunning()) {
            $this->comment("Stopping server process (PID: {$this->serverProcess->getPid()})...");
            $this->serverProcess->stop(5, SIGTERM);
        }

        $command = [$this->phpPath, base_path('cli'), 'serve:start'];

        if ($host = $this->option('host')) {
            $command[] = '--host=' . $host;
        }
        if ($port = $this->option('port')) {
            $command[] = '--port=' . $port;
        }

        $this->serverProcess = new Process($command, base_path(), null, null, null);

        $this->serverProcess->start(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->error($buffer);
            } else {
                $this->rawOutput($buffer);
            }
        });
    }

    /**
     * Lấy danh sách các đường dẫn cần theo dõi.
     * @return string[]
     */
    private function getWatchPaths(): array
    {
        return config('server.watch.directories', [base_path('src'), base_path('routes')]);
    }

    /**
     * Lấy danh sách các đường dẫn cần bỏ qua.
     */
    private function getIgnorePaths(): array
    {
        // Đọc cấu hình từ file config
        return config('server.watch.ignore', [storage_path(), base_path('vendor')]);
    }

    /**
     * Ghi output thô ra console mà không thêm tiền tố hay định dạng.
     */
    private function rawOutput(string $string): void
    {
        $this->output->write($string);
    }

    /**
     * Đăng ký xử lý tín hiệu để dừng worker một cách an toàn.
     */
    private function registerSignalHandler(): void
    {
        if (!extension_loaded('pcntl')) {
            $this->warn('PCNTL extension is not available. Graceful shutdown (Ctrl+C) may not work correctly.');
            return;
        }

        pcntl_async_signals(true);

        $handler = function () {
            $this->warn('Shutdown signal received.');
            $this->shouldQuit = true;

            if ($this->serverProcess && $this->serverProcess->isRunning()) {
                $this->serverProcess->stop(5, SIGTERM);
                $this->info('Server process stopped.');
            }
        };

        pcntl_signal(SIGINT, $handler); // Ctrl+C
        pcntl_signal(SIGTERM, $handler); // Tín hiệu từ process manager
    }
}
