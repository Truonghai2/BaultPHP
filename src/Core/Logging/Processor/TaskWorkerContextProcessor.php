<?php

namespace Core\Logging\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Swoole\Http\Server as SwooleHttpServer;

/**
 * Adds context about the Swoole task worker (like PID and worker ID)
 * to log records.
 */
class TaskWorkerContextProcessor implements ProcessorInterface
{
    /**
     * @param SwooleHttpServer $server The Swoole server instance.
     */
    public function __construct(private SwooleHttpServer $server)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['task_worker'] = [
            'pid' => $this->server->getWorkerPid(),
            'id' => $this->server->worker_id,
        ];

        return $record;
    }
}
