<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    |
    | The paths you specify here will not be logged.
    | Example: ['telescope/*', 'horizon/*', 'admin/*']
    |
    */
    'excluded_paths' => [
	
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded HTTP Methods
    |--------------------------------------------------------------------------
    |
    | The HTTP methods you specify here will not be logged.
    | Example: ['POST', 'PATCH', 'PUT'] - these requests will not be logged
    | Leave empty array [] to log all methods.
    |
    */
    'excluded_methods' => [
	
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Info Cache Duration
    |--------------------------------------------------------------------------
    |
    | Determine how long you want to keep IP information in the cache.
    | Example: '24h' or '3600' (seconds)
    |
    */
    'ip_info_cache_duration' => 24 * 60 * 60, // 24 hours (in seconds)
	
	/*
    |--------------------------------------------------------------------------
    | Logging Bots
    |--------------------------------------------------------------------------
    |
    | If the incoming visitor is a bot (Google bot, search engine bot, etc.), should it be logged?
    |
    */
    'log_bots' => false,
	
	/*
    |--------------------------------------------------------------------------
    | Detailed IP Info
    |--------------------------------------------------------------------------
    |
    | If this option is set to true, it will retrieve detailed information from http://ip-api.com.
    |
    */
    'detailed_ip_info' => true,

    /*
    |--------------------------------------------------------------------------
    | Use Queue System
    |--------------------------------------------------------------------------
    |
    | If this option is set to true, visit logging will be processed via Laravel queues.
    | If false, it will be processed synchronously (blocking).
    | Recommended: true for production, false for development/testing.
    |
    */
    'use_queue' => true,

    /*
    |--------------------------------------------------------------------------
    | Log Payload
    |--------------------------------------------------------------------------
    |
    | If this option is set to true, request payload/body data will be logged.
    | Set to false to disable payload logging for privacy/security reasons.
    |
    */
    'log_payload' => false,

    /*
    |--------------------------------------------------------------------------
    | Excluded Payload Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should be excluded from the payload when logging requests.
    | This is useful for excluding sensitive data like passwords, tokens, etc.
    | Example: ['password', 'password_confirmation', 'token', '_token']
    | Note: This only applies if 'log_payload' is set to true.
    |
    */
    'excluded_payload_fields' => [
        'password',
        'password_confirmation',
        'token',
        '_token',
    ],
];
