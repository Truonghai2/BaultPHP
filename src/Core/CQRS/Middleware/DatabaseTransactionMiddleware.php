<?php

namespace Core\CQRS\Middleware;

use Core\CQRS\Command;
use Core\CQRS\CommandMiddleware;
use Core\ORM\Connection;
use PDO;
use Throwable;

/**
 * DatabaseTransactionMiddleware is responsible for managing database transactions
 * around the execution of a command. It ensures that if the command execution fails,
 * the transaction is rolled back, and if it succeeds, the transaction is committed.
 */
class DatabaseTransactionMiddleware implements CommandMiddleware
{
    private Connection $connection;

    /**
     * DatabaseTransactionMiddleware constructor.
     * Initializes the PDO connection for database transactions.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
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
        $pdo = $this->connection->connection();

        $pdo->beginTransaction();

        try {
            $result = $next($command);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
