<?php

namespace Core\Console\Commands;

use Core\Console\Contracts\BaseCommand;
use Core\WebSocket\WebSocketServer;

class WebSocketServerCommand extends BaseCommand
{
    /**
     * The signature of the command.
     */
    public function signature(): string
    {
        return 'ws:serve {--port=9502 : The port for the WebSocket server to listen on.}';
    }

    /**
     * The description of the command.
     */
    public function description(): string
    {
        return 'Starts the custom WebSocket server.';
    }

    /**
     * The handler for the command.
     */
    public function handle(): int
    {
        if (!extension_loaded('swoole')) {
            $this->error('The Swoole extension is not installed or enabled. This command cannot function without it.');
            return self::FAILURE;
        }

        $this->info('Starting WebSocket server...');

        $host = config('server.swoole.host', '0.0.0.0');
        $port = (int) $this->option('port');

        $this->comment("Server will listen on <info>ws://{$host}:{$port}</info>");

        $server = new WebSocketServer($this->app, $host, $port);

        $server->start();

        return self::SUCCESS;
    }
}
