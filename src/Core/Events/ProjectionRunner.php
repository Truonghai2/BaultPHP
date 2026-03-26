<?php

namespace Core\Events;

use Core\Application;
use Psr\Log\LoggerInterface;

/**
 * Projection Runner.
 * 
 * Manages projection lifecycle:
 * - Initial build
 * - Continuous updates
 * - Reset/rebuild
 */
class ProjectionRunner
{
    public function __construct(
        private EventStreamProcessor $processor,
        private LoggerInterface $logger,
        private Application $app,
    ) {
    }

    /**
     * Build all projections from scratch.
     */
    public function buildAll(): void
    {
        $this->logger->info("Building all projections from scratch");

        $projections = $this->processor->getProjections();

        // Reset all projections
        foreach ($projections as $projectionClass) {
            $projection = $this->app->make($projectionClass);
            
            $this->logger->info("Resetting projection: {$projectionClass}");
            $projection->reset();
        }

        // Process all events
        $this->processor->processFrom(0);

        $this->logger->info("All projections built successfully");
    }

    /**
     * Rebuild specific projection.
     */
    public function rebuild(string $projectionClass): void
    {
        $this->logger->info("Rebuilding projection: {$projectionClass}");

        $projection = $this->app->make($projectionClass);
        
        // Reset projection
        $projection->reset();

        // Temporary: Only process this projection
        $originalProjections = $this->processor->getProjections();
        $this->processor->registerProjection($projectionClass);

        // Process all events
        $this->processor->processFrom(0);

        // Restore original projections
        foreach ($originalProjections as $proj) {
            if ($proj !== $projectionClass) {
                $this->processor->registerProjection($proj);
            }
        }

        $this->logger->info("Projection rebuilt: {$projectionClass}");
    }

    /**
     * Run projections continuously.
     */
    public function runContinuously(int $pollInterval = 1000): void
    {
        $this->logger->info("Starting continuous projection updates");
        $this->processor->run($pollInterval);
    }

    /**
     * Catch up projections to current position.
     */
    public function catchUp(): void
    {
        $position = $this->processor->getCheckpoint();
        $this->logger->info("Catching up projections from position {$position}");
        
        $this->processor->processFrom($position);
    }
}
