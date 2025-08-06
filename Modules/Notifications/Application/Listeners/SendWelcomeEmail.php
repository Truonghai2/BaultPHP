<?php

namespace Modules\Notifications\Application\Listeners;

use Modules\User\Domain\Events\UserWasCreated;

class SendWelcomeEmail
{
    /**
     * Handle the event.
     *
     * @param UserWasCreated $event
     * @return void
     */
    public function handle(UserWasCreated $event): void
    {
        $user = $event->user;

        // Logic gửi email ở đây. Ví dụ:
        // mail($user->email, 'Welcome to BaultFrame!', "Hello {$user->name}, welcome aboard!");
        error_log("Sending welcome email to {$user->email}");
    }
}
