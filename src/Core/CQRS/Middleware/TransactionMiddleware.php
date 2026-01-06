<?php

namespace Core\CQRS\Middleware;

use Core\CQRS\Contracts\CommandInterface;

/**
 * Transaction Middleware
 * 
 * Wraps command execution in a database transaction.
 */
class TransactionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private \PDO $connection
    ) {
    }

    public function handle(CommandInterface|QueryInterface $message, callable $next): mixed
    {
        // Only wrap commands in transactions, not queries
        if (!$message instanceof CommandInterface) {
            return $next($message);
        }

        $this->connection->beginTransaction();

        try {
            $result = $next($message);
            $this->connection->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}

