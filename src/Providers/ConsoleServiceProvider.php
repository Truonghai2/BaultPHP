<?php

namespace App\Providers;

use Core\CLI\FailedCommand;
use Core\Console\Commands\Queue\WorkCommand;
use Core\Console\Contracts\CommandInterface;
use Core\Support\ServiceProvider;
use Symfony\Component\Console\Command\Command;

class ConsoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Tag tất cả các lớp Command để có thể resolve chúng sau này.
        $this->app->afterResolving(Command::class, function ($command) {
            if ($command instanceof Command && !$command instanceof FailedCommand) {
                // Kiểm tra xem command có implement interface ConsoleCommand hay không
                $reflection = new \ReflectionClass($command);
                if ($reflection->implementsInterface(CommandInterface::class)) {
                    $this->app->tag(get_class($command), 'console.command');
                }
            }
        });

        $this->app->singleton(\Core\Console\Kernel::class);

        // Tag command của chúng ta để Kernel có thể tìm thấy
        $this->app->tag(WorkCommand::class, 'console.command');
    }
}
