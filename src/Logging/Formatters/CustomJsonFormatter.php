<?php

namespace App\Logging\Formatters;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

class CustomJsonFormatter implements FormatterInterface
{
    /**
     * Formats a log record.
     *
     * @param LogRecord $record A record to format
     * @return string The formatted record as a JSON string.
     */
    public function format(LogRecord $record): string
    {
        $formatted = [
            '@timestamp' => $record->datetime->format('c'), // Định dạng ISO 8601
            'level'      => $record->level->getName(),
            'channel'    => $record->channel,
            'message'    => $record->message,
            'context'    => $record->context,
            'extra'      => $record->extra, // Chứa request_id, user_id từ ContextProcessor
            'app_name'   => config('app.name', 'BaultPHP'),
        ];

        // JSON_UNESCAPED_SLASHES và JSON_UNESCAPED_UNICODE giúp log dễ đọc hơn.
        // Thêm "\n" để mỗi log entry nằm trên một dòng riêng biệt.
        return json_encode($formatted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * Formats a set of log records.
     */
    public function formatBatch(array $records): string
    {
        return implode('', array_map([$this, 'format'], $records));
    }
}
