<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Final Class SanitizationProcessor
 * Tự động kiểm duyệt các giá trị của các key nhạy cảm trong log.
 */
final class SanitizationProcessor implements ProcessorInterface
{
    /**
     * Danh sách các key nhạy cảm cần được che giấu.
     * @var array<string>
     */
    private readonly array $sensitiveKeys;

    public function __construct(string ...$sensitiveKeys)
    {
        $this->sensitiveKeys = array_map('strtolower', $sensitiveKeys);
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        // Làm sạch cả 'context' và 'extra' của log record.
        $sanitizedContext = $this->sanitize($record->context);
        $sanitizedExtra = $this->sanitize($record->extra);

        return $record->with(context: $sanitizedContext, extra: $sanitizedExtra);
    }

    /**
     * Quét và làm sạch một mảng một cách đệ quy.
     */
    protected function sanitize(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (in_array(strtolower((string) $key), $this->sensitiveKeys, true)) {
                $value = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $value = $this->sanitize($value);
            }
        }

        return $data;
    }
}
