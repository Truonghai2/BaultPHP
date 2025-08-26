<?php

namespace App\Logging;

use Psr\Log\LoggerInterface;

class QueryLoggerCollector
{
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Logs the SQL query.
     * This method signature is compatible with what TraceablePDO expects from a collector.
     */
    public function addQuery(string $sql, array $params, float $executionMS, $connectionName = null, $pdo = null)
    {
        $this->logger->info('SQL Query Executed', [
            'sql' => $sql,
            'bindings' => $params,
            'time_ms' => $executionMS,
            'connection' => $connectionName,
        ]);
    }
}
