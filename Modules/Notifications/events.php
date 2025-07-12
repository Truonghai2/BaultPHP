<?php

return [
    \Modules\User\Domain\Events\UserWasCreated::class => [
        \Modules\Notifications\Application\Listeners\SendWelcomeEmail::class,
    ],
];