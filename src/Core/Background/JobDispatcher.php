<?php 

namespace Core\Background;

class JobDispatcher
{
    public function dispatch(callable $job): void
    {
        // ⚠️ Tối giản - thực tế nên tách process / chạy async / swoole
        // Bạn có thể sử dụng: shell_exec('php run-job.php &')
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new \Exception("Cannot fork");
        } elseif ($pid === 0) {
            call_user_func($job);
            exit(0);
        }
    }
}