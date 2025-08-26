<?php

namespace Core\Tasking;

use Core\Application;
use Swoole\Http\Server as SwooleServer;

/**
 * Service để gửi các tác vụ bất đồng bộ đến Swoole Task Worker.
 */
class TaskService
{
    private ?SwooleServer $server;

    public function __construct(Application $app)
    {
        if ($app->has(SwooleServer::class)) {
            $this->server = $app->get(SwooleServer::class);
        } else {
            $this->server = null;
        }
    }

    /**
     * Gửi một đối tượng Task đến Task Worker để xử lý.
     *
     * @param object $task Đối tượng chứa dữ liệu cho tác vụ.
     */
    public function dispatch(object $task): void
    {
        $this->server?->task($task);
    }
}
