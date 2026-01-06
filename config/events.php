<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global Event-Listener mapping
    |--------------------------------------------------------------------------
    |
    | Nếu bạn muốn gán global event và listener (ngoài module),
    | có thể cấu hình ở đây.
    |
    */

    Modules\User\Domain\Events\UserRegistered::class => [
        Modules\Notifications\Listeners\SendWelcomeNotification::class,
    ],
];
