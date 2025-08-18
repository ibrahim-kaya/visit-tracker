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
  - Authenticated user ID (if logged in)
- Exclude specific routes or paths.
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

    'log_bots' => false,

    'ip_info_cache_duration' => 86400, // seconds
    
    'use_queue' => true, // Use Laravel queues for processing
];
```

- **excluded\_paths** → Wildcards supported.
- **log\_bots** → Set `true` to log bot visits.
- **ip\_info\_cache\_duration** → Cache IP info to reduce API calls.
- **use\_queue** → Set `true` to use Laravel queues, `false` for synchronous processing.

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
}
```


**Optional manual middleware:**

```php
protected $middleware = [
    \IbrahimKaya\VisitTracker\Middleware\VisitTracker::class,
];
```


---

## 📜 License

MIT License © [İbrahim Kaya](https://ibrahimkaya.dev)
