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
        return 'serve:start {--host= : The host to bind the server to} {--port= : The port to bind the server to} {--daemon|-d : Run the server in daemon mode (Not recommended in Docker)} {--watch : Enable Swoole native file watcher (for internal use)}';
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

        if ($input->getOption('daemon')) {
            $pidFile = config('server.swoole.pid_file');
            if ($pidFile && file_exists($pidFile) && $pid = (int) file_get_contents($pidFile)) {
                $host = config('server.swoole.host', '0.0.0.0');
                $port = config('server.swoole.port', 9501);
                $this->error("Swoole server is already running on {$host}:{$port} with PID {$pid}.");
                return self::FAILURE;
            }
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
        }

        if (config('server.swoole.daemonize')) {
            $this->info('Starting Swoole HTTP server in daemon mode...');
        } else {
            $this->info('Starting Swoole HTTP server...');
        }

        /** @var SwooleServer $server */
        $server = $this->app->make(SwooleServer::class);
        $server->start();

        return self::SUCCESS;
    }

    protected function overrideConfigWithOptions(InputInterface $input): void
    {
        $config = $this->app->make('config');

        if ($host = $input->getOption('host')) {
            $config->set('server.swoole.host', $host);
        }

        if ($port = $input->getOption('port')) {
            $config->set('server.swoole.port', (int)$port);
        }

        if ($input->getOption('daemon')) {
            $config->set('server.swoole.daemonize', true);
        }
    }

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
