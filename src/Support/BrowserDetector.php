<?php

namespace IbrahimKaya\VisitTracker\Support;

use hisorange\BrowserDetect\Contracts\ResultInterface;
use Illuminate\Support\Facades\Cache;

class BrowserDetector
{
    /**
     * Detect the current request UA via the container-bound (cached) parser.
     *
     * Laravel cache can occasionally return a broken serialized Result as
     * __PHP_Incomplete_Class; forget that key and parse once more.
     */
    public static function detect(?string $userAgent = null): ResultInterface
    {
        try {
            $result = app('browser-detect')->detect();

            if ($result instanceof ResultInterface) {
                return $result;
            }
        } catch (\TypeError $e) {
            // Fall through to cache bust + retry.
        }

        $agent = substr(
            (string) ($userAgent ?? request()->userAgent() ?? ''),
            0,
            (int) config('browser-detect.security.max-header-length', 2048)
        );

        $key = config('browser-detect.cache.prefix', 'bd4_').md5($agent);
        Cache::forget($key);

        $result = app('browser-detect')->parse($agent);

        if (! $result instanceof ResultInterface) {
            throw new \RuntimeException('browser-detect returned an invalid result after cache recovery.');
        }

        return $result;
    }
}
