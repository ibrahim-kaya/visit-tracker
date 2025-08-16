<<<<<<< HEAD
# Laravel Visit Tracker


A **Laravel package** to automatically track page visits including IP, browser, device, referrer, and more. Perfect for analytics and monitoring.

---

## 🌟 Features

- Automatic tracking of all web requests.
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
];
```

- **excluded\_paths** → Wildcards supported.
- **log\_bots** → Set `true` to log bot visits.
- **ip\_info\_cache\_duration** → Cache IP info to reduce API calls.

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

MIT License © [İbrahim Kaya](https://github.com/ibrahim-kaya)

=======
# visit-tracker
A Laravel package to automatically track page visits including IP, browser, device, referrer, and more. Perfect for analytics and monitoring.
>>>>>>> origin/main
