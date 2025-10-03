<?php

namespace Core\Debug\Proxy;

use Core\Debug\DebugManager;
use PDO;
use PDOStatement;

/**
 * A proxy for PDO connections that intercepts queries to log them for debugging.
 */
class DebugPdoProxy extends PDO
{
    private DebugManager $debugManager;

    public function __construct(private PDO $pdo, DebugManager $debugManager)
    {
        $this->debugManager = $debugManager;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $statement = $this->pdo->prepare($query, $options);
        if ($statement === false) {
            return false;
        }
        return new DebugPdoStatementProxy($statement, $this->debugManager, $query);
    }

    public function query(string $query, ?int $fetchMode = null, ...$fetch_mode_args): PDOStatement|false
    {
        $startTime = microtime(true);
        try {
            // Cannot use `parent::query` as this is a proxy, not a real extension
            $result = $this->pdo->query($query, $fetchMode, ...$fetch_mode_args);
            return $result;
        } finally {
            $duration = microtime(true) - $startTime;
            $this->debugManager->recordQuery($query, [], $duration);
        }
    }

    public function exec(string $statement): int|false
    {
        $startTime = microtime(true);
        try {
            return $this->pdo->exec($statement);
        } finally {
            $duration = microtime(true) - $startTime;
            $this->debugManager->recordQuery($statement, [], $duration);
        }
    }

    /**
     * Forward any other calls to the original PDO object.
     */
    public function __call(string $method, array $args)
    {
        return $this->pdo->{$method}(...$args);
    }

    /**
     * Forward property access to the original PDO object.
     */
    public function __get(string $name)
    {
        return $this->pdo->{$name};
    }

    public function getOriginalConnection(): PDO
    {
        return $this->pdo;
    }
}
