<?php

namespace IbrahimKaya\VisitTracker\Jobs;

use IbrahimKaya\VisitTracker\Models\PageVisitLog;
use IbrahimKaya\VisitTracker\Support\IpInfoLookup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $ipInfo = $this->detailedIp ? IpInfoLookup::lookup($this->ip) : null;

        if (is_array($ipInfo) && empty($this->visitData['country_code'])) {
            $this->visitData['country_code'] = $ipInfo['countryCode'] ?? null;
        }

        PageVisitLog::create(array_merge($this->visitData, [
            'ip_info' => $ipInfo,
        ]));
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('Visit log job failed: '.$exception->getMessage(), [
            'visit_data' => $this->visitData,
            'ip' => $this->ip,
            'exception' => $exception,
        ]);
    }
}
