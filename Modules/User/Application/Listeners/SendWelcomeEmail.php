<?php

namespace Modules\User\Application\Listeners;

use Core\Support\Facades\Mail;
use Modules\User\Domain\Events\UserWasCreated;
use Modules\User\Mail\WelcomeEmail;

/**
 * Handles the UserWasCreated event to send a welcome email.
 */
class SendWelcomeEmail
{
    /**
     * Handle the event.
     */
    public function handle(UserWasCreated $event): void
    {
        $user = $event->user;

        // Use the Mail facade to fluently build and send the email.
        // The `WelcomeEmail` mailable will be automatically pushed to the queue
        // because it implements the ShouldQueue interface.
        Mail::to($user)->send(new WelcomeEmail($user));
    }
}
