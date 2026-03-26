<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GraphQL Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for GraphQL features including DataLoader and Federation.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | DataLoader Configuration
    |--------------------------------------------------------------------------
    */
    'dataloader' => [
        'cache' => env('GRAPHQL_DATALOADER_CACHE', true),
        'ttl' => env('GRAPHQL_DATALOADER_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Federation Configuration
    |--------------------------------------------------------------------------
    */
    'federation' => [
        'enabled' => env('GRAPHQL_FEDERATION_ENABLED', false),
        'timeout' => env('GRAPHQL_FEDERATION_TIMEOUT', 10),
        'subgraphs' => [
            // Example subgraph configuration:
            // 'user_service' => [
            //     'url' => env('USER_SERVICE_GRAPHQL_URL', 'http://localhost:8001/graphql'),
            //     'options' => [],
            // ],
        ],
    ],
];
