<?php

/**
 * User Module - Event Sourcing Configuration
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Event Sourcing for User Module
    |--------------------------------------------------------------------------
    */
    'enabled' => env('EVENT_SOURCING_USER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Dual Write Mode
    |--------------------------------------------------------------------------
    */
    'dual_write' => env('EVENT_SOURCING_USER_DUAL_WRITE', true),

    /*
    |--------------------------------------------------------------------------
    | Auto Record Events
    |--------------------------------------------------------------------------
    */
    'auto_record' => [
        'enabled' => env('EVENT_SOURCING_USER_AUTO_RECORD', false), // Manual by default
        
        'models' => [
            // 'Modules\User\Infrastructure\Models\User',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregates Configuration
    |--------------------------------------------------------------------------
    */
    'aggregates' => [
        'user' => [
            'enabled' => env('EVENT_SOURCING_USER_AGGREGATE_ENABLED', true),
            'class' => 'Core\EventSourcing\Examples\UserAggregate',
            
            'snapshots' => [
                'enabled' => env('EVENT_SOURCING_USER_SNAPSHOTS', true),
                'frequency' => 50,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Projections
    |--------------------------------------------------------------------------
    */
    'projections' => [
        'enabled' => false,
        'registered' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Publishing
    |--------------------------------------------------------------------------
    */
    'publish_events' => [
        'enabled' => env('EVENT_SOURCING_USER_PUBLISH_ENABLED', false),
        'queue' => 'user_events',
        
        'event_types' => [
            'Core\EventSourcing\Examples\Events\UserRegistered',
            'Core\EventSourcing\Examples\Events\UserStatusChanged',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GDPR Compliance
    |--------------------------------------------------------------------------
    */
    'gdpr' => [
        'enabled' => true,
        'anonymize_on_delete' => true,
        'retention_period' => 365, // days
    ],

    /*
    |--------------------------------------------------------------------------
    | Console Commands
    |--------------------------------------------------------------------------
    */
    'commands' => [
        'Modules\User\Console\EventSourcingDemoCommand',
    ],
];

