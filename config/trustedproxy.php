<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Set trusted proxy IP addresses. These proxies will be allowed to send
    | X-Forwarded-* headers that will be used to determine the client's
    | actual IP address and other connection information.
    |
    | Use '*' to trust all proxies (not recommended for production)
    | Use '**' to trust all proxies that connect via a private network
    | Use specific IPs: ['192.168.1.1', '10.0.0.1']
    | Use CIDR notation: ['192.168.1.0/24']
    |
    */
    'proxies' => env('TRUSTED_PROXIES', null),

    /*
    |--------------------------------------------------------------------------
    | Trusted Headers
    |--------------------------------------------------------------------------
    |
    | Headers to trust from proxies. You can customize which headers are trusted
    | for determining client information.
    |
    */
    'headers' => [
        // Client IP detection headers (in order of priority)
        'client_ip' => [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_FORWARDED',
        ],

        // Host header
        'client_host' => 'HTTP_X_FORWARDED_HOST',

        // Port header
        'client_port' => 'HTTP_X_FORWARDED_PORT',

        // Protocol header (http/https)
        'client_proto' => 'HTTP_X_FORWARDED_PROTO',

        // The Forwarded header (RFC 7239)
        'forwarded' => 'HTTP_FORWARDED',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trust All Proxies in Private Networks
    |--------------------------------------------------------------------------
    |
    | When set to true, all requests from private network ranges will be
    | considered as coming from trusted proxies.
    |
    | Private ranges: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8
    |
    */
    'trust_private_networks' => env('TRUST_PRIVATE_NETWORKS', false),
];
