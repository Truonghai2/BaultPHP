<?php

/**
 * Admin Module - Event Sourcing Configuration
 * 
 * Module-specific event sourcing settings
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Event Sourcing for Admin Module
    |--------------------------------------------------------------------------
    */
    'enabled' => env('EVENT_SOURCING_ADMIN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Dual Write Mode
    |--------------------------------------------------------------------------
    */
    'dual_write' => env('EVENT_SOURCING_ADMIN_DUAL_WRITE', true),

    /*
    |--------------------------------------------------------------------------
    | Auto Record Events
    |--------------------------------------------------------------------------
    */
    'auto_record' => [
        'enabled' => env('EVENT_SOURCING_ADMIN_AUTO_RECORD', true),
        
        'models' => [
            'Modules\Admin\Infrastructure\Models\Module',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregates Configuration
    |--------------------------------------------------------------------------
    */
    'aggregates' => [
        'module' => [
            'enabled' => env('EVENT_SOURCING_ADMIN_MODULE_ENABLED', true),
            'class' => 'Modules\Admin\Domain\Aggregates\ModuleAggregate',
            
            'snapshots' => [
                'enabled' => false, // Modules don't need snapshots (low event count)
                'frequency' => 50,
            ],
            
            'observer' => 'Modules\Admin\Infrastructure\Observers\ModuleEventSourcingObserver',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Lifecycle Tracking
    |--------------------------------------------------------------------------
    */
    'lifecycle' => [
        // Track all installations
        'track_installations' => env('ADMIN_TRACK_INSTALLATIONS', true),
        
        // Track dependency resolution
        'track_dependencies' => env('ADMIN_TRACK_DEPENDENCIES', true),
        
        // Track enable/disable
        'track_state_changes' => env('ADMIN_TRACK_STATE_CHANGES', true),
        
        // Track updates
        'track_updates' => env('ADMIN_TRACK_UPDATES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Projections (Optional)
    |--------------------------------------------------------------------------
    */
    'projections' => [
        'enabled' => false,
        
        'registered' => [
            // 'module_status' => 'Modules\Admin\Application\Projections\ModuleStatusProjection',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Publishing
    |--------------------------------------------------------------------------
    */
    'publish_events' => [
        'enabled' => env('EVENT_SOURCING_ADMIN_PUBLISH_ENABLED', false),
        'queue' => 'admin_events',
        
        // Critical events to publish
        'event_types' => [
            'Modules\Admin\Domain\Aggregates\Events\ModuleInstalled',
            'Modules\Admin\Domain\Aggregates\Events\ModuleUninstalled',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Configuration
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => true,
        'log_all_changes' => true, // Admin is critical, log everything
        'alert_on_critical' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Console Commands
    |--------------------------------------------------------------------------
    */
    'commands' => [
        'Modules\Admin\Console\ModuleEventSourcingCommand',
    ],
];

