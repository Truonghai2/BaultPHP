<?php

namespace Modules\User\Providers;

use Core\Support\ServiceProvider;
use Modules\User\Console\AclSyncPermissionsCommand;

class ConsoleServiceProvider extends ServiceProvider
{
    protected array $commands = [
        AclSyncPermissionsCommand::class,
    ];

    public function register(): void
    {
        foreach ($this->commands as $commandClass) {
            $this->app->singleton($commandClass);
        }
    }
}