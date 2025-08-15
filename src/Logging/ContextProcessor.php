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
    private ?string $requestId = null;

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
            // Đối với các lệnh CLI, thêm tên lệnh đang chạy.
            if (isset($_SERVER['argv'])) {
                $record->extra['command'] = implode(' ', array_slice($_SERVER['argv'], 1));
            }
        } else {
            // Đối với các request HTTP, thêm ID của request và ID của user.
            if ($this->requestId === null) {
                // Tạo một ID duy nhất cho mỗi request.
                $this->requestId = uniqid('req_', true);
            }
            $record->extra['request_id'] = $this->requestId;

            try {
                // Lấy ID của user một cách an toàn, tránh lỗi nếu chưa đăng nhập.
                $record->extra['user_id'] = Auth::id() ?? 'guest';
            } catch (\Throwable) {
                $record->extra['user_id'] = 'unresolved';
            }
        }

        return $record;
    }
}
