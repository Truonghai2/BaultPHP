<?php

namespace Modules\Todo\Application\EventHandlers;

use Modules\Todo\Domain\Events\TodoCreated;
use Modules\Todo\Infrastructure\Repositories\TodoReadRepository;

/**
 * Event Handler: Update Read Model when Todo is created.
 * 
 * Implements eventual consistency pattern.
 */
class TodoCreatedEventHandler
{
    public function __construct(
        private TodoReadRepository $readRepo
    ) {}

    /**
     * Handle the TodoCreated event.
     */
    public function handle(TodoCreated $event): void
    {
        // Clear cache for the user
        // The next read will fetch fresh data from write store
        $this->readRepo->clearCacheForUser($event->userId);

        // Optional: Could also copy to dedicated read database here
        // For now, read and write share the same database
    }
}
