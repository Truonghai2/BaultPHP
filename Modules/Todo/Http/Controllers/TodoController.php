<?php

namespace Modules\Todo\Http\Controllers;

use Core\CQRS\{CommandBus, QueryBus};
use Core\Http\{Request, Response};
use Modules\Todo\Application\Commands\{CreateTodoCommand, CompleteTodoCommand};
use Modules\Todo\Application\Queries\GetTodosQuery;

/**
 * Todo HTTP Controller.
 * 
 * Uses CQRS pattern: Commands for writes, Queries for reads.
 */
class TodoController
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus
    ) {}

    /**
     * Get all todos for current user.
     * 
     * Query (Read Side)
     */
    public function index(Request $request): Response
    {
        $query = new GetTodosQuery(
            userId: auth()->id(),
            limit: $request->input('limit', 20),
            offset: $request->input('offset', 0),
            completed: $request->has('completed') 
                ? (bool) $request->input('completed') 
                : null
        );

        $result = $this->queryBus->execute($query);

        return $result->match(
            success: fn($todos) => Response::json([
                'data' => array_map(fn($todo) => $todo->toArray(), $todos),
                'meta' => [
                    'limit' => $query->limit,
                    'offset' => $query->offset,
                ]
            ]),
            failure: fn($error) => Response::json([
                'error' => $error
            ], 500)
        );
    }

    /**
     * Create a new todo.
     * 
     * Command (Write Side)
     */
    public function store(Request $request): Response
    {
        // Validate request
        $request->validate([
            'title' => 'required|string|min:3|max:200',
        ]);

        $command = new CreateTodoCommand(
            title: $request->input('title'),
            userId: auth()->id()
        );

        $result = $this->commandBus->execute($command);

        return $result->match(
            success: fn($data) => Response::json([
                'message' => 'Todo created successfully',
                'data' => $data
            ], 201),
            failure: fn($error) => Response::json([
                'error' => $error
            ], 400)
        );
    }

    /**
     * Mark todo as completed.
     * 
     * Command (Write Side)
     */
    public function complete(Request $request, string $id): Response
    {
        $command = new CompleteTodoCommand(todoId: $id);

        $result = $this->commandBus->execute($command);

        return $result->match(
            success: fn() => Response::json([
                'message' => 'Todo marked as completed'
            ]),
            failure: fn($error) => Response::json([
                'error' => $error
            ], 400)
        );
    }
}
