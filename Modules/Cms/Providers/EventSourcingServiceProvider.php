<?php

declare(strict_types=1);

namespace Modules\Cms\Providers;

use Core\BaseServiceProvider;
use Modules\Cms\Infrastructure\Observers\PageEventSourcingObserver;
use Modules\Cms\Infrastructure\Observers\PageBlockEventSourcingObserver;

/**
 * Event Sourcing Service Provider for CMS Module
 * 
 * Registers observers to automatically record events when models change
 */
class EventSourcingServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        // Register services
        $this->app->singleton(
            \Modules\Cms\Application\Services\PageAggregateService::class
        );
        
        $this->app->singleton(
            \Modules\Cms\Application\Services\PageBlockAggregateService::class
        );
        
        $this->app->singleton(
            \Modules\Cms\Domain\Services\PageDomainService::class
        );
    }

    public function boot(): void
    {
        /** @var \Core\EventSourcing\ModuleConfigLoader $configLoader */
        $configLoader = $this->app->make(\Core\EventSourcing\ModuleConfigLoader::class);
        
        // Check if event sourcing is enabled for CMS module
        if (!$configLoader->isEnabled('Cms')) {
            return;
        }

        // Register model observers based on module config
        $this->registerObservers($configLoader);

        // Register console commands from module config
        if ($this->app->runningInConsole()) {
            $this->registerCommands($configLoader);
        }
    }

    private function registerObservers($configLoader): void
    {
        if (!$configLoader->isAutoRecordEnabled('Cms')) {
            return;
        }

        $aggregates = $configLoader->getEnabledAggregates('Cms');

        foreach ($aggregates as $name => $config) {
            $modelClass = $this->getModelForAggregate($name);
            $observerClass = $this->getObserverForAggregate($name);

            if ($modelClass && $observerClass && class_exists($modelClass) && class_exists($observerClass)) {
                $modelClass::observe($observerClass);
            }
        }
    }

    private function registerCommands($configLoader): void
    {
        $commands = $configLoader->get('Cms', 'commands', []);
        
        if (!empty($commands)) {
            $this->commands($commands);
        }
    }

    private function getModelForAggregate(string $aggregateName): ?string
    {
        $models = [
            'page' => \Modules\Cms\Infrastructure\Models\Page::class,
            'block' => \Modules\Cms\Infrastructure\Models\PageBlock::class,
        ];

        return $models[$aggregateName] ?? null;
    }

    private function getObserverForAggregate(string $aggregateName): ?string
    {
        $observers = [
            'page' => PageEventSourcingObserver::class,
            'block' => PageBlockEventSourcingObserver::class,
        ];

        return $observers[$aggregateName] ?? null;
    }
}
