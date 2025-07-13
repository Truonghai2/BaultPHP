<?php

namespace Core\Console;

use Core\Application;
use Core\WebSocket\WebSocketWorker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WebSocketServeCommand extends Command
{
    protected static $defaultName = 'websocket:serve';
    protected static $defaultDescription = 'Starts the WebSocket server worker.';

    private Application $app;

    public function __construct(Application $app)
    {
        parent::__construct();
        $this->app = $app;
    }

    protected function configure(): void
    {
        $this->setHelp('This command runs the RoadRunner worker to handle WebSocket connections and Redis Pub/Sub events.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $worker = new WebSocketWorker($this->app, $output);
        $worker->run();

        return Command::SUCCESS;
    }
}