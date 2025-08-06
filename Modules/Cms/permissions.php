<?php

/**
 * Defines permissions for the Cms module.
 */

return [
    'pages:view' => [
        'description' => 'View pages.',
        'captype' => 'read',
    ],
    'pages:create' => [
        'description' => 'Create new pages.',
        'captype' => 'write',
    ],
    'pages:update' => [
        'description' => 'Update any page.',
        'captype' => 'write',
    ],
    'pages:delete' => [
        'description' => 'Delete any page.',
        'captype' => 'write',
    ],
];
