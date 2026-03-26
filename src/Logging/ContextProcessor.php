<?php

namespace App\Logging;

use Core\Application;
use Core\Support\{Context, Facades\Auth};
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Class ContextProcessor
 * Tự động inject thông tin ngữ cảnh vào tất cả các log record.
 * 
 * Includes:
 * - Correlation ID (for distributed tracing)
 * - User ID (if authenticated)
 * - Request ID (if available)
 * - Command (if running in console)
 */
class ContextProcessor implements ProcessorInterface
{
    public function __construct(private Application $app)
    {
    }

    /**
     * Thêm dữ liệu vào log record.
     *
     * @param LogRecord $record
     * @return LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        // Always add correlation ID (if available)
        $correlationId = Context::getCorrelationId();
        if ($correlationId) {
            $record->extra['correlation_id'] = $correlationId;
        }

        if ($this->app->runningInConsole()) {
            // Console context
            if (isset($_SERVER['argv'])) {
                $record->extra['command'] = implode(' ', array_slice($_SERVER['argv'], 1));
            }
            
            // Add correlation ID for console commands too
            if (!$correlationId) {
                $correlationId = Context::generateCorrelationId();
                Context::setCorrelationId($correlationId);
                $record->extra['correlation_id'] = $correlationId;
            }
        } else {
            // HTTP context
            if ($this->app->bound('request_id')) {
                $record->extra['request_id'] = $this->app->make('request_id');
            }

            // User ID
            try {
                $userId = Context::getUserId() ?? Auth::id();
                $record->extra['user_id'] = $userId ?? 'guest';
            } catch (\Throwable) {
                $record->extra['user_id'] = 'unresolved';
            }
        }

        return $record;
    }
}
