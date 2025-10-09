<?php

namespace App\Providers;

use Core\Console\Commands\ScheduleRunCommand;
use Core\Console\ScheduleKernel;
use Core\Console\Scheduling\Scheduler;
use Core\Support\ServiceProvider;

class ScheduleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Scheduler::class);
        $this->app->singleton(ScheduleKernel::class);

        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->singleton(ScheduleRunCommand::class);
            $this->app->tag(ScheduleRunCommand::class, 'console.command');
        }
    }
}
