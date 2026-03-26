<?php

namespace Modules\Todo\Application\EventHandlers;

use Modules\Todo\Domain\Events\{TodoCompleted, TodoUncompleted};
use Modules\Todo\Infrastructure\Repositories\TodoReadRepository;

/**
 * Event Handler: Update Read Model when Todo status changes.
 */
class TodoCompletedEventHandler
{
    public function __construct(
        private TodoReadRepository $readRepo
    ) {}

    /**
     * Handle TodoCompleted or TodoUncompleted events.
     */
    public function handle(TodoCompleted|TodoUncompleted $event): void
    {
        // Clear cache for this specific todo
        $this->readRepo->clearCacheForTodo($event->todoId);

        // Also clear user's todo list cache
        // We'd need to fetch the todo to get userId, or include it in the event
        // For simplicity, we just clear the specific todo cache
        // The next read will fetch fresh data
    }
}
