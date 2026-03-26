<?php

namespace Modules\User\Mail;

use Core\Mail\Mailable;

/**
 * Welcome Email.
 * 
 * Sent to new users after registration.
 */
class WelcomeEmail extends Mailable
{
    public function __construct(
        private object $user,
        private ?string $verificationUrl = null
    ) {
        parent::__construct();
    }

    /**
     * Build the message.
     */
    public function build(): \Symfony\Component\Mime\Email
    {
        return $this
            ->subject('Welcome to ' . config('app.name', 'BaultFrame') . '!')
            ->view('emails.welcome', [
                'user' => $this->user,
                'verificationUrl' => $this->verificationUrl,
            ])
            ->build();
    }
}
