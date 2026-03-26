<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Enhancements Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for security enhancements:
    | - Zero-Trust Architecture
    | - AI-Powered Threat Detection
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Zero-Trust Authentication Configuration
    |--------------------------------------------------------------------------
    */
    'zerotrust' => [
        'enabled' => env('ZEROTRUST_ENABLED', false),
        'verification_interval' => env('ZEROTRUST_VERIFICATION_INTERVAL', 300), // seconds
        'max_risk_score' => env('ZEROTRUST_MAX_RISK_SCORE', 0.7),
        'require_device_verification' => env('ZEROTRUST_REQUIRE_DEVICE', true),
        'ip_whitelist' => env('ZEROTRUST_IP_WHITELIST', '') ? explode(',', env('ZEROTRUST_IP_WHITELIST')) : [],
        'ip_blacklist' => env('ZEROTRUST_IP_BLACKLIST', '') ? explode(',', env('ZEROTRUST_IP_BLACKLIST')) : [],
        'malicious_ips' => env('ZEROTRUST_MALICIOUS_IPS', '') ? explode(',', env('ZEROTRUST_MALICIOUS_IPS')) : [],
        'allowed_hours' => env('ZEROTRUST_ALLOWED_HOURS', '') ? explode(',', env('ZEROTRUST_ALLOWED_HOURS')) : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Threat Detection Configuration
    |--------------------------------------------------------------------------
    */
    'threat_detection' => [
        'enabled' => env('THREAT_DETECTION_ENABLED', false),
        'max_requests_per_minute' => env('THREAT_MAX_REQUESTS_PER_MINUTE', 60),
        'max_requests_per_hour' => env('THREAT_MAX_REQUESTS_PER_HOUR', 1000),
        'max_threats_per_ip' => env('THREAT_MAX_THREATS_PER_IP', 10),
        'auto_block' => env('THREAT_AUTO_BLOCK', true),
        'malicious_ips' => env('THREAT_MALICIOUS_IPS', '') ? explode(',', env('THREAT_MALICIOUS_IPS')) : [],
        'unusual_endpoints' => env('THREAT_UNUSUAL_ENDPOINTS', '') ? explode(',', env('THREAT_UNUSUAL_ENDPOINTS')) : [],
        'unusual_methods' => env('THREAT_UNUSUAL_METHODS', '') ? explode(',', env('THREAT_UNUSUAL_METHODS')) : [],
        'threat_patterns' => [
            // Add custom threat patterns here
            // '/pattern/' => 0.5, // score
        ],
    ],
];
