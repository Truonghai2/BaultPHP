<?php

namespace Modules\Todo\Projections;

use Core\Events\Event;
use Core\Events\Projection;
use Modules\Todo\Domain\Events\{TodoCreated, TodoCompleted, TodoUncompleted};
use Psr\Log\LoggerInterface;

/**
 * Todo Statistics Projection.
 * 
 * Maintains denormalized user statistics:
 * - Total todos per user
 * - Completed todos per user
 * - Completion rate
 * 
 * Updates in real-time from event stream!
 */
class TodoStatsProjection implements Projection
{
    public function __construct(
        private \PDO $db,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Events this projection handles.
     */
    public function handles(): array
    {
        return [
            TodoCreated::class,
            TodoCompleted::class,
            TodoUncompleted::class,
        ];
    }

    /**
     * Project event to read model.
     */
    public function project(Event $event): void
    {
        match (get_class($event)) {
            TodoCreated::class => $this->whenTodoCreated($event),
            TodoCompleted::class => $this->whenTodoCompleted($event),
            TodoUncompleted::class => $this->whenTodoUncompleted($event),
            default => null,
        };
    }

    /**
     * Handle TodoCreated event.
     */
    protected function whenTodoCreated(TodoCreated $event): void
    {
        $this->logger->debug("Projecting TodoCreated", [
            'todo_id' => $event->todoId,
            'user_id' => $event->userId,
        ]);

        // Upsert user stats
        $sql = "
            INSERT INTO todo_user_stats (user_id, total_todos, completed_todos, updated_at)
            VALUES (:user_id, 1, 0, NOW())
            ON CONFLICT (user_id)
            DO UPDATE SET
                total_todos = todo_user_stats.total_todos + 1,
                updated_at = NOW()
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $event->userId]);
    }

    /**
     * Handle TodoCompleted event.
     */
    protected function whenTodoCompleted(TodoCompleted $event): void
    {
        $this->logger->debug("Projecting TodoCompleted", [
            'todo_id' => $event->todoId,
        ]);

        // Get user ID from todo
        $userId = $this->getUserIdForTodo($event->todoId);

        if ($userId) {
            $sql = "
                UPDATE todo_user_stats
                SET completed_todos = completed_todos + 1,
                    completion_rate = ROUND((completed_todos + 1) * 100.0 / total_todos, 2),
                    updated_at = NOW()
                WHERE user_id = :user_id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
        }
    }

    /**
     * Handle TodoUncompleted event.
     */
    protected function whenTodoUncompleted(TodoUncompleted $event): void
    {
        $this->logger->debug("Projecting TodoUncompleted", [
            'todo_id' => $event->todoId,
        ]);

        $userId = $this->getUserIdForTodo($event->todoId);

        if ($userId) {
            $sql = "
                UPDATE todo_user_stats
                SET completed_todos = completed_todos - 1,
                    completion_rate = ROUND((completed_todos - 1) * 100.0 / total_todos, 2),
                    updated_at = NOW()
                WHERE user_id = :user_id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
        }
    }

    /**
     * Reset projection (truncate table).
     */
    public function reset(): void
    {
        $this->logger->info("Resetting TodoStatsProjection");
        $this->db->exec("TRUNCATE TABLE todo_user_stats");
    }

    /**
     * Get user ID for todo.
     */
    protected function getUserIdForTodo(string $todoId): ?string
    {
        $sql = "SELECT user_id FROM todos WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $todoId]);
        
        return $stmt->fetchColumn() ?: null;
    }
}
