<?php

/**
 * User Module Settings Configuration
 * 
 * Define all configurable settings for the User module.
 * These can be managed through the admin panel at /admin/modules/User/settings
 */

return [
    // Registration Settings
    'registration' => [
        'allow_registration' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Allow User Registration',
            'description' => 'Enable or disable new user registration',
            'order' => 1,
        ],
        'require_email_verification' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Require Email Verification',
            'description' => 'Users must verify their email before logging in',
            'order' => 2,
        ],
        'default_role' => [
            'type' => 'select',
            'default' => 'user',
            'label' => 'Default User Role',
            'description' => 'Role assigned to new users upon registration',
            'options' => [
                'user' => 'User',
                'subscriber' => 'Subscriber',
                'member' => 'Member',
            ],
            'order' => 3,
        ],
    ],

    // Authentication Settings
    'authentication' => [
        'max_login_attempts' => [
            'type' => 'integer',
            'default' => 5,
            'label' => 'Max Login Attempts',
            'description' => 'Maximum failed login attempts before lockout',
            'min' => 3,
            'max' => 10,
            'order' => 1,
        ],
        'lockout_duration' => [
            'type' => 'integer',
            'default' => 900,
            'label' => 'Lockout Duration (seconds)',
            'description' => 'How long to lock out user after max attempts (15 minutes default)',
            'min' => 60,
            'max' => 3600,
            'order' => 2,
        ],
        'session_lifetime' => [
            'type' => 'integer',
            'default' => 43200,
            'label' => 'Session Lifetime (minutes)',
            'description' => 'How long user sessions last (30 days default)',
            'min' => 60,
            'max' => 525600,
            'order' => 3,
        ],
        'remember_me_duration' => [
            'type' => 'integer',
            'default' => 10080,
            'label' => 'Remember Me Duration (minutes)',
            'description' => 'How long "Remember Me" lasts (7 days default)',
            'min' => 1440,
            'max' => 525600,
            'order' => 4,
        ],
    ],

    // Password Settings
    'password' => [
        'min_length' => [
            'type' => 'integer',
            'default' => 8,
            'label' => 'Minimum Password Length',
            'description' => 'Minimum number of characters required',
            'min' => 6,
            'max' => 32,
            'order' => 1,
        ],
        'require_uppercase' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Require Uppercase Letter',
            'description' => 'Password must contain at least one uppercase letter',
            'order' => 2,
        ],
        'require_lowercase' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Require Lowercase Letter',
            'description' => 'Password must contain at least one lowercase letter',
            'order' => 3,
        ],
        'require_number' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Require Number',
            'description' => 'Password must contain at least one number',
            'order' => 4,
        ],
        'require_special_char' => [
            'type' => 'boolean',
            'default' => false,
            'label' => 'Require Special Character',
            'description' => 'Password must contain at least one special character (!@#$%^&*)',
            'order' => 5,
        ],
    ],

    // OAuth Settings
    'oauth' => [
        'enable_oauth' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Enable OAuth2',
            'description' => 'Allow OAuth2 authentication',
            'order' => 1,
        ],
        'access_token_lifetime' => [
            'type' => 'integer',
            'default' => 3600,
            'label' => 'Access Token Lifetime (seconds)',
            'description' => 'How long access tokens are valid (1 hour default)',
            'min' => 300,
            'max' => 86400,
            'order' => 2,
        ],
        'refresh_token_lifetime' => [
            'type' => 'integer',
            'default' => 1209600,
            'label' => 'Refresh Token Lifetime (seconds)',
            'description' => 'How long refresh tokens are valid (14 days default)',
            'min' => 86400,
            'max' => 2592000,
            'order' => 3,
        ],
    ],

    // Profile Settings
    'profile' => [
        'allow_avatar_upload' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Allow Avatar Upload',
            'description' => 'Users can upload profile avatars',
            'order' => 1,
        ],
        'max_avatar_size' => [
            'type' => 'integer',
            'default' => 2048,
            'label' => 'Max Avatar Size (KB)',
            'description' => 'Maximum file size for avatar uploads',
            'min' => 512,
            'max' => 10240,
            'order' => 2,
        ],
        'allowed_avatar_types' => [
            'type' => 'array',
            'default' => ['jpg', 'jpeg', 'png', 'gif'],
            'label' => 'Allowed Avatar Types',
            'description' => 'File types allowed for avatars',
            'order' => 3,
        ],
    ],

    // Email Settings
    'email' => [
        'send_welcome_email' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Send Welcome Email',
            'description' => 'Send email when user registers',
            'order' => 1,
        ],
        'welcome_email_subject' => [
            'type' => 'string',
            'default' => 'Welcome to {app_name}!',
            'label' => 'Welcome Email Subject',
            'description' => 'Subject line for welcome emails. Use {app_name} for app name',
            'order' => 2,
        ],
    ],

    // Security Settings
    'security' => [
        'enable_2fa' => [
            'type' => 'boolean',
            'default' => false,
            'label' => 'Enable Two-Factor Authentication',
            'description' => 'Allow users to enable 2FA on their accounts',
            'order' => 1,
        ],
        'detect_suspicious_login' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Detect Suspicious Logins',
            'description' => 'Send alerts for logins from new devices/locations',
            'order' => 2,
        ],
        'log_authentication_attempts' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Log Authentication Attempts',
            'description' => 'Keep logs of all login attempts',
            'order' => 3,
        ],
    ],

    // Performance Settings
    'performance' => [
        'cache_user_permissions' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Cache User Permissions',
            'description' => 'Cache user permissions for better performance',
            'order' => 1,
        ],
        'permission_cache_ttl' => [
            'type' => 'integer',
            'default' => 3600,
            'label' => 'Permission Cache TTL (seconds)',
            'description' => 'How long to cache user permissions',
            'min' => 300,
            'max' => 86400,
            'order' => 2,
        ],
    ],
];

