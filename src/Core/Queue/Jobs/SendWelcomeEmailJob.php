<?php

namespace Core\Queue\Jobs;

use Core\Contracts\Queue\Job;
use Psr\Log\LoggerInterface;
use Throwable;

class SendWelcomeEmailJob implements Job, \Serializable
{
    public function __construct(public string $userEmail, public string $userName)
    {
    }

    /**
     * Xử lý việc gửi email.
     * LoggerInterface sẽ được tự động inject bởi container.
     */
    public function handle(LoggerInterface $logger): void
    {
        $logger->info("Simulating sending welcome email to {$this->userName} <{$this->userEmail}>");
        // Trong thực tế, bạn sẽ gọi MailerService ở đây.
    }

    public function fail(Throwable $exception, LoggerInterface $logger): void
    {
        $logger->error("Job SendWelcomeEmailJob failed for user {$this->userEmail}", ['exception' => $exception->getMessage()]);
    }
}
