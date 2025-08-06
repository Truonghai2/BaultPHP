<?php

namespace Core\Console\Commands\Server;

use Core\Console\Contracts\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;

class ServeCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'serve {--host=127.0.0.1 : The host to bind the server to} {--port=9501 : The port to bind the server to}';
    }

    public function description(): string
    {
        return 'Starts the Swoole HTTP server (alias for server:start)';
    }

    /**
     * The `handle` method in the base class seems to be called without the OutputInterface context.
     * To fix this, we override the `execute` method directly, which is guaranteed by Symfony
     * to receive the correct input and output objects.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!extension_loaded('swoole')) {
            // Fail early to provide a better user experience.
            $this->error('The Swoole extension is not installed or enabled. This command cannot function without it.');
            return self::FAILURE;
        }

        $host = $input->getOption('host') ?? '127.0.0.1';
        $port = $input->getOption('port') ?? 9501;

        $config = app('config');
        $config->set('server.swoole.host', $host);
        $config->set('server.swoole.port', $port);

        if (!config('server.swoole.daemonize')) {
            // We use the $output object directly instead of $this->comment()
            // to avoid any potential issues with the BaseCommand's implementation.
            $output->writeln('<comment>Press Ctrl+C to stop the server.</comment>');
        }

        $command = $this->getApplication()->find('server:start');
        $startInput = new ArrayInput([]);
        return $command->run($startInput, $output);
    }

    public function handle(): int
    {
        return 0;
    }
}