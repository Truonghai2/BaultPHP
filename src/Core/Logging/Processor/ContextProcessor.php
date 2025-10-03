<?php

namespace Core\Logging\Processor;

/**
 * A Monolog-compatible processor that adds extra context data to log records.
 * This is useful for tagging logs from a specific source, like a scheduler or a queue worker.
 */
class ContextProcessor
{
    /**
     * @param array $context The context data to add to each log record.
     */
    public function __construct(private array $context)
    {
    }

    /**
     * The method that Monolog calls to process the record.
     */
    public function __invoke(array $record): array
    {
        $record['extra'] = array_merge($record['extra'] ?? [], $this->context);

        return $record;
    }
}
