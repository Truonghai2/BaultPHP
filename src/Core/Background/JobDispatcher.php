<?php

namespace Core\Background;

class JobDispatcher
{
    public function dispatch(callable $job): void
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new \Exception('Cannot fork');
        } elseif ($pid === 0) {
            call_user_func($job);
            exit(0);
        }
    }
}
