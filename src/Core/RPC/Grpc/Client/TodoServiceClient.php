<?php

namespace Core\RPC\Grpc\Client;

/**
 * Convenient wrapper for TodoService gRPC client.
 * 
 * Provides fluent API for calling Todo service.
 */
class TodoServiceClient
{
    private $client;
    private array $metadata = [];

    public function __construct(
        private GrpcClient $grpcClient,
        private string $hostname = 'localhost:50051'
    ) {
        // Initialize the generated gRPC client
        $this->client = $grpcClient->createClient(
            \Todo\TodoServiceClient::class,
            $hostname
        );
    }

    /**
     * Set authentication token.
     */
    public function withToken(string $token): self
    {
        $this->metadata = $this->grpcClient->withAuth($token);
        return $this;
    }

    /**
     * Create a new todo.
     */
    public function createTodo(string $title, string $userId): object
    {
        $request = new \Todo\CreateTodoRequest();
        $request->setTitle($title);
        $request->setUserId($userId);

        return $this->grpcClient->call(
            $this->client,
            'CreateTodo',
            $request,
            $this->metadata
        );
    }

    /**
     * Complete a todo.
     */
    public function completeTodo(string $todoId): object
    {
        $request = new \Todo\CompleteTodoRequest();
        $request->setTodoId($todoId);

        return $this->grpcClient->call(
            $this->client,
            'CompleteTodo',
            $request,
            $this->metadata
        );
    }

    /**
     * List todos for a user.
     */
    public function listTodos(
        string $userId,
        int $limit = 20,
        int $offset = 0,
        ?bool $completed = null
    ): object {
        $request = new \Todo\ListTodosRequest();
        $request->setUserId($userId);
        $request->setLimit($limit);
        $request->setOffset($offset);

        if ($completed !== null) {
            $request->setCompleted($completed);
        }

        return $this->grpcClient->call(
            $this->client,
            'ListTodos',
            $request,
            $this->metadata
        );
    }

    /**
     * Get a specific todo.
     */
    public function getTodo(string $todoId): object
    {
        $request = new \Todo\GetTodoRequest();
        $request->setTodoId($todoId);

        return $this->grpcClient->call(
            $this->client,
            'GetTodo',
            $request,
            $this->metadata
        );
    }
}
