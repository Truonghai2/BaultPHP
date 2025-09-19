<?php

return [
    'admin:view' => [
        'description' => 'Access to the admin dashboard',
        'captype' => 'view',
    ],

    'settings:manage' => [
        'description' => 'Manage application settings',
        'captype' => 'write',
    ],

    'users:manage' => [
        'description' => 'Manage users and roles',
        'captype' => 'write',
    ],
];
