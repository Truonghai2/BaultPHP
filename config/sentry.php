<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sentry DSN
    |--------------------------------------------------------------------------
    |
    | The DSN (Data Source Name) for your Sentry project. This is the
    | most important configuration value. It tells the Sentry SDK
    | where to send events. You should set this in your .env file.
    |
    | Example: SENTRY_DSN=https://xxxxxxxxxxxxxxxxxxxxxxxx@xxxx.ingest.sentry.io/xxxxxxx
    |
    */
    'dsn' => env('SENTRY_DSN', null),

    // Capture sensitive data-carrying PII, like user data.
    'send_default_pii' => true,

    // You can add other Sentry options here if needed, for example:
    // 'traces_sample_rate' => 1.0,
    // 'release' => 'my-project-name@1.0.0',

];
