<?php

namespace Core\Console\Commands\Server;

use Core\Console\Contracts\BaseCommand;
use Symfony\Component\Process\PhpExecutableFinder;

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
     * @var string|false
     */
    private string|false $phpPath;

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
        $this->info('Starting server with hot-reload enabled (via Swoole native watcher)...');

        $this->comment('Clearing all application caches...');
        $this->callCommand(\Core\Console\Commands\Cache\CacheClearCommand::class);
        $this->comment('Optimizing application for development watch mode...');
        $this->call('optimize');

        $args = [base_path('cli'), 'serve:start', '--watch'];

        if ($host = $this->option('host')) {
            $args[] = '--host=' . $host;
        }
        if ($port = $this->option('port')) {
            $args[] = '--port=' . $port;
        }

        $this->info('Handing over to Swoole server...');
        $this->line('');

        if (!\function_exists('pcntl_exec')) {
            $this->error('The "pcntl" extension is required to run serve:watch correctly. Please install it.');
            return self::FAILURE;
        }

        pcntl_exec($this->phpPath, $args);

        $this->error('Failed to execute server process. Make sure pcntl_exec is allowed.');
        return self::FAILURE;
    }
}
