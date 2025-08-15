<?php

namespace Core\Contracts\Mail;

use Core\Mail\PendingMail;

interface Mailer
{
    /**
     * Set the recipient of the email.
     *
     * @param object|array|string $users
     * @return PendingMail
     */
    public function to(object|array|string $users): PendingMail;

    /**
     * Send a Mailable instance to the specified users.
     *
     * @param Mailable $mailable
     * @return PendingMail
     */
    public function send(Mailable $mailable): void;
}
