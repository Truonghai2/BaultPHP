<?php

namespace Core\Queue;

use Core\Application;
use Core\Contracts\Queue\Job;
use Core\Server\SwooleServer;

/**
 * Cung cấp một giao diện đơn giản để gửi job đến custom queue process.
 */
class QueueManager
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * Gửi một job đến queue process.
     */
    public function dispatch(Job $job): bool
    {
        $server = $this->getServer();
        if (!$server) {
            return false;
        }

        $message = serialize(['type' => 'queue_job', 'payload' => $job]);

        return $server->sendMessageToMaster($message);
    }

    /**
     * Lazily resolve the SwooleServer instance from the container.
     */
    protected function getServer(): ?SwooleServer
    {
        return $this->app->bound(SwooleServer::class) ? $this->app->make(SwooleServer::class) : null;
    }
}
