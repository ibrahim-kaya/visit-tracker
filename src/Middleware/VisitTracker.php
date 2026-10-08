<?php

namespace IbrahimKaya\VisitTracker\Middleware;

use Closure;
use IbrahimKaya\VisitTracker\Jobs\ProcessVisitLog;
use IbrahimKaya\VisitTracker\Models\PageVisitLog;
use IbrahimKaya\VisitTracker\Support\BrowserDetector;
use IbrahimKaya\VisitTracker\Support\IpInfoLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Symfony\Component\HttpFoundation\Response;

class VisitTracker
{
    public const ATTR_PENDING = 'visit_tracker.pending';

    public const ATTR_CONTEXT = 'visit_tracker.context';

    public function handle(Request $request, Closure $next)
    {
        try {
            if ($this->shouldSkipRequest($request)) {
                return $next($request);
            }

            // Cheap bot gate before cookie / terminate work (same detector as browser-detect pipeline)
            if (! config('visit-tracker.log_bots', false) && $this->isCheapBot($request->userAgent())) {
                return $next($request);
            }

            if (! $this->passesSampleRate()) {
                return $next($request);
            }

            $visitorId = $this->resolveVisitorId($request);

            $payload = null;
            if (config('visit-tracker.log_payload', false) && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $payload = $request->except(config('visit-tracker.excluded_payload_fields', ['password', 'password_confirmation', 'token', '_token']));
                $payload = $this->filterSerializableData($payload);
                if (empty($payload)) {
                    $payload = null;
                }
            }

            $request->attributes->set(self::ATTR_PENDING, true);
            $request->attributes->set(self::ATTR_CONTEXT, [
                'user_id' => $this->resolveUserId($request),
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'visitor_id' => $visitorId,
                'referrer' => $request->headers->get('referer'),
                'page_url' => $request->fullUrl(),
                'path' => '/'.ltrim($request->path(), '/'),
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('VisitTracker middleware error: '.$e->getMessage(), [
                'exception' => $e,
                'path' => $request->path(),
            ]);
        }

        return $next($request);
    }

    /**
     * Run after the response is sent so UA parsing / DB writes do not delay TTFB.
     */
    public function terminate(Request $request, Response $response): void
    {
        if (! $request->attributes->get(self::ATTR_PENDING)) {
            return;
        }

        try {
            $context = $request->attributes->get(self::ATTR_CONTEXT, []);

            if ($this->isDuplicate($context)) {
                return;
            }

            $result = BrowserDetector::detect($context['user_agent'] ?? $request->userAgent());

            $logBots = config('visit-tracker.log_bots', false);
            if ($result->isBot() && ! $logBots) {
                return;
            }

            $ip = $this->getIp();
            $detailedIp = (bool) config('visit-tracker.detailed_ip_info', false);

            $visitData = [
                'user_id' => $context['user_id'] ?? null,
                'session_id' => $context['session_id'] ?? null,
                'visitor_id' => $context['visitor_id'] ?? null,
                'ip_address' => $ip,
                'referrer' => $context['referrer'] ?? null,
                'device_type' => $result->deviceType(),
                'browser' => $result->browserName(),
                'platform' => $result->platformName(),
                'ip_info' => null,
                'country_code' => null,
                'page_url' => $context['page_url'] ?? null,
                'path' => $this->normalizePath($context['path'] ?? null, $context['page_url'] ?? null),
                'user_agent' => $context['user_agent'] ?? null,
                'method' => $context['method'] ?? null,
                'payload' => $context['payload'] ?? null,
                'is_bot' => $result->isBot(),
            ];

            if (config('visit-tracker.use_queue', false)) {
                $pending = ProcessVisitLog::dispatch($visitData, $ip, $detailedIp);

                if ($connection = config('visit-tracker.queue_connection')) {
                    $pending->onConnection($connection);
                }

                if ($queue = config('visit-tracker.queue_name')) {
                    $pending->onQueue($queue);
                }
            } else {
                $this->processVisitLogSynchronously($visitData, $ip, $detailedIp);
            }
        } catch (\Throwable $e) {
            Log::error('VisitTracker terminate error: '.$e->getMessage(), [
                'exception' => $e,
                'path' => $request->path(),
            ]);
        }
    }

    protected function shouldSkipRequest(Request $request): bool
    {
        $path = $request->path();

        foreach (config('visit-tracker.excluded_paths', []) as $excluded) {
            if (Str::is($excluded, $path)) {
                return true;
            }
        }

        $excludedMethods = config('visit-tracker.excluded_methods', []);
        if (! empty($excludedMethods) && in_array($request->method(), $excludedMethods, true)) {
            return true;
        }

        if (config('visit-tracker.skip_ajax', true) && ($request->ajax() || $request->expectsJson())) {
            return true;
        }

        if (config('visit-tracker.skip_prefetch', true) && $this->isPrefetchRequest($request)) {
            return true;
        }

        return false;
    }

    protected function isPrefetchRequest(Request $request): bool
    {
        $purpose = strtolower((string) $request->headers->get('Sec-Purpose', ''));
        $purposeAlt = strtolower((string) $request->headers->get('Purpose', ''));

        return str_contains($purpose, 'prefetch')
            || str_contains($purpose, 'prerender')
            || str_contains($purposeAlt, 'prefetch')
            || str_contains($purposeAlt, 'prerender')
            || $request->headers->get('X-Moz') === 'prefetch';
    }

    protected function isCheapBot(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return false;
        }

        return (new CrawlerDetect())->isCrawler($userAgent);
    }

    protected function passesSampleRate(): bool
    {
        $rate = (float) config('visit-tracker.sample_rate', 1.0);

        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) <= $rate;
    }

    protected function isDuplicate(array $context): bool
    {
        $seconds = (int) config('visit-tracker.dedupe_seconds', 0);

        if ($seconds <= 0) {
            return false;
        }

        $visitorKey = $context['visitor_id']
            ?? $context['session_id']
            ?? ($context['user_agent'] ?? 'anon');
        $page = $context['page_url'] ?? '';
        $key = 'visit_tracker_dedupe_'.md5($visitorKey.'|'.$page);

        // Cache::add returns false when the key already exists within the TTL window.
        return ! Cache::add($key, 1, $seconds);
    }

    /**
     * Resolve user id without forcing a users-table hydrate when possible.
     */
    protected function resolveUserId(Request $request): int|string|null
    {
        $guard = Auth::guard();

        if ($guard->hasUser()) {
            return $guard->id();
        }

        if ($request->hasSession() && method_exists($guard, 'getName')) {
            $id = $request->session()->get($guard->getName());

            return $id !== null && $id !== '' ? $id : null;
        }

        return null;
    }

    /**
     * Resolve (or create) the persistent anonymous visitor id.
     * Cookie is queued only when missing so Set-Cookie is not sent every response.
     */
    protected function resolveVisitorId(Request $request): ?string
    {
        if (! config('visit-tracker.attribute_on_auth', true)) {
            return null;
        }

        $cookieName = config('visit-tracker.visitor_cookie', 'visit_tracker_vid');
        $visitorId = $request->cookie($cookieName);
        $isNew = ! is_string($visitorId) || $visitorId === '';

        if ($isNew) {
            $visitorId = (string) Str::uuid();
        }

        if ($isNew) {
            $minutes = (int) config('visit-tracker.visitor_cookie_minutes', 60 * 24 * 365 * 2);

            Cookie::queue(cookie(
                $cookieName,
                $visitorId,
                $minutes,
                '/',
                null,
                config('session.secure', null),
                true,
                false,
                'lax'
            ));
        }

        return $visitorId;
    }

    protected function normalizePath(?string $path, ?string $pageUrl): ?string
    {
        if (is_string($path) && $path !== '') {
            return Str::limit($path, 255, '');
        }

        if (! is_string($pageUrl) || $pageUrl === '') {
            return null;
        }

        $parsed = parse_url($pageUrl, PHP_URL_PATH);

        return is_string($parsed) ? Str::limit($parsed, 255, '') : null;
    }

    protected function getIp(): ?string
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            if (! empty($_SERVER[$key])) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }

        return null;
    }

    protected function processVisitLogSynchronously(array $visitData, ?string $ip, bool $detailedIp): void
    {
        $ipInfo = null;
        if ($detailedIp && $ip) {
            $ipInfo = IpInfoLookup::lookup($ip);
        }

        if (is_array($ipInfo)) {
            $visitData['country_code'] = $ipInfo['countryCode'] ?? null;
        }

        PageVisitLog::create(array_merge($visitData, [
            'ip_info' => $ipInfo,
        ]));
    }

    /**
     * @param  mixed  $data
     * @return mixed
     */
    protected function filterSerializableData($data)
    {
        if (is_array($data)) {
            $filtered = [];
            foreach ($data as $key => $value) {
                $filtered[$key] = $this->filterSerializableData($value);
            }

            return $filtered;
        }

        if (is_object($data)) {
            if ($data instanceof \Illuminate\Http\UploadedFile) {
                return [
                    'original_name' => $data->getClientOriginalName(),
                    'mime_type' => $data->getMimeType(),
                    'size' => $data->getSize(),
                    '_type' => 'uploaded_file',
                ];
            }

            try {
                serialize($data);

                return $data;
            } catch (\Throwable $e) {
                return [
                    '_type' => 'object',
                    '_class' => get_class($data),
                    '_message' => 'Non-serializable object filtered',
                ];
            }
        }

        return $data;
    }
}
