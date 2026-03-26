<?php

namespace Modules\Todo\RPC;

use Core\RPC\Grpc\Server\GrpcService;
use Core\CQRS\{CommandBus, QueryBus};
use Modules\Todo\Application\Commands\{CreateTodoCommand, CompleteTodoCommand};
use Modules\Todo\Application\Queries\GetTodosQuery;

/**
 * gRPC TodoService Implementation.
 * 
 * Perfect demonstration of CQRS + gRPC integration!
 */
class TodoServiceImpl extends GrpcService
{
    /**
     * Create a new todo.
     * 
     * RPC: CreateTodo(CreateTodoRequest) returns (TodoResponse)
     */
    public function createTodo($request)
    {
        try {
            // gRPC Request → CQRS Command
            $command = new CreateTodoCommand(
                title: $request->getTitle(),
                userId: $request->getUserId()
            );

            // Execute via CommandBus
            $result = $this->executeCommand($command);

            // Result → gRPC Response
            return $this->resultToResponse($result, function($data) {
                return $this->todoToResponse($data);
            });

        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Complete a todo.
     * 
     * RPC: CompleteTodo(CompleteTodoRequest) returns (TodoResponse)
     */
    public function completeTodo($request)
    {
        try {
            $command = new CompleteTodoCommand(
                todoId: $request->getTodoId()
            );

            $result = $this->executeCommand($command);

            return $this->resultToResponse($result, function($data) {
                return $this->todoToResponse($data);
            });

        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * List todos for a user.
     * 
     * RPC: ListTodos(ListTodosRequest) returns (ListTodosResponse)
     */
    public function listTodos($request)
    {
        try {
            // gRPC Request → CQRS Query
            $query = new GetTodosQuery(
                userId: $request->getUserId(),
                limit: $request->getLimit() ?: 20,
                offset: $request->getOffset() ?: 0,
                completed: $request->hasCompleted() ? $request->getCompleted() : null
            );

            // Execute via QueryBus (with caching!)
            $result = $this->executeQuery($query);

            // Result → gRPC Response
            return $this->resultToResponse($result, function($todos) use ($request) {
                return $this->todosToListResponse($todos, $request);
            });

        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Convert todo to gRPC TodoResponse.
     */
    private function todoToResponse($todo)
    {
        $response = new \Todo\TodoResponse();
        $response->setId($todo['todo_id'] ?? $todo['id']);
        $response->setTitle($todo['title']);
        $response->setUserId($todo['user_id']);
        $response->setCompleted($todo['completed'] ?? false);
        $response->setCreatedAt($todo['created_at']);
        
        if (isset($todo['completed_at'])) {
            $response->setCompletedAt($todo['completed_at']);
        }
        
        return $response;
    }

    /**
     * Convert todos list to gRPC ListTodosResponse.
     */
    private function todosToListResponse($todos, $request)
    {
        $response = new \Todo\ListTodosResponse();
        
        foreach ($todos as $todo) {
            $response->addTodos($this->todoToResponse($todo->toArray()));
        }
        
        $response->setTotal(count($todos));
        $response->setLimit($request->getLimit() ?: 20);
        $response->setOffset($request->getOffset() ?: 0);
        
        return $response;
    }
}
