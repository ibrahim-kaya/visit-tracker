<?php

namespace IbrahimKaya\VisitTracker\Middleware;

use Closure;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use IbrahimKaya\VisitTracker\Models\PageVisitLog;
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
        $ip_info = $this->getIpInfo($ip);

        // Create visit data
        PageVisitLog::create([
            'user_id'    => auth()->check() ? auth()->id() : null,
            'session_id' => session()->getId(),
            'ip_address' => $ip,
            'referrer'   => $request->headers->get('referer'),
            'device_type'=> Browser::deviceType(),
            'browser'    => Browser::browserName(),
            'platform'   => Browser::platformName(),
            'ip_info'    => $ip_info,
            'page_url'   => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'is_bot'     => Browser::isBot(),
        ]);

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
     * Fetch IP information (cache supported)
     */
    protected function getIpInfo(?string $ip): ?array
    {
        if (!$ip) return null;

        $cacheDuration = config('visit-tracker.ip_info_cache_duration', 24 * 60 * 60);

        return Cache::remember("visit_tracker_ip_{$ip}", $cacheDuration, function() use ($ip) {
            try {
                $client = new Client(['timeout' => 2]);
                $res = $client->get("http://ip-api.com/json/{$ip}?fields=status,message,continent,continentCode,country,countryCode,region,regionName,city,district,zip,lat,lon,timezone,offset,currency,isp,org,as,asname,reverse,proxy,hosting,query");
                $json = json_decode($res->getBody()->getContents(), true);
                return $json && $json['status'] === 'success' ? $json : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }
}
