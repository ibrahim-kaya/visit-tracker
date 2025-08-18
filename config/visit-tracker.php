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
];
