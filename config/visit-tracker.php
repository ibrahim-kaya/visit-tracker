<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    |
    | The paths you specify here will not be logged.
    | Wildcards are supported (Str::is).
    |
    */
    'excluded_paths' => [
        'livewire/*',
        'livewire/update',
        '_debugbar/*',
        'telescope/*',
        'horizon/*',
        'up',
        'sanctum/csrf-cookie',
        'broadcasting/auth',
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
    | Skip AJAX / JSON Requests
    |--------------------------------------------------------------------------
    |
    | When true, requests that expect JSON or are XHR are not logged.
    |
    */
    'skip_ajax' => true,

    /*
    |--------------------------------------------------------------------------
    | Skip Prefetch Requests
    |--------------------------------------------------------------------------
    |
    | When true, browser prefetch / prerender requests are not logged.
    |
    */
    'skip_prefetch' => true,

    /*
    |--------------------------------------------------------------------------
    | Sample Rate
    |--------------------------------------------------------------------------
    |
    | Fraction of eligible requests to log (0.0–1.0). 1.0 logs every visit.
    | Example: 0.1 logs roughly 10% of visits.
    |
    */
    'sample_rate' => 1.0,

    /*
    |--------------------------------------------------------------------------
    | Dedupe Window (seconds)
    |--------------------------------------------------------------------------
    |
    | Skip logging when the same visitor hits the same page_url within this
    | many seconds. Set to 0 to disable. Uses the application cache.
    |
    */
    'dedupe_seconds' => 0,

    /*
    |--------------------------------------------------------------------------
    | IP Info Cache Duration
    |--------------------------------------------------------------------------
    |
    | How long successful IP geolocation lookups are cached (seconds).
    |
    */
    'ip_info_cache_duration' => 24 * 60 * 60, // 24 hours

    /*
    |--------------------------------------------------------------------------
    | IP Info Negative Cache Duration
    |--------------------------------------------------------------------------
    |
    | How long failed / rate-limited IP lookups are cached as a miss (seconds).
    | Prevents hammering ip-api.com when a lookup fails.
    |
    */
    'ip_info_negative_cache_duration' => 15 * 60, // 15 minutes

    /*
    |--------------------------------------------------------------------------
    | Logging Bots
    |--------------------------------------------------------------------------
    |
    | If the incoming visitor is a bot (Google bot, search engine bot, etc.),
    | should it be logged?
    |
    */
    'log_bots' => false,

    /*
    |--------------------------------------------------------------------------
    | Detailed IP Info
    |--------------------------------------------------------------------------
    |
    | When true, fetches geolocation from http://ip-api.com (free tier:
    | 45 requests/minute, HTTP only). Prefer leaving this false unless needed;
    | lookups run after the response is sent (terminate) or in a queue job.
    |
    */
    'detailed_ip_info' => false,

    /*
    |--------------------------------------------------------------------------
    | Use Queue System
    |--------------------------------------------------------------------------
    |
    | When true, visit logging is dispatched to a Laravel queue.
    | When false, the visit is written after the response is sent (terminate),
    | which is often the most efficient setup (one INSERT, no worker).
    | If you use queues, prefer redis over the database driver.
    |
    */
    'use_queue' => false,

    /*
    |--------------------------------------------------------------------------
    | Queue Connection / Queue Name
    |--------------------------------------------------------------------------
    |
    | Optional. Leave null/empty to use the application's default queue connection.
    | Set these when your worker/Horizon listens on a different connection than
    | QUEUE_CONNECTION (e.g. Horizon on redis while QUEUE_CONNECTION=database).
    |
    */
    'queue_connection' => env('VISIT_TRACKER_QUEUE_CONNECTION'),
    'queue_name' => env('VISIT_TRACKER_QUEUE'),

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

    /*
    |--------------------------------------------------------------------------
    | Attribute Visits On Auth
    |--------------------------------------------------------------------------
    |
    | When true, anonymous visits from the same browser (visitor cookie and/or
    | current session) are assigned to the user after login or registration.
    |
    */
    'attribute_on_auth' => true,

    /*
    |--------------------------------------------------------------------------
    | Visitor Cookie
    |--------------------------------------------------------------------------
    |
    | Persistent cookie used to recognize an anonymous visitor across session
    | regenerations (login/logout). Required for reliable attribution.
    |
    */
    'visitor_cookie' => 'visit_tracker_vid',

    /*
    |--------------------------------------------------------------------------
    | Visitor Cookie Lifetime (minutes)
    |--------------------------------------------------------------------------
    |
    | How long the visitor cookie should last. Default: 2 years.
    |
    */
    'visitor_cookie_minutes' => 60 * 24 * 365 * 2,

    /*
    |--------------------------------------------------------------------------
    | Retention Days
    |--------------------------------------------------------------------------
    |
    | Automatically prune visit logs older than this many days via
    | `php artisan model:prune`. Set to null to disable pruning.
    | Schedule: $schedule->command('model:prune')->daily();
    |
    */
    'retention_days' => null,

    /*
    |--------------------------------------------------------------------------
    | Statistics Cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long PageVisitLog summary/statistic helpers cache their results.
    | Set to 0 to disable caching.
    |
    */
    'statistics_cache_ttl' => 60,
];
