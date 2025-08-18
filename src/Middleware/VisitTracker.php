<?php

namespace IbrahimKaya\VisitTracker\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use IbrahimKaya\VisitTracker\Models\PageVisitLog;
use IbrahimKaya\VisitTracker\Jobs\ProcessVisitLog;
use hisorange\BrowserDetect\Parser as Browser;

class VisitTracker
{
    public function handle(Request $request, Closure $next)
    {
        $path = $request->path();

        // Excluded paths check
        foreach (config('visit-tracker.excluded_paths', []) as $excluded) {
            if (Str::is($excluded, $path)) {
                return $next($request);
            }
        }

        // Bot check
		$logBots = config('visit-tracker.log_bots', false);

        if (Browser::isBot() && !$logBots) {
            return $next($request);
        }

        $ip = $this->getIp();

		$detailedIp = config('visit-tracker.detailed_ip_info', false);

        // Create visit data
        $visitData = [
            'user_id'    => auth()->check() ? auth()->id() : null,
            'session_id' => session()->getId(),
            'ip_address' => $ip,
            'referrer'   => $request->headers->get('referer'),
            'device_type'=> Browser::deviceType(),
            'browser'    => Browser::browserName(),
            'platform'   => Browser::platformName(),
            'ip_info'    => null, // Will be filled by the job
            'page_url'   => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'is_bot'     => Browser::isBot(),
        ];

        // Check if queue system should be used
        if (config('visit-tracker.use_queue', true)) {
            // Dispatch job to queue for processing
            \IbrahimKaya\VisitTracker\Jobs\ProcessVisitLog::dispatch($visitData, $ip, $detailedIp);
        } else {
            // Process synchronously (for development/testing)
            $this->processVisitLogSynchronously($visitData, $ip, $detailedIp);
        }

        return $next($request);
    }

    /**
     * Get the user's real IP address
     */
    protected function getIp(): ?string
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
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

    /**
     * Process visit log synchronously (when queue is disabled)
     */
    protected function processVisitLogSynchronously(array $visitData, ?string $ip, bool $detailedIp): void
    {
        // Get IP info if needed
        $ip_info = null;
        if ($detailedIp && $ip) {
            $ip_info = $this->getIpInfoSynchronously($ip);
        }

        // Create visit log directly
        \IbrahimKaya\VisitTracker\Models\PageVisitLog::create(array_merge($visitData, [
            'ip_info' => $ip_info,
        ]));
    }

    /**
     * Fetch IP information synchronously (cache supported)
     */
    protected function getIpInfoSynchronously(?string $ip): ?array
    {
        if (!$ip) return null;

        $cacheDuration = config('visit-tracker.ip_info_cache_duration', 24 * 60 * 60);

        return \Illuminate\Support\Facades\Cache::remember("visit_tracker_ip_{$ip}", $cacheDuration, function() use ($ip) {
            try {
                $client = new \GuzzleHttp\Client(['timeout' => 2]);
                $res = $client->get("http://ip-api.com/json/{$ip}?fields=status,message,continent,continentCode,country,countryCode,region,regionName,city,district,zip,lat,lon,timezone,offset,currency,isp,org,as,asname,reverse,proxy,hosting,query");
                $json = json_decode($res->getBody()->getContents(), true);
                return $json && $json['status'] === 'success' ? $json : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }
}
