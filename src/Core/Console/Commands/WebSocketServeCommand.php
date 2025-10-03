<?php

namespace Core\Console\Commands;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\WebSocket\CentrifugoWorker;

class WebSocketServeCommand extends BaseCommand
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    public function signature(): string
    {
        return 'websocket:serve';
    }

    public function description(): string
    {
        return 'Starts the custom WebSocket worker for real-time communication.';
    }

    public function handle(): int
    {
        $this->io->title('Starting WebSocket Server');
        $this->io->info('Initializing WebSocket server...');
        $this->fire();
        return 0;
    }

    /**
     * The core logic of the command.
     */
    public function fire(): void
    {
        $worker = new CentrifugoWorker($this->app, $this->io);
        $worker->run();
    }
}
