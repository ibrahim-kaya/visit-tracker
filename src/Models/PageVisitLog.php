<?php

namespace IbrahimKaya\VisitTracker\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PageVisitLog extends Model
{
    protected $table = 'page_visit_logs';

     protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'referrer',
        'device_type',
        'browser',
        'platform',
        'ip_info',
        'page_url',
        'user_agent',
        'is_bot'
    ];

    protected $casts = [
        'ip_info' => 'array',
        'is_bot' => 'boolean',
    ];

    /**
     * Returns the total number of visits
     *
     * @param bool $excludeBots Exclude bot visits
     * @return int
     */
    public static function totalVisits(bool $excludeBots = false): int
    {
        $query = static::query();
        
        if ($excludeBots) {
            $query->where('is_bot', false);
        }
        
        return $query->count();
    }

    /**
     * Returns the number of unique visitors (based on user_id if available, otherwise session_id)
     *
     * @param bool $excludeBots Exclude bot visits
     * @return int
     */
    public static function uniqueVisitors(bool $excludeBots = false): int
    {
        $query = static::query()
            ->where(function ($q) {
                $q->whereNotNull('user_id')
                  ->orWhereNotNull('session_id');
            });
        
        if ($excludeBots) {
            $query->where('is_bot', false);
        }
        
        // Count distinct visitors: use user_id if available, otherwise use session_id
        // This ensures logged-in users are counted correctly even if their session_id changes
        return $query->selectRaw('COUNT(DISTINCT COALESCE(user_id, session_id)) as unique_count')
            ->value('unique_count') ?? 0;
    }

    /**
     * Returns the number of unique IP addresses
     *
     * @param bool $excludeBots Exclude bot visits
     * @return int
     */
    public static function uniqueIpAddresses(bool $excludeBots = false): int
    {
        $query = static::query()->whereNotNull('ip_address');
        
        if ($excludeBots) {
            $query->where('is_bot', false);
        }
        
        return $query->distinct('ip_address')->count('ip_address');
    }

    /**
     * Returns the most visited pages
     *
     * @param int $limit Number of results to return
     * @param bool $excludeBots Exclude bot visits
     * @return \Illuminate\Support\Collection
     */
    public static function mostVisitedPages(int $limit = 10, bool $excludeBots = false)
    {
        $query = static::query()->whereNotNull('page_url');
        
        if ($excludeBots) {
            $query->where('is_bot', false);
        }
        
        return $query->select('page_url', DB::raw('count(*) as visit_count'))
            ->groupBy('page_url')
            ->orderByDesc('visit_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Returns visits within a specific date range
     *
     * @param string|null $startDate Start date (Y-m-d format)
     * @param string|null $endDate End date (Y-m-d format)
     * @param bool $excludeBots Exclude bot visits
     * @return int
     */
    public static function visitsByDateRange(?string $startDate = null, ?string $endDate = null, bool $excludeBots = false): int
    {
        $query = static::query();
        
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        
        if ($excludeBots) {
            $query->where('is_bot', false);
        }
        
        return $query->count();
    }

    /**
     * Returns statistics by device type
     *
     * @param bool $excludeBots Exclude bot visits
     * @return \Illuminate\Support\Collection
     */
    public static function statisticsByDeviceType(bool $excludeBots = false)
    {
        $query = static::query()->whereNotNull('device_type');
        
        if ($excludeBots) {
            $query->where('is_bot', false);
        }
        
        return $query->select('device_type', DB::raw('count(*) as count'))
            ->groupBy('device_type')
            ->orderByDesc('count')
            ->get();
    }

    /**
     * Returns statistics by browser
     *
     * @param bool $excludeBots Exclude bot visits
     * @return \Illuminate\Support\Collection
     */
    public static function statisticsByBrowser(bool $excludeBots = false)
    {
        $query = static::query()->whereNotNull('browser');
        
        if ($excludeBots) {
            $query->where('is_bot', false);
        }
        
        return $query->select('browser', DB::raw('count(*) as count'))
            ->groupBy('browser')
            ->orderByDesc('count')
            ->get();
    }

    /**
     * Returns statistics by platform
     *
     * @param bool $excludeBots Exclude bot visits
     * @return \Illuminate\Support\Collection
     */
    public static function statisticsByPlatform(bool $excludeBots = false)
    {
        $query = static::query()->whereNotNull('platform');
        
        if ($excludeBots) {
            $query->where('is_bot', false);
        }
        
        return $query->select('platform', DB::raw('count(*) as count'))
            ->groupBy('platform')
            ->orderByDesc('count')
            ->get();
    }

    /**
     * Returns referrer statistics
     *
     * @param int $limit Number of results to return
     * @param bool $excludeBots Exclude bot visits
     * @return \Illuminate\Support\Collection
     */
    public static function statisticsByReferrer(int $limit = 10, bool $excludeBots = false)
    {
        $query = static::query()->whereNotNull('referrer');
        
        if ($excludeBots) {
            $query->where('is_bot', false);
        }
        
        return $query->select('referrer', DB::raw('count(*) as count'))
            ->groupBy('referrer')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /**
     * Returns daily visit statistics
     *
     * @param int $days Number of days of data to return
     * @param bool $excludeBots Exclude bot visits
     * @return \Illuminate\Support\Collection
     */
    public static function dailyStatistics(int $days = 30, bool $excludeBots = false)
    {
        $query = static::query()
            ->whereDate('created_at', '>=', Carbon::now()->subDays($days));
        
        if ($excludeBots) {
            $query->where('is_bot', false);
        }
        
        return $query->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Returns statistics by country (from ip_info)
     *
     * @param bool $excludeBots Exclude bot visits
     * @return \Illuminate\Support\Collection
     */
    public static function statisticsByCountry(bool $excludeBots = false)
    {
        $query = static::query()
            ->whereNotNull('ip_info');
        
        if ($excludeBots) {
            $query->where('is_bot', false);
        }
        
        // Extract country information from JSON field and group
        return $query->get()
            ->filter(function ($item) {
                return isset($item->ip_info['country']) && !empty($item->ip_info['country']);
            })
            ->groupBy(function ($item) {
                return $item->ip_info['country'] ?? 'Bilinmeyen';
            })
            ->map(function ($group) {
                return [
                    'country' => $group->first()->ip_info['country'] ?? 'Bilinmeyen',
                    'count' => $group->count()
                ];
            })
            ->sortByDesc('count')
            ->values();
    }

    /**
     * Returns summary statistics for the last N days
     *
     * @param int $days Number of days of data
     * @param bool $excludeBots Exclude bot visits
     * @return array
     */
    public static function summaryStatistics(int $days = 30, bool $excludeBots = false): array
    {
        $baseQuery = static::query()
            ->whereDate('created_at', '>=', Carbon::now()->subDays($days));
        
        if ($excludeBots) {
            $baseQuery->where('is_bot', false);
        }
        
        $uniqueVisitorsQuery = (clone $baseQuery)
            ->where(function ($q) {
                $q->whereNotNull('user_id')
                  ->orWhereNotNull('session_id');
            });
        
        return [
            'total_visits' => (clone $baseQuery)->count(),
            'unique_visitors' => $uniqueVisitorsQuery->selectRaw('COUNT(DISTINCT COALESCE(user_id, session_id)) as unique_count')
                ->value('unique_count') ?? 0,
            'unique_ips' => (clone $baseQuery)->whereNotNull('ip_address')->distinct('ip_address')->count('ip_address'),
            'top_pages' => static::mostVisitedPages(5, $excludeBots),
            'by_device' => static::statisticsByDeviceType($excludeBots),
            'by_browser' => static::statisticsByBrowser($excludeBots),
            'by_platform' => static::statisticsByPlatform($excludeBots),
        ];
    }

    /**
     * Scope to filter out bot visits
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeBots($query)
    {
        return $query->where('is_bot', false);
    }

    /**
     * Scope to filter visits within a specific date range
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $startDate
     * @param string|null $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, ?string $startDate = null, ?string $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        
        return $query;
    }
}
