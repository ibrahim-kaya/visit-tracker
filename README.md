## Laravel Visit Tracker by _[İbrahim Kaya](https://ibrahimkaya.dev)_

[![Latest Stable Version](http://poser.pugx.org/ibrahim-kaya/visit-tracker/v)](https://packagist.org/packages/ibrahim-kaya/visit-tracker) [![Total Downloads](http://poser.pugx.org/ibrahim-kaya/visit-tracker/downloads)](https://packagist.org/packages/ibrahim-kaya/visit-tracker) [![Latest Unstable Version](http://poser.pugx.org/ibrahim-kaya/visit-tracker/v/unstable)](https://packagist.org/packages/ibrahim-kaya/visit-tracker) [![License](http://poser.pugx.org/ibrahim-kaya/visit-tracker/license)](https://packagist.org/packages/ibrahim-kaya/visit-tracker) [![PHP Version Require](http://poser.pugx.org/ibrahim-kaya/visit-tracker/require/php)](https://packagist.org/packages/ibrahim-kaya/visit-tracker)

A **Laravel package** to automatically track page visits including IP, browser, device, referrer, and more. Perfect for analytics and monitoring.

---

## 🌟 Features

- Automatic tracking of all web requests.
- **Queue-based processing** for better performance.
- Logs detailed visitor information:
  - IP address (with optional geolocation from http://ip-api.com)
  - Browser name
  - Platform/OS
  - Device type
  - Referrer URL
  - Full URL
  - User agent
  - HTTP method (GET, POST, PUT, DELETE, etc.)
  - Request payload/body (optional, configurable with sensitive data exclusion)
  - Authenticated user ID (if logged in)
- Exclude specific routes or paths.
- Exclude specific HTTP methods (e.g., POST, PATCH).
- Optional logging of bots.
- Configurable IP info cache duration.
- Middleware auto-registered for all web routes.
- **Asynchronous IP geolocation** processing via Laravel queues.

---

## 🚀 Installation

### 1️⃣ Require the package via Composer

```bash
composer require ibrahim-kaya/visit-tracker
```

---

### 2️⃣ Publish the configuration

```bash
php artisan vendor:publish --provider="IbrahimKaya\VisitTracker\VisitTrackerServiceProvider" --tag=visit-tracker-config
```

- Creates `config/visit-tracker.php`.

---

### 3️⃣ Run migrations

```bash
php artisan migrate
```

- Creates `page_visit_logs` table.

### 4️⃣ Configure queue system (optional)

**If you want to use queues** (recommended for production), make sure your Laravel application has a queue driver configured in `.env`:

```bash
QUEUE_CONNECTION=database
# or
QUEUE_CONNECTION=redis
# or
QUEUE_CONNECTION=sync
```

If using database queues, run:
```bash
php artisan queue:table
php artisan migrate
```

**If you don't want to use queues**, set `use_queue => false` in `config/visit-tracker.php`. This will process visits synchronously (useful for development/testing).



**Start queue worker (only if using queues):**

```bash
php artisan queue:work
```

**Or for production (supervisor recommended):**

```bash
php artisan queue:work --daemon
```

---

## ⚙️ Configuration

`config/visit-tracker.php`:

```php
return [
    'excluded_paths' => [
        'admin/*',
        'telescope/*',
    ],

    'excluded_methods' => [
        'POST',
        'PATCH',
    ],

    'log_bots' => false,

    'ip_info_cache_duration' => 86400, // seconds
    
    'use_queue' => true, // Use Laravel queues for processing

    'log_payload' => false, // Set to true to log request payload/body data

    'excluded_payload_fields' => [
        'password',
        'password_confirmation',
        'token',
        '_token',
    ],
];
```

- **excluded\_paths** → Wildcards supported. Paths that will not be logged.
- **excluded\_methods** → HTTP methods that will not be logged. Example: `['POST', 'PATCH']` - these requests will not be logged. Leave empty array `[]` to log all methods.
- **log\_bots** → Set `true` to log bot visits.
- **ip\_info\_cache\_duration** → Cache IP info to reduce API calls.
- **use\_queue** → Set `true` to use Laravel queues, `false` for synchronous processing.
- **log\_payload** → Set `true` to log request payload/body data. Set to `false` to disable payload logging for privacy/security reasons.
- **excluded\_payload\_fields** → Fields to exclude from request payload logging (useful for sensitive data like passwords, tokens, etc.). Only applies if `log_payload` is `true`.

---

## 💻 Usage

No extra code is required. Visit any web page and the visit is logged automatically.


**Retrieve logs example:**

```php
use IbrahimKaya\VisitTracker\Models\PageVisitLog;

$recentVisits = PageVisitLog::latest()->take(5)->get();

foreach ($recentVisits as $visit) {
    echo $visit->ip_address;
    echo $visit->browser;
    echo $visit->device_type;
    echo $visit->method; // HTTP method (GET, POST, etc.)
    echo $visit->payload; // Request payload (array, null for GET requests)
}
```


**Optional manual middleware:**

```php
protected $middleware = [
    \IbrahimKaya\VisitTracker\Middleware\VisitTracker::class,
];
```

---

## 📊 Statistics

The `PageVisitLog` model provides various static methods to retrieve statistics about your visits.

> **Note:** The examples below are just some quick use functions. For detailed usage, you can query the model directly using Laravel's Eloquent methods to retrieve and manipulate the data as needed. All visit data is stored in the `page_visit_logs` table and can be accessed through the `PageVisitLog` model.

### Basic Statistics

**Total Visits:**
```php
use IbrahimKaya\VisitTracker\Models\PageVisitLog;

// Get total visits (including bots)
$total = PageVisitLog::totalVisits();

// Get total visits excluding bots
$total = PageVisitLog::totalVisits(true);
```

**Unique Visitors:**
```php
// Counts unique visitors using user_id (if logged in) or session_id
// This ensures logged-in users are counted correctly even if their session_id changes
$unique = PageVisitLog::uniqueVisitors();
$unique = PageVisitLog::uniqueVisitors(true); // Exclude bots
```

**Unique IP Addresses:**
```php
$uniqueIps = PageVisitLog::uniqueIpAddresses();
$uniqueIps = PageVisitLog::uniqueIpAddresses(true); // Exclude bots
```

### Page Statistics

**Most Visited Pages:**
```php
// Get top 10 most visited pages
$topPages = PageVisitLog::mostVisitedPages(10);

// Get top 5 most visited pages excluding bots
$topPages = PageVisitLog::mostVisitedPages(5, true);

// Access results
foreach ($topPages as $page) {
    echo $page->page_url . ': ' . $page->visit_count . ' visits';
}
```

**Visits by Date Range:**
```php
// Get visits for a specific date range
$visits = PageVisitLog::visitsByDateRange('2024-01-01', '2024-01-31');

// Get visits from a specific date to today
$visits = PageVisitLog::visitsByDateRange('2024-01-01');

// Get visits up to a specific date
$visits = PageVisitLog::visitsByDateRange(null, '2024-01-31');

// Exclude bots
$visits = PageVisitLog::visitsByDateRange('2024-01-01', '2024-01-31', true);
```

### Device & Browser Statistics

**Statistics by Device Type:**
```php
$deviceStats = PageVisitLog::statisticsByDeviceType();
$deviceStats = PageVisitLog::statisticsByDeviceType(true); // Exclude bots

// Access results
foreach ($deviceStats as $stat) {
    echo $stat->device_type . ': ' . $stat->count . ' visits';
}
```

**Statistics by Browser:**
```php
$browserStats = PageVisitLog::statisticsByBrowser();
$browserStats = PageVisitLog::statisticsByBrowser(true); // Exclude bots

foreach ($browserStats as $stat) {
    echo $stat->browser . ': ' . $stat->count . ' visits';
}
```

**Statistics by Platform:**
```php
$platformStats = PageVisitLog::statisticsByPlatform();
$platformStats = PageVisitLog::statisticsByPlatform(true); // Exclude bots

foreach ($platformStats as $stat) {
    echo $stat->platform . ': ' . $stat->count . ' visits';
}
```

### Referrer Statistics

**Top Referrers:**
```php
// Get top 10 referrers
$referrers = PageVisitLog::statisticsByReferrer(10);
$referrers = PageVisitLog::statisticsByReferrer(10, true); // Exclude bots

foreach ($referrers as $referrer) {
    echo $referrer->referrer . ': ' . $referrer->count . ' visits';
}
```

### Time-based Statistics

**Daily Statistics:**
```php
// Get daily statistics for the last 30 days
$daily = PageVisitLog::dailyStatistics(30);
$daily = PageVisitLog::dailyStatistics(30, true); // Exclude bots

foreach ($daily as $day) {
    echo $day->date . ': ' . $day->count . ' visits';
}
```

### Geographic Statistics

**Statistics by Country:**
```php
// Requires detailed IP info to be enabled in config
$countryStats = PageVisitLog::statisticsByCountry();
$countryStats = PageVisitLog::statisticsByCountry(true); // Exclude bots

foreach ($countryStats as $stat) {
    echo $stat['country'] . ': ' . $stat['count'] . ' visits';
}
```

### Summary Statistics

**Get All Statistics at Once:**
```php
// Get summary statistics for the last 30 days
$summary = PageVisitLog::summaryStatistics(30);
$summary = PageVisitLog::summaryStatistics(30, true); // Exclude bots

// Returns an array with:
// - total_visits
// - unique_visitors
// - unique_ips
// - top_pages (top 5)
// - by_device
// - by_browser
// - by_platform

echo $summary['total_visits'];
echo $summary['unique_visitors'];
```

### Query Scopes

**Exclude Bots Scope:**
```php
// Use the scope to filter out bots
$visits = PageVisitLog::excludeBots()->get();
```

**Date Range Scope:**
```php
// Filter visits by date range
$visits = PageVisitLog::dateRange('2024-01-01', '2024-01-31')->get();

// Combine scopes
$visits = PageVisitLog::excludeBots()
    ->dateRange('2024-01-01', '2024-01-31')
    ->get();
```

---

## 📜 License

MIT License © [İbrahim Kaya](https://ibrahimkaya.dev)
