<?php

namespace App\Providers;

use Core\Application;
use Core\Events\EventStreamProcessor;
use Core\Events\ProjectionRunner;
use Core\Events\EventStore;
use Psr\Log\LoggerInterface;

/**
 * Projection Service Provider.
 * 
 * Registers event stream projections.
 */
class ProjectionServiceProvider
{
    public function __construct(
        protected Application $app
    ) {
    }

    public function register(): void
    {
        // Register EventStreamProcessor
        $this->app->singleton(EventStreamProcessor::class, function ($app) {
            $processor = new EventStreamProcessor(
                $app->make(EventStore::class),
                $app->make(LoggerInterface::class),
                $app
            );

            // Register all configured projections
            $projections = config('projections.projections', []);
            foreach ($projections as $projectionClass) {
                $processor->registerProjection($projectionClass);
            }

            return $processor;
        });

        // Register ProjectionRunner
        $this->app->singleton(ProjectionRunner::class, function ($app) {
            return new ProjectionRunner(
                $app->make(EventStreamProcessor::class),
                $app->make(LoggerInterface::class),
                $app
            );
        });
    }

    public function boot(): void
    {
        // Auto-start projections if enabled
        if (config('projections.auto_start', false)) {
            $runner = $this->app->make(ProjectionRunner::class);
            
            // Catch up to current position
            $runner->catchUp();
        }
    }
}
