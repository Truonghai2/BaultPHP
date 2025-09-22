<?php

namespace App\Logging;

use Core\Application;
use Core\Support\Facades\Auth;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Class ContextProcessor
 * Tự động inject thông tin ngữ cảnh vào tất cả các log record.
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
        if ($this->app->runningInConsole()) {
            if (isset($_SERVER['argv'])) {
                $record->extra['command'] = implode(' ', array_slice($_SERVER['argv'], 1));
            }
        } else {
            if ($this->app->bound('request_id')) {
                $record->extra['request_id'] = $this->app->make('request_id');
            }

            try {
                $record->extra['user_id'] = Auth::id() ?? 'guest';
            } catch (\Throwable) {
                $record->extra['user_id'] = 'unresolved';
            }
        }

        return $record;
    }
}
