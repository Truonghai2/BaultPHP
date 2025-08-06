<?php

namespace Core\Contracts\Mail;

use Core\Mail\PendingMail;

interface Mailer
{
    /**
     * Bắt đầu quá trình gửi một Mailable instance.
     */
    public function to(object|array|string $users): PendingMail;

    /**
     * Gửi một Mailable instance.
     *
     * @internal This is for internal use by PendingMail.
     */
    public function send(Mailable $mailable): void;
}
