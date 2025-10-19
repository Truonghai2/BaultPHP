<?php

namespace Core\Debug;

use App\Logging\QueryLoggerCollector;
use DebugBar\DataCollector\PDO\TraceablePDO;
use DebugBar\DataCollector\PDO\TracedStatement;
use PDO;

/**
 * Extends the standard TraceablePDO to also log queries to a custom logger.
 */
class LoggingTraceablePDO extends TraceablePDO
{
    protected ?QueryLoggerCollector $queryLogger = null;

    public function __construct(PDO $pdo, ?QueryLoggerCollector $queryLogger = null)
    {
        parent::__construct($pdo);
        $this->queryLogger = $queryLogger;
    }

    /**
     * Overrides the parent method to also pass the statement to our logger.
     *
     * @param TracedStatement $stmt
     */
    public function addExecutedStatement(TracedStatement $stmt): void
    {
        parent::addExecutedStatement($stmt);

        if ($this->queryLogger && $stmt->isSuccess()) {
            $this->queryLogger->addQuery(
                $stmt->getSql(),
                $stmt->getParameters(),
                $stmt->getDuration() * 1000, // Convert seconds to milliseconds
                $this->getAttribute(PDO::ATTR_DRIVER_NAME),
            );
        }
    }
}
