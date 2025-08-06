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
        } catch (\Throwable $e) {
            // Cần có một cơ chế xử lý lỗi ở đây, ví dụ: log lỗi.
            app('log')->error('Sync job failed', ['exception' => $e]);
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
