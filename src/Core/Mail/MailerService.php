<?php

namespace Core\Mail;

use Core\Contracts\Mail\Mailable as MailableContract;
use Core\Contracts\Mail\Mailer as MailerContract;
use Symfony\Component\Mailer\MailerInterface as SymfonyMailer;

class MailerService implements MailerContract
{
    public function __construct(
        protected SymfonyMailer $symfonyMailer,
        protected array $from,
    ) {
    }

    public function to(object|array|string $users): PendingMail
    {
        return (new PendingMail($this))->to($users);
    }

    public function send(MailableContract $mailable): void
    {
        if (!$mailable instanceof Mailable) {
            throw new \InvalidArgumentException('Mailable must be an instance of Core\Mail\Mailable');
        }

        $this->symfonyMailer->send($mailable->buildSymfonyEmail($this->from));
    }
}
