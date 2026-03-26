<?php

namespace Core\Mail;

use Core\Contracts\Queue\Job;

/**
 * Send Mail Job.
 * 
 * Queue job for sending emails asynchronously.
 */
class SendMailJob implements Job
{
    public function __construct(
        private Mailable $mailable
    ) {
    }

    /**
     * Handle the job.
     */
    public function handle(Mailer $mailer): void
    {
        $mailer->send($this->mailable);
    }

    /**
     * Get mailable.
     */
    public function getMailable(): Mailable
    {
        return $this->mailable;
    }

    /**
     * Get job display name.
     */
    public function displayName(): string
    {
        return 'Send Email: ' . get_class($this->mailable);
    }

    /**
     * Number of times to attempt.
     */
    public function tries(): int
    {
        return 3;
    }

    /**
     * Timeout in seconds.
     */
    public function timeout(): int
    {
        return 30;
    }
}
