<?php

namespace Modules\User\Mail;

use Core\Mail\Mailable;

/**
 * Password Reset Email.
 * 
 * Sent when user requests password reset.
 */
class PasswordResetEmail extends Mailable
{
    public function __construct(
        private object $user,
        private string $resetUrl,
        private int $expiresIn = 60
    ) {
        parent::__construct();
    }

    /**
     * Build the message.
     */
    public function build(): \Symfony\Component\Mime\Email
    {
        return $this
            ->subject('Reset Your Password')
            ->view('emails.password-reset', [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'expiresIn' => $this->expiresIn,
            ])
            ->priority(1) // High priority
            ->build();
    }
}
