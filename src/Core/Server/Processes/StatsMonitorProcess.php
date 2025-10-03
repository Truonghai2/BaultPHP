<?php

namespace Core\Server\Processes;

use Swoole\Coroutine;
use Swoole\Process;
use Throwable;

/**
 * Một custom process ví dụ để giám sát và ghi lại số liệu thống kê của server Swoole.
 */
class StatsMonitorProcess extends BaseProcess
{
    public function run(Process $worker): void
    {
        $this->logger->info('StatsMonitorProcess started.', ['pid' => $worker->pid]);

        // Đổi tên tiến trình để dễ nhận dạng khi dùng lệnh `ps` hoặc `htop`
        swoole_set_process_name('bault:stats_monitor');

        // Chạy trong một coroutine để có thể sử dụng các hàm sleep bất đồng bộ
        Coroutine::create(function () {
            while (true) {
                try {
                    $stats = $this->server->stats();
                    $this->logger->info('Swoole Server Stats', $stats);
                } catch (Throwable $e) {
                    $this->logger->error('Error in StatsMonitorProcess: ' . $e->getMessage());
                }
                Coroutine::sleep(30);
            }
        });
    }
}
