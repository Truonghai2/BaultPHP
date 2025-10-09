<?php

namespace Core\CQRS\Middleware;

use Core\CQRS\Command\Command;
use Core\CQRS\Command\TransactionalCommand;
use Core\ORM\Connection;
use Throwable;

/**
 * DatabaseTransactionMiddleware is responsible for managing database transactions
 * around the execution of a command. It ensures that if the command execution fails,
 * the transaction is rolled back, and if it succeeds, the transaction is committed.
 */
class DatabaseTransactionMiddleware implements CommandMiddleware
{
    /**
     * DatabaseTransactionMiddleware constructor.
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Handle the command execution within a database transaction.
     *
     * @param Command  $command
     * @param callable $next
     * @return mixed
     * @throws Throwable
     */
    public function handle(Command $command, callable $next)
    {
        if (!$command instanceof TransactionalCommand) {
            return $next($command);
        }

        $this->connection->beginTransaction();

        try {
            $result = $next($command);
            $this->connection->commit();
            return $result;
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}
