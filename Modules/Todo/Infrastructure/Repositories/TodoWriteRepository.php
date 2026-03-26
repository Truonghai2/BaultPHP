<?php

namespace Modules\Todo\Infrastructure\Repositories;

use Core\Support\Result;
use Core\Database\Connection;
use Modules\Todo\Domain\Entities\Todo;

/**
 * Write Repository for Todo Aggregate.
 * 
 * Handles persistence of domain entities to write store (PostgreSQL).
 */
class TodoWriteRepository
{
    public function __construct(
        private Connection $db
    ) {}

    /**
     * Save todo to write store.
     */
    public function save(Todo $todo): Result
    {
        try {
            $data = $todo->toArray();
            
            // Check if exists
            $existing = $this->db->table('todos')
                ->where('id', $todo->id())
                ->first();

            if ($existing) {
                // Update
                $this->db->table('todos')
                    ->where('id', $todo->id())
                    ->update($data);
            } else {
                // Insert
                $this->db->table('todos')->insert($data);
            }

            return Result::ok();

        } catch (\Throwable $e) {
            return Result::fail('Failed to save todo: ' . $e->getMessage());
        }
    }

    /**
     * Find todo by ID.
     */
    public function findById(string $id): Result
    {
        try {
            $data = $this->db->table('todos')
                ->where('id', $id)
                ->first();

            if (!$data) {
                return Result::fail('Todo not found');
            }

            $todo = Todo::fromArray((array) $data);

            return Result::ok($todo);

        } catch (\Throwable $e) {
            return Result::fail('Failed to load todo: ' . $e->getMessage());
        }
    }

    /**
     * Delete todo.
     */
    public function delete(string $id): Result
    {
        try {
            $this->db->table('todos')
                ->where('id', $id)
                ->delete();

            return Result::ok();

        } catch (\Throwable $e) {
            return Result::fail('Failed to delete todo: ' . $e->getMessage());
        }
    }
}
