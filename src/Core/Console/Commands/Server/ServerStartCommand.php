<?php

namespace Core\Console\Commands\Server;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\Server\SwooleServer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ServerStartCommand extends BaseCommand
{
    protected Application $app;

    public function __construct(Application $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    public function signature(): string
    {
        return 'serve:start {--host= : The host to bind the server to} {--port= : The port to bind the server to}';
    }

    public function description(): string
    {
        return 'Starts the Swoole HTTP server.';
    }

    /**
     * Overriding execute for better access to Input/Output and consistency.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!extension_loaded('swoole')) {
            $this->error('The Swoole extension is not installed or enabled. This command cannot function without it.');
            return self::FAILURE;
        }

        $this->overrideConfigWithOptions($input);

        // The PID check logic is now handled within SwooleServer's configuration preparation,
        // but we can keep a preliminary check here to provide a friendlier error message
        // without attempting to start the server.
        $pidFile = config('server.swoole.pid_file');
        if ($pidFile && file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);
            if ($pid && function_exists('posix_kill') && posix_kill($pid, 0)) {
                $host = config('server.swoole.host', '127.0.0.1');
                $port = config('server.swoole.port', 9501);
                $this->error("Swoole server is already running on {$host}:{$port} with PID {$pid}.");
                return self::FAILURE;
            }
            // Clean up stale PID file if process is not running
            unlink($pidFile);
        }

        $this->info('Preparing to start Swoole HTTP server...');

        /** @var SwooleServer $server */
        $server = $this->app->make(SwooleServer::class);
        $server->start();

        return self::SUCCESS;
    }

    /**
     * Override server configuration with command-line options.
     *
     * @param InputInterface $input
     */
    protected function overrideConfigWithOptions(InputInterface $input): void
    {
        $config = $this->app->make('config');

        if ($host = $input->getOption('host')) {
            $config->set('server.swoole.host', $host);
        }

        if ($port = $input->getOption('port')) {
            $config->set('server.swoole.port', (int)$port);
        }

        // The `serve:start` command should always run in the foreground.
        // The process manager (like Docker or Supervisor) or the user's shell
        // is responsible for backgrounding. This prevents unexpected daemonization.
        $config->set('server.swoole.daemonize', false);
    }

    public function handle(): int
    {
        // The `execute` method is used instead, so this can return a default success.
        return self::SUCCESS;
    }
}
