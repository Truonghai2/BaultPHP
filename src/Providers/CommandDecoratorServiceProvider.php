<?php

namespace App\Providers;

use Core\CLI\FailedCommand;
use Core\CLI\LoggingCommandDecorator;
use Core\Console\Contracts\CommandInterface;
use Core\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;

class CommandDecoratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Decorate console commands with a logger, if a logger is available.
        $this->app->afterResolving(Command::class, function (Command $command) {
            if (!$command instanceof FailedCommand &&
                $command instanceof CommandInterface && // Đảm bảo CommandInterface được implement
                $this->app->bound(LoggerInterface::class)
            ) {
                $logger = $this->app->make(LoggerInterface::class);
                return new LoggingCommandDecorator($command, $logger);
            }
            return $command;
        });
    }

    // No boot method is needed as decoration happens on resolution.
}
