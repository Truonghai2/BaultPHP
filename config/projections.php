<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stream Projections
    |--------------------------------------------------------------------------
    |
    | Configure read model projections that automatically update from
    | event streams.
    |
    */

    'enabled' => env('PROJECTIONS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Projections to Run
    |--------------------------------------------------------------------------
    |
    | List of projection classes to run.
    |
    */

    'projections' => [
        \Modules\Todo\Projections\TodoStatsProjection::class,
        // Add more projections here
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Stop on error: If true, stops processing on first error.
    | If false, logs error and continues with next event.
    |
    */

    'stop_on_error' => env('PROJECTIONS_STOP_ON_ERROR', false),

    /*
    |--------------------------------------------------------------------------
    | Batch Size
    |--------------------------------------------------------------------------
    |
    | Number of events to process in each batch.
    |
    */

    'batch_size' => env('PROJECTIONS_BATCH_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Poll Interval
    |--------------------------------------------------------------------------
    |
    | Interval between event polling (milliseconds).
    | Lower = more real-time, higher = less CPU.
    |
    */

    'poll_interval' => env('PROJECTIONS_POLL_INTERVAL', 1000),

    /*
    |--------------------------------------------------------------------------
    | Auto-Start
    |--------------------------------------------------------------------------
    |
    | Automatically start projections on application boot.
    |
    */

    'auto_start' => env('PROJECTIONS_AUTO_START', false),
];
