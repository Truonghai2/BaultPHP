<?php

namespace Core\CQRS;

use Core\Application;
use Core\Support\Result;
use Psr\Log\LoggerInterface;

/**
 * Command Bus for CQRS pattern.
 * 
 * Dispatches commands to their handlers asynchronously.
 * Provides middleware pipeline for cross-cutting concerns.
 * 
 * Usage:
 * ```php
 * $commandBus = app(CommandBus::class);
 * 
 * // Register handler
 * $commandBus->register(AddTodoCommand::class, AddTodoCommandHandler::class);
 * 
 * // Execute command
 * $result = $commandBus->execute(new AddTodoCommand('Buy milk'));
 * 
 * if ($result->isSuccess()) {
 *     // Success
 * }
 * ```
 */
class CommandBus
{
    /**
     * Registered command handlers.
     * @var array<string, string>
     */
    protected array $handlers = [];

    /**
     * Middleware stack.
     * @var array<callable>
     */
    protected array $middleware = [];

    public function __construct(
        protected Application $app,
        protected LoggerInterface $logger
    ) {}

    /**
     * Register a command handler.
     *
     * @param string $commandClass
     * @param string $handlerClass
     */
    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }

    /**
     * Register multiple command handlers.
     *
     * @param array<string, string> $handlers
     */
    public function registerMany(array $handlers): void
    {
        foreach ($handlers as $commandClass => $handlerClass) {
            $this->register($commandClass, $handlerClass);
        }
    }

    /**
     * Add middleware to the pipeline.
     *
     * @param callable $middleware
     */
    public function addMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Execute a command.
     *
     * @param Command $command
     * @return Result<void>
     */
    public function execute(Command $command): Result
    {
        $commandClass = get_class($command);

        // Check if handler exists
        if (!isset($this->handlers[$commandClass])) {
            $error = "No handler registered for command: $commandClass";
            $this->logger->error('CommandBus: ' . $error, [
                'command' => $commandClass,
                'correlation_id' => $command->getCorrelationId(),
            ]);

            return Result::fail($error);
        }

        // Log command execution
        $this->logger->info('CommandBus: Executing command', [
            'command' => $command->getCommandName(),
            'bounded_context' => $command->getBoundedContext(),
            'correlation_id' => $command->getCorrelationId(),
            'metadata' => $command->getMetadata(),
        ]);

        $startTime = microtime(true);

        try {
            // Resolve handler
            $handlerClass = $this->handlers[$commandClass];
            $handler = $this->app->make($handlerClass);

            // Execute through middleware pipeline
            $result = $this->executeWithMiddleware($handler, $command);

            // Log success
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->info('CommandBus: Command executed successfully', [
                'command' => $command->getCommandName(),
                'duration_ms' => round($duration, 2),
                'correlation_id' => $command->getCorrelationId(),
            ]);

            return $result;

        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            // Log failure
            $this->logger->error('CommandBus: Command execution failed', [
                'command' => $command->getCommandName(),
                'error' => $e->getMessage(),
                'duration_ms' => round($duration, 2),
                'correlation_id' => $command->getCorrelationId(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Result::fail($e->getMessage());
        }
    }

    /**
     * Execute command with middleware pipeline.
     *
     * @param CommandHandler $handler
     * @param Command $command
     * @return Result<void>
     */
    protected function executeWithMiddleware(CommandHandler $handler, Command $command): Result
    {
        if (empty($this->middleware)) {
            return $handler->handle($command);
        }

        // Build middleware pipeline
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($next, $middleware) => fn() => $middleware($command, $next),
            fn() => $handler->handle($command)
        );

        return $pipeline();
    }

    /**
     * Execute command asynchronously (queue it).
     *
     * @param Command $command
     * @param string|null $queue
     * @return mixed
     */
    public function executeAsync(Command $command, ?string $queue = null): mixed
    {
        // Convert command to job
        $job = new ExecuteCommandJob($command);

        // Dispatch to queue
        return app('queue')->connection()->push($job, $queue);
    }

    /**
     * Get all registered handlers.
     *
     * @return array<string, string>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Check if a handler is registered for a command.
     *
     * @param string $commandClass
     * @return bool
     */
    public function hasHandler(string $commandClass): bool
    {
        return isset($this->handlers[$commandClass]);
    }
}
