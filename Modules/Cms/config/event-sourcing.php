<?php

/**
 * CMS Module - Event Sourcing Configuration
 *
 * Module-specific event sourcing settings.
 * Overrides global config from config/event-sourcing.php
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Event Sourcing for CMS Module
    |--------------------------------------------------------------------------
    */
    'enabled' => env('EVENT_SOURCING_CMS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Dual Write Mode for CMS
    |--------------------------------------------------------------------------
    |
    | Write to both traditional DB and event store
    |
    */
    'dual_write' => env('EVENT_SOURCING_CMS_DUAL_WRITE', true),

    /*
    |--------------------------------------------------------------------------
    | Auto Record Events
    |--------------------------------------------------------------------------
    |
    | Automatically record events when models change
    |
    */
    'auto_record' => [
        'enabled' => env('EVENT_SOURCING_CMS_AUTO_RECORD', true),

        // Models to observe
        'models' => [
            'Modules\Cms\Infrastructure\Models\Page',
            'Modules\Cms\Infrastructure\Models\PageBlock',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregates Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for each aggregate in the module
    |
    */
    'aggregates' => [
        'page' => [
            'enabled' => env('EVENT_SOURCING_CMS_PAGE_ENABLED', true),
            'class' => 'Modules\Cms\Domain\Aggregates\PageAggregate',

            // Snapshot configuration
            'snapshots' => [
                'enabled' => env('EVENT_SOURCING_CMS_PAGE_SNAPSHOTS', true),
                'frequency' => 100, // Every 100 events
            ],

            // Observer
            'observer' => 'Modules\Cms\Infrastructure\Observers\PageEventSourcingObserver',
        ],

        'block' => [
            'enabled' => env('EVENT_SOURCING_CMS_BLOCK_ENABLED', true),
            'class' => 'Modules\Cms\Domain\Aggregates\PageBlockAggregate',

            'snapshots' => [
                'enabled' => env('EVENT_SOURCING_CMS_BLOCK_SNAPSHOTS', true),
                'frequency' => 50,
            ],

            'observer' => 'Modules\Cms\Infrastructure\Observers\PageBlockEventSourcingObserver',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Projections
    |--------------------------------------------------------------------------
    |
    | Read models for optimized queries
    |
    */
    'projections' => [
        'enabled' => env('EVENT_SOURCING_CMS_PROJECTIONS_ENABLED', true),
        'registered' => [
            'page_list' => 'Modules\\Cms\\Application\\Projections\\PageListProjection',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Publishing
    |--------------------------------------------------------------------------
    */
    'publish_events' => [
        'enabled' => env('EVENT_SOURCING_CMS_PUBLISH_ENABLED', false),
        'queue' => 'cms_events',

        // Event types to publish
        'event_types' => [
            'Modules\Cms\Domain\Aggregates\Events\PagePublished',
            'Modules\Cms\Domain\Aggregates\Events\PageDeleted',
            // Add more as needed
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Rules
    |--------------------------------------------------------------------------
    */
    'business_rules' => [
        // Require at least 1 block before publishing
        'require_blocks_for_publish' => env('CMS_REQUIRE_BLOCKS', true),

        // SEO score threshold for publishing
        'min_seo_score' => env('CMS_MIN_SEO_SCORE', 60),

        // Max blocks per page
        'max_blocks_per_page' => env('CMS_MAX_BLOCKS', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Configuration
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => true,
        'track_user' => true,
        'track_ip' => true,
        'track_user_agent' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Console Commands
    |--------------------------------------------------------------------------
    |
    | CLI commands for this module
    |
    */
    'commands' => [
        'Modules\Cms\Console\PageEventSourcingCommand',
        'Modules\Cms\Console\BlockEventSourcingCommand',
    ],
];
