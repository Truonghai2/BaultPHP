<?php

namespace Modules\User\Mail;

use Core\Contracts\Queue\ShouldQueue;
use Core\Mail\Mailable;
use Modules\User\Infrastructure\Models\User;

class WelcomeEmail extends Mailable implements ShouldQueue
{
    public function __construct(public User $user)
    {
    }

    public function build(): static
    {
        return $this->subject("Welcome to BaultPHP, {$this->user->name}!")
                    ->view('user::emails.welcome', ['user' => $this->user]);
    }
}
