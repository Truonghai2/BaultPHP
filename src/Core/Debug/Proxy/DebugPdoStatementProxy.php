<?php

namespace Core\Debug\Proxy;

use Core\Debug\DebugManager;
use PDOStatement;

class DebugPdoStatementProxy
{
    public function __construct(
        private PDOStatement $statement,
        private DebugManager $debugManager,
        private string $queryString,
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $startTime = microtime(true);
        try {
            return $this->statement->execute($params);
        } finally {
            $duration = microtime(true) - $startTime;
            $this->debugManager->recordQuery($this->queryString, $params ?? [], $duration);
        }
    }

    /**
     * Forward any other calls to the original PDOStatement object.
     */
    public function __call(string $method, array $args)
    {
        return $this->statement->{$method}(...$args);
    }
}
