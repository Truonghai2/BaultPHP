<?php

namespace Core\Logging;

use Core\Application;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Injects a unique request ID into all log records for a given request cycle.
 * This is crucial for tracing a request's entire lifecycle through the logs
 * in a concurrent environment like Swoole.
 */
class RequestProcessor implements ProcessorInterface
{
    public function __construct(private Application $app)
    {
    }

    /**
     * @param LogRecord $record
     * @return LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        // If a request_id is bound to the container for the current request, add it to the log.
        if ($this->app->bound('request_id')) {
            $record->extra['request_id'] = $this->app->make('request_id');
        }

        return $record;
    }
}
