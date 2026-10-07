<?php

namespace IbrahimKaya\VisitTracker\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PageVisitLog extends Model
{
    use MassPrunable;

    protected $table = 'page_visit_logs';

    protected $fillable = [
        'user_id',
        'session_id',
        'visitor_id',
        'ip_address',
        'referrer',
        'device_type',
        'browser',
        'platform',
        'ip_info',
        'country_code',
        'page_url',
        'path',
        'user_agent',
        'method',
        'payload',
        'is_bot',
    ];

    protected $casts = [
        'ip_info' => 'array',
        'payload' => 'array',
        'is_bot' => 'boolean',
    ];

    /**
     * Get the prunable model query.
     */
    public function prunable(): Builder
    {
        $days = config('visit-tracker.retention_days');

        if ($days === null || (int) $days <= 0) {
            // Nothing to prune when retention is disabled.
            return static::query()->whereRaw('0 = 1');
        }

        return static::query()->where('created_at', '<', Carbon::now()->subDays((int) $days));
    }

    /**
     * Assign anonymous visits to a user (by visitor cookie and/or session id).
     *
     * @param  int|string  $userId
     * @return int Number of updated rows
     */
    public static function attributeToUser($userId, ?string $visitorId = null, ?string $sessionId = null): int
    {
        if ($visitorId === null && $sessionId === null) {
            return 0;
        }

        return static::query()
            ->whereNull('user_id')
            ->where(function ($query) use ($visitorId, $sessionId) {
                if ($visitorId) {
                    $query->orWhere('visitor_id', $visitorId);
                }

                if ($sessionId) {
                    $query->orWhere('session_id', $sessionId);
                }
            })
            ->update(['user_id' => $userId]);
    }

    /**
     * Cache helper for statistics methods.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected static function rememberStat(string $key, callable $callback)
    {
        $ttl = (int) config('visit-tracker.statistics_cache_ttl', 60);

        if ($ttl <= 0) {
            return $callback();
        }

        return Cache::remember('visit_tracker_stat_'.$key, $ttl, $callback);
    }

    /**
     * Apply a sargable created_at range (avoids WHERE DATE(created_at)).
     */
    protected static function applyCreatedAtRange(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        if ($startDate) {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('created_at', '<', Carbon::parse($endDate)->endOfDay()->addSecond());
        }

        return $query;
    }

    /**
     * Returns the total number of visits
     */
    public static function totalVisits(bool $excludeBots = false): int
    {
        return (int) static::rememberStat('total_'.($excludeBots ? '1' : '0'), function () use ($excludeBots) {
            $query = static::query();

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            return $query->count();
        });
    }

    /**
     * Returns the number of unique visitors (based on user_id if available, otherwise session_id)
     */
    public static function uniqueVisitors(bool $excludeBots = false): int
    {
        return (int) static::rememberStat('unique_'.($excludeBots ? '1' : '0'), function () use ($excludeBots) {
            $query = static::query()
                ->where(function ($q) {
                    $q->whereNotNull('user_id')
                        ->orWhereNotNull('session_id');
                });

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            return $query->selectRaw('COUNT(DISTINCT COALESCE(user_id, session_id)) as unique_count')
                ->value('unique_count') ?? 0;
        });
    }

    /**
     * Returns the number of unique IP addresses
     */
    public static function uniqueIpAddresses(bool $excludeBots = false): int
    {
        return (int) static::rememberStat('unique_ip_'.($excludeBots ? '1' : '0'), function () use ($excludeBots) {
            $query = static::query()->whereNotNull('ip_address');

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            return $query->distinct('ip_address')->count('ip_address');
        });
    }

    /**
     * Returns the most visited pages (prefers normalized path when present)
     */
    public static function mostVisitedPages(int $limit = 10, bool $excludeBots = false)
    {
        return static::rememberStat("top_pages_{$limit}_".($excludeBots ? '1' : '0'), function () use ($limit, $excludeBots) {
            $query = static::query()->where(function ($q) {
                $q->whereNotNull('path')->orWhereNotNull('page_url');
            });

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            return $query->select(
                DB::raw('COALESCE(path, page_url) as page_url'),
                DB::raw('count(*) as visit_count')
            )
                ->groupBy(DB::raw('COALESCE(path, page_url)'))
                ->orderByDesc('visit_count')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Returns visits within a specific date range
     */
    public static function visitsByDateRange(?string $startDate = null, ?string $endDate = null, bool $excludeBots = false): int
    {
        $key = 'range_'.md5(($startDate ?? '').'|'.($endDate ?? '').'|'.($excludeBots ? '1' : '0'));

        return (int) static::rememberStat($key, function () use ($startDate, $endDate, $excludeBots) {
            $query = static::query();
            static::applyCreatedAtRange($query, $startDate, $endDate);

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            return $query->count();
        });
    }

    /**
     * Returns statistics by device type
     */
    public static function statisticsByDeviceType(bool $excludeBots = false)
    {
        return static::rememberStat('device_'.($excludeBots ? '1' : '0'), function () use ($excludeBots) {
            $query = static::query()->whereNotNull('device_type');

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            return $query->select('device_type', DB::raw('count(*) as count'))
                ->groupBy('device_type')
                ->orderByDesc('count')
                ->get();
        });
    }

    /**
     * Returns statistics by browser
     */
    public static function statisticsByBrowser(bool $excludeBots = false)
    {
        return static::rememberStat('browser_'.($excludeBots ? '1' : '0'), function () use ($excludeBots) {
            $query = static::query()->whereNotNull('browser');

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            return $query->select('browser', DB::raw('count(*) as count'))
                ->groupBy('browser')
                ->orderByDesc('count')
                ->get();
        });
    }

    /**
     * Returns statistics by platform
     */
    public static function statisticsByPlatform(bool $excludeBots = false)
    {
        return static::rememberStat('platform_'.($excludeBots ? '1' : '0'), function () use ($excludeBots) {
            $query = static::query()->whereNotNull('platform');

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            return $query->select('platform', DB::raw('count(*) as count'))
                ->groupBy('platform')
                ->orderByDesc('count')
                ->get();
        });
    }

    /**
     * Returns referrer statistics
     */
    public static function statisticsByReferrer(int $limit = 10, bool $excludeBots = false)
    {
        return static::rememberStat("referrer_{$limit}_".($excludeBots ? '1' : '0'), function () use ($limit, $excludeBots) {
            $query = static::query()->whereNotNull('referrer');

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            return $query->select('referrer', DB::raw('count(*) as count'))
                ->groupBy('referrer')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Returns daily visit statistics
     */
    public static function dailyStatistics(int $days = 30, bool $excludeBots = false)
    {
        return static::rememberStat("daily_{$days}_".($excludeBots ? '1' : '0'), function () use ($days, $excludeBots) {
            $query = static::query()
                ->where('created_at', '>=', Carbon::now()->subDays($days)->startOfDay());

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            return $query->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        });
    }

    /**
     * Returns statistics by country (uses country_code column when available)
     */
    public static function statisticsByCountry(bool $excludeBots = false)
    {
        return static::rememberStat('country_'.($excludeBots ? '1' : '0'), function () use ($excludeBots) {
            $query = static::query()->whereNotNull('country_code');

            if ($excludeBots) {
                $query->where('is_bot', false);
            }

            $fromColumn = $query->select('country_code as country', DB::raw('count(*) as count'))
                ->groupBy('country_code')
                ->orderByDesc('count')
                ->get();

            if ($fromColumn->isNotEmpty()) {
                return $fromColumn->map(fn ($row) => [
                    'country' => $row->country,
                    'count' => (int) $row->count,
                ])->values();
            }

            // Fallback for rows written before country_code existed
            $fallback = static::query()->whereNotNull('ip_info');

            if ($excludeBots) {
                $fallback->where('is_bot', false);
            }

            return $fallback->get()
                ->filter(function ($item) {
                    return isset($item->ip_info['country']) && ! empty($item->ip_info['country']);
                })
                ->groupBy(function ($item) {
                    return $item->ip_info['country'] ?? 'Bilinmeyen';
                })
                ->map(function ($group) {
                    return [
                        'country' => $group->first()->ip_info['country'] ?? 'Bilinmeyen',
                        'count' => $group->count(),
                    ];
                })
                ->sortByDesc('count')
                ->values();
        });
    }

    /**
     * Returns summary statistics for the last N days
     */
    public static function summaryStatistics(int $days = 30, bool $excludeBots = false): array
    {
        return static::rememberStat("summary_{$days}_".($excludeBots ? '1' : '0'), function () use ($days, $excludeBots) {
            $baseQuery = static::query()
                ->where('created_at', '>=', Carbon::now()->subDays($days)->startOfDay());

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
        });
    }

    /**
     * Scope to filter out bot visits
     */
    public function scopeExcludeBots($query)
    {
        return $query->where('is_bot', false);
    }

    /**
     * Scope to filter visits within a specific date range (sargable)
     */
    public function scopeDateRange($query, ?string $startDate = null, ?string $endDate = null)
    {
        return static::applyCreatedAtRange($query, $startDate, $endDate);
    }
}
