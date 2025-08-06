<?php

namespace Core\Mail;

use Core\Contracts\Mail\Mailable as MailableContract;
use Core\Contracts\Mail\Mailer as MailerContract;
use Core\Contracts\Queue\Job;

/**
 * Lớp Job này chịu trách nhiệm gửi một Mailable từ hàng đợi.
 * Nó được tuần tự hóa và đẩy vào queue, sau đó được worker xử lý.
 */
class SendQueuedMailable implements Job
{
    /**
     * The mailable instance.
     *
     * @var \Core\Contracts\Mail\Mailable
     */
    public MailableContract $mailable;

    /**
     * Create a new job instance.
     */
    public function __construct(MailableContract $mailable)
    {
        $this->mailable = $mailable;
    }

    /**
     * Handle the job.
     */
    public function handle(): void
    {
        app(MailerContract::class)->send($this->mailable);
    }
}
