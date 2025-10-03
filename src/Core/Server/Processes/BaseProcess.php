<?php

namespace Core\Server\Processes;

use Core\Application;
use Psr\Log\LoggerInterface;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Process;

/**
 * Lớp cơ sở trừu tượng cho các custom process của Swoole.
 * Cung cấp các tiện ích chung như truy cập vào application container,
 * logger, và server instance.
 */
abstract class BaseProcess
{
    protected Process $worker;

    public function __construct(
        protected Application $app,
        protected SwooleHttpServer $server,
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * Phương thức chính được gọi khi process bắt đầu.
     * Lớp con phải implement logic của mình ở đây.
     *
     * @param Process $worker
     * @return void
     */
    abstract public function run(Process $worker): void;

    /**
     * Phương thức này được Swoole gọi. Nó đóng vai trò là điểm vào (entrypoint).
     */
    public function __invoke(Process $worker): void
    {
        $this->worker = $worker;
        $this->run($worker);
    }
}
