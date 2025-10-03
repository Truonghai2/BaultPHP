<?php

namespace Core\Queue\Jobs;

use Core\Contracts\Queue\Job;

/**
 * Một job đơn giản để ghi một thông điệp vào log.
 */
class LogMessageJob implements Job, \Serializable
{
    public function __construct(public string $message)
    {
    }

    public function handle(): void
    {
        // Logic xử lý job sẽ được thực hiện bởi worker của queue, không phải ở đây.
    }
}
