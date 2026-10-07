## Laravel Visit Tracker by _[İbrahim Kaya](https://ibrahimkaya.dev)_

[![Latest Stable Version](http://poser.pugx.org/ibrahim-kaya/visit-tracker/v)](https://packagist.org/packages/ibrahim-kaya/visit-tracker) [![Total Downloads](http://poser.pugx.org/ibrahim-kaya/visit-tracker/downloads)](https://packagist.org/packages/ibrahim-kaya/visit-tracker) [![Latest Unstable Version](http://poser.pugx.org/ibrahim-kaya/visit-tracker/v/unstable)](https://packagist.org/packages/ibrahim-kaya/visit-tracker) [![License](http://poser.pugx.org/ibrahim-kaya/visit-tracker/license)](https://packagist.org/packages/ibrahim-kaya/visit-tracker) [![PHP Version Require](http://poser.pugx.org/ibrahim-kaya/visit-tracker/require/php)](https://packagist.org/packages/ibrahim-kaya/visit-tracker)

A **Laravel package** to automatically track page visits including IP, browser, device, referrer, and more. Perfect for analytics and monitoring.

---

## Features

- Automatic tracking of all web requests.
- **Terminable middleware** — UA parsing and DB writes run after the response is sent (does not delay TTFB).
- Optional **queue-based** processing when you prefer a worker.
- Logs detailed visitor information:
  - IP address (with optional geolocation from http://ip-api.com)
  - Browser name, platform/OS, device type
  - Referrer URL, full URL, normalized path
  - User agent, HTTP method, optional payload
  - Authenticated user ID (if logged in)
  - Country code (when detailed IP info is enabled)
- **Attribute anonymous visits after login/register** (visitor cookie + session)
- Exclude paths/methods; skip AJAX and prefetch by default
- Optional sampling and per-visitor/page dedupe
- Optional bot logging (cheap bot pre-check when disabled)
- Configurable IP info cache (positive + negative) and statistics cache
- Optional retention pruning via `model:prune`
- Middleware auto-registered for all web routes

---

## Installation

### 1. Require the package via Composer

```bash
composer require ibrahim-kaya/visit-tracker
```

### 2. Publish the configuration

```bash
php artisan vendor:publish --provider="IbrahimKaya\VisitTracker\VisitTrackerServiceProvider" --tag=visit-tracker-config
```

### 3. Run migrations

```bash
php artisan migrate
```

Creates / updates the `page_visit_logs` table (including performance indexes, `path`, and `country_code`).

> **Note:** On very large existing tables, adding indexes may take time. Prefer a maintenance window.

### 4. Queue (optional)

By default `use_queue` is `false`. Tracking still runs **after** the HTTP response is sent (`terminate`), so a single INSERT is usually enough and no worker is required.

If you prefer queues:

```bash
QUEUE_CONNECTION=redis
# or database / sync
```

Prefer **redis** over the `database` queue driver (database queues add INSERT jobs + DELETE jobs per visit).

```bash
php artisan queue:work
```

---

## Performance recommendations

| Topic | Recommendation |
|-------|----------------|
| Response time | Tracking runs in `terminate()` after the response is sent — keep it that way. |
| Queues | Default `use_queue => false` is fine for most sites. If you enable queues, use **redis**. |
| Browser detect cache | Publish `hisorange/browser-detect` config and raise `browser-detect.cache.interval` to **7–30 days** (UA → result is deterministic). Prefer **redis** or **apcu** as the app cache store instead of `file`. |
| IP geolocation | Keep `detailed_ip_info => false` unless needed. Free ip-api.com allows **45 req/min**, HTTP only. Failures are negatively cached. |
| Noise | Defaults skip Livewire/Debugbar/Telescope/Horizon/health/Sanctum/broadcasting, AJAX, and prefetch. Tune `sample_rate` / `dedupe_seconds` under heavy traffic. |
| Retention | Set `retention_days` and schedule `$schedule->command('model:prune')->daily();` |

---

## Configuration

`config/visit-tracker.php` (key options):

```php
return [
    'excluded_paths' => [
        'livewire/*',
        '_debugbar/*',
        'telescope/*',
        'horizon/*',
        'up',
        'sanctum/csrf-cookie',
        'broadcasting/auth',
    ],

    'excluded_methods' => [],

    'skip_ajax' => true,
    'skip_prefetch' => true,

    'sample_rate' => 1.0,      // 0.0–1.0
    'dedupe_seconds' => 0,     // 0 = disabled

    'log_bots' => false,

    'detailed_ip_info' => false,
    'ip_info_cache_duration' => 86400,
    'ip_info_negative_cache_duration' => 900,

    // After-response INSERT (no worker). Set true only if you want a queue worker.
    'use_queue' => false,
    'queue_connection' => env('VISIT_TRACKER_QUEUE_CONNECTION'),
    'queue_name' => env('VISIT_TRACKER_QUEUE'),

    'log_payload' => false,
    'excluded_payload_fields' => [
        'password',
        'password_confirmation',
        'token',
        '_token',
    ],

    'attribute_on_auth' => true,
    'visitor_cookie' => 'visit_tracker_vid',
    'visitor_cookie_minutes' => 60 * 24 * 365 * 2,

    'retention_days' => null,          // e.g. 90
    'statistics_cache_ttl' => 60,      // seconds; 0 disables
];
```

- **excluded_paths** — Wildcards supported. Defaults cover common framework noise.
- **skip_ajax / skip_prefetch** — Avoid logging XHR/JSON and browser prefetch/prerender.
- **sample_rate** — Log only a fraction of visits (e.g. `0.1` ≈ 10%).
- **dedupe_seconds** — Skip repeats of the same visitor + `page_url` within N seconds (cache-backed).
- **log_bots** — When `false`, a cheap CrawlerDetect check skips bots before the heavy UA pipeline.
- **detailed_ip_info** — Optional ip-api.com lookup (after response / in job).
- **use_queue** — `false` = write in `terminate`; `true` = dispatch `ProcessVisitLog`.
- **retention_days** — Enable `MassPrunable` cleanup via `model:prune`.
- **statistics_cache_ttl** — Short TTL cache around `PageVisitLog` statistic helpers.

### Attribute anonymous visits after login/register

When `attribute_on_auth` is enabled (default), the package:

1. Stores a persistent `visitor_id` cookie while the guest browses (queued only when missing)
2. Saves that `visitor_id` (and `session_id`) on each visit log
3. On `Login` / `Registered`, updates matching rows where `user_id` is null

```php
use IbrahimKaya\VisitTracker\Models\PageVisitLog;

PageVisitLog::attributeToUser(
    auth()->id(),
    request()->cookie(config('visit-tracker.visitor_cookie')),
    session()->getId()
);
```

### Retention pruning

```php
// config/visit-tracker.php
'retention_days' => 90,

// app/Console/Kernel.php or routes/console.php
$schedule->command('model:prune', [
    '--model' => [\IbrahimKaya\VisitTracker\Models\PageVisitLog::class],
])->daily();
```

---

## Usage

No extra code is required. Visit any web page and the visit is logged automatically after the response is sent.

```php
use IbrahimKaya\VisitTracker\Models\PageVisitLog;

$recentVisits = PageVisitLog::latest()->take(5)->get();

foreach ($recentVisits as $visit) {
    echo $visit->ip_address;
    echo $visit->browser;
    echo $visit->device_type;
    echo $visit->path;
    echo $visit->country_code;
    echo $visit->method;
    echo $visit->payload;
}
```

Optional manual middleware registration:

```php
protected $middleware = [
    \IbrahimKaya\VisitTracker\Middleware\VisitTracker::class,
];
```

---

## Statistics

The `PageVisitLog` model provides static helpers (results are cached for `statistics_cache_ttl` seconds).

> For custom reporting, query the model with Eloquent directly. Aggregations prefer the indexed `path` / `country_code` columns when present.

### Basic Statistics

```php
use IbrahimKaya\VisitTracker\Models\PageVisitLog;

$total = PageVisitLog::totalVisits();
$total = PageVisitLog::totalVisits(true); // exclude bots

$unique = PageVisitLog::uniqueVisitors();
$uniqueIps = PageVisitLog::uniqueIpAddresses(true);
```

### Page Statistics

```php
$topPages = PageVisitLog::mostVisitedPages(10);
$topPages = PageVisitLog::mostVisitedPages(5, true);

foreach ($topPages as $page) {
    echo $page->page_url . ': ' . $page->visit_count . ' visits';
}

$visits = PageVisitLog::visitsByDateRange('2024-01-01', '2024-01-31', true);
```

### Device & Browser Statistics

```php
$deviceStats = PageVisitLog::statisticsByDeviceType(true);
$browserStats = PageVisitLog::statisticsByBrowser();
$platformStats = PageVisitLog::statisticsByPlatform();
```

### Referrer & Time-based

```php
$referrers = PageVisitLog::statisticsByReferrer(10, true);
$daily = PageVisitLog::dailyStatistics(30, true);
```

### Geographic Statistics

```php
// Prefer country_code (filled when detailed_ip_info is enabled)
$countryStats = PageVisitLog::statisticsByCountry(true);

foreach ($countryStats as $stat) {
    echo $stat['country'] . ': ' . $stat['count'] . ' visits';
}
```

### Summary

```php
$summary = PageVisitLog::summaryStatistics(30, true);
// total_visits, unique_visitors, unique_ips, top_pages, by_device, by_browser, by_platform
```

### Query Scopes

```php
$visits = PageVisitLog::excludeBots()
    ->dateRange('2024-01-01', '2024-01-31')
    ->get();
```

---

## License

MIT License © [İbrahim Kaya](https://ibrahimkaya.dev)
