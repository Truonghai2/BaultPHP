<?php

namespace Core\Jobs;

use Core\Contracts\Queue\Job;
use Psr\Log\LoggerInterface;

class LogInfoJob implements Job
{
    public function __construct(
        protected string $message,
        protected array $context = [],
    ) {
    }

    public function handle(): void
    {
        // Lấy logger từ DI container
        $logger = app(LoggerInterface::class);
        $logger->info('LogInfoJob executed: ' . $this->message, $this->context);
    }
}
