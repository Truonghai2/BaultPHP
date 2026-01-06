<?php

/**
 * Module Settings Schema Example
 *
 * This file shows how to define settings for your module.
 * Copy this to your module directory as 'settings.php'
 *
 * Example: Modules/YourModule/settings.php
 */

return [
    // General Settings Group
    'general' => [
        'enabled' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Enable Module',
            'description' => 'Enable or disable this module',
            'order' => 1,
        ],
        'title' => [
            'type' => 'string',
            'default' => 'My Module',
            'label' => 'Module Title',
            'description' => 'Display title for the module',
            'required' => true,
            'order' => 2,
        ],
        'max_items' => [
            'type' => 'integer',
            'default' => 10,
            'label' => 'Maximum Items',
            'description' => 'Maximum number of items to display',
            'min' => 1,
            'max' => 100,
            'order' => 3,
        ],
    ],

    // API Settings Group
    'api' => [
        'api_key' => [
            'type' => 'string',
            'default' => '',
            'label' => 'API Key',
            'description' => 'Your API key for external service',
            'required' => true,
            'encrypted' => true, // This will be encrypted in database
            'order' => 1,
        ],
        'api_url' => [
            'type' => 'string',
            'default' => 'https://api.example.com',
            'label' => 'API URL',
            'description' => 'Base URL for API requests',
            'pattern' => '/^https?:\/\/.+/', // Regex validation
            'order' => 2,
        ],
        'timeout' => [
            'type' => 'integer',
            'default' => 30,
            'label' => 'API Timeout (seconds)',
            'description' => 'Request timeout in seconds',
            'min' => 5,
            'max' => 300,
            'order' => 3,
        ],
    ],

    // Display Settings Group
    'display' => [
        'theme' => [
            'type' => 'select',
            'default' => 'light',
            'label' => 'Theme',
            'description' => 'Select display theme',
            'options' => [
                'light' => 'Light Theme',
                'dark' => 'Dark Theme',
                'auto' => 'Auto (System)',
            ],
            'order' => 1,
        ],
        'show_avatars' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Show User Avatars',
            'description' => 'Display user avatars in listings',
            'order' => 2,
        ],
        'items_per_page' => [
            'type' => 'integer',
            'default' => 20,
            'label' => 'Items Per Page',
            'description' => 'Number of items to show per page',
            'enum' => [10, 20, 30, 50, 100], // Only allow these values
            'order' => 3,
        ],
    ],

    // Advanced Settings Group
    'advanced' => [
        'debug_mode' => [
            'type' => 'boolean',
            'default' => false,
            'label' => 'Debug Mode',
            'description' => 'Enable debug logging (not recommended for production)',
            'public' => false, // Only accessible to admins
            'order' => 1,
        ],
        'cache_lifetime' => [
            'type' => 'integer',
            'default' => 3600,
            'label' => 'Cache Lifetime (seconds)',
            'description' => 'How long to cache data',
            'min' => 60,
            'max' => 86400,
            'order' => 2,
        ],
        'custom_config' => [
            'type' => 'json',
            'default' => '{}',
            'label' => 'Custom Configuration',
            'description' => 'JSON configuration for advanced users',
            'order' => 3,
        ],
    ],
];
