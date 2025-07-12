<?php

return [
    // cấu hình User
    'providers' => [
        // Các provider khác...
        Modules\User\Providers\UserServiceProvider::class,
        Modules\User\Providers\ModuleServiceProvider::class,
    ],
];

