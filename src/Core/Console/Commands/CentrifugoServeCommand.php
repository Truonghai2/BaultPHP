<?php


namespace Core\Console\Commands;

use Core\Application;
use Core\Console\Contracts\BaseCommand;
use Core\WebSocket\CentrifugoWorker;

class CentrifugoServeCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected string $signature = 'centrifugo:serve';

    /**
     * The console command description.
     */
    protected string $description = 'Starts the Centrifugo proxy worker.';

    
    public function signature(): string
    {
        return $this->signature;
    }
    public function description(): string
    {
        return $this->description;
    }

    /**
     * The core logic of the command.
     * This method starts the Centrifugo worker.
     */
    public function handle(array $args = []): int
    {
        $this->io->title('Starting Centrifugo Worker');
        $this->io->info('Initializing Centrifugo worker...');
        $this->fire();
        return 0;
    }

    /**
     * The entry point for the command.
     * This method runs the Centrifugo worker.
     */
    public function fire(): void
    {
        $worker = new CentrifugoWorker($this->app, $this->io);
        $worker->run();
    }
}

