<?php

namespace IbrahimKaya\VisitTracker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use IbrahimKaya\VisitTracker\Models\PageVisitLog;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class ProcessVisitLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 30;
    public $tries = 3;

    protected $visitData;
    protected $ip;
    protected $detailedIp;

    /**
     * Create a new job instance.
     */
    public function __construct(array $visitData, ?string $ip, bool $detailedIp = false)
    {
        $this->visitData = $visitData;
        $this->ip = $ip;
        $this->detailedIp = $detailedIp;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get the ip information if user wants detailed ip info
        $ip_info = $this->detailedIp ? $this->getIpInfo($this->ip) : null;

        // Create the visit log
        PageVisitLog::create(array_merge($this->visitData, [
            'ip_info' => $ip_info,
        ]));
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
                $client = new Client(['timeout' => 10]);
                $res = $client->get("http://ip-api.com/json/{$ip}?fields=status,message,continent,continentCode,country,countryCode,region,regionName,city,district,zip,lat,lon,timezone,offset,currency,isp,org,as,asname,reverse,proxy,hosting,query");
                $json = json_decode($res->getBody()->getContents(), true);
                return $json && $json['status'] === 'success' ? $json : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Log the error
        \Log::error('Visit log job failed: ' . $exception->getMessage(), [
            'visit_data' => $this->visitData,
            'ip' => $this->ip,
            'exception' => $exception
        ]);
    }
}
