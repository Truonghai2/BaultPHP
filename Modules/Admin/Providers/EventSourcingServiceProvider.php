<?php

declare(strict_types=1);

namespace Modules\Admin\Providers;

use Core\ServiceProvider;
use Modules\Admin\Infrastructure\Models\Module;
use Modules\Admin\Infrastructure\Observers\ModuleEventSourcingObserver;

/**
 * Event Sourcing Service Provider for Admin Module
 */
class EventSourcingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            \Modules\Admin\Application\Services\ModuleAggregateService::class
        );
    }

    public function boot(): void
    {
        /** @var \Core\EventSourcing\ModuleConfigLoader $configLoader */
        $configLoader = $this->app->make(\Core\EventSourcing\ModuleConfigLoader::class);
        
        if (!$configLoader->isEnabled('Admin')) {
            return;
        }

        // Register observers
        $this->registerObservers($configLoader);

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->registerCommands($configLoader);
        }
    }

    private function registerObservers($configLoader): void
    {
        if (!$configLoader->isAutoRecordEnabled('Admin')) {
            return;
        }

        $aggregates = $configLoader->getEnabledAggregates('Admin');

        foreach ($aggregates as $name => $config) {
            if (isset($config['observer']) && $name === 'module') {
                if (class_exists($config['observer'])) {
                    Module::observe($config['observer']);
                }
            }
        }
    }

    private function registerCommands($configLoader): void
    {
        $commands = $configLoader->get('Admin', 'commands', []);
        
        if (!empty($commands)) {
            $this->commands($commands);
        }
    }
}

