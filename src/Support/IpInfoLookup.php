<?php

namespace IbrahimKaya\VisitTracker\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Cache;

class IpInfoLookup
{
    public const SENTINEL_MISS = '__visit_tracker_ip_miss__';

    /**
     * Fetch IP geolocation with positive and negative caching.
     */
    public static function lookup(?string $ip): ?array
    {
        if (! $ip) {
            return null;
        }

        $cacheKey = "visit_tracker_ip_{$ip}";
        $positiveTtl = (int) config('visit-tracker.ip_info_cache_duration', 24 * 60 * 60);
        $negativeTtl = (int) config('visit-tracker.ip_info_negative_cache_duration', 15 * 60);

        $cached = Cache::get($cacheKey);

        if ($cached === self::SENTINEL_MISS) {
            return null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $client = new Client(['timeout' => 3, 'http_errors' => true]);
            $res = $client->get(
                "http://ip-api.com/json/{$ip}?fields=status,message,continent,continentCode,country,countryCode,region,regionName,city,district,zip,lat,lon,timezone,offset,currency,isp,org,as,asname,reverse,proxy,hosting,query"
            );
            $json = json_decode($res->getBody()->getContents(), true);

            if ($json && ($json['status'] ?? null) === 'success') {
                Cache::put($cacheKey, $json, $positiveTtl);

                return $json;
            }

            Cache::put($cacheKey, self::SENTINEL_MISS, $negativeTtl);

            return null;
        } catch (ClientException $e) {
            $status = $e->getResponse()?->getStatusCode();
            $ttl = $status === 429 ? max($negativeTtl, 60 * 60) : $negativeTtl;
            Cache::put($cacheKey, self::SENTINEL_MISS, $ttl);

            return null;
        } catch (\Throwable $e) {
            Cache::put($cacheKey, self::SENTINEL_MISS, $negativeTtl);

            return null;
        }
    }
}
