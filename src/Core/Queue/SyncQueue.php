<?php

namespace Core\Queue;

use Core\Contracts\Queue\Job;
use Core\Contracts\Queue\Queue;

class SyncQueue implements Queue
{
    public function push(Job $job, ?string $queue = null): void
    {
        // Với sync driver, job được thực thi ngay lập tức.
        try {
            $job->handle();
        } catch (\Throwable $e) { // Bắt lỗi kiểu Throwable để bao quát cả Error và Exception
            // Re-throw the exception so the developer is immediately aware of the failure.
            throw $e;
        }
    }

    public function later($delay, Job $job, ?string $queue = null): void
    {
        $this->push($job, $queue);
    }

    public function pop(?string $queue = null): ?Job
    {
        return null; // Sync queue không có pop
    }
}
