<?php

namespace IbrahimKaya\VisitTracker\Models;

use Illuminate\Database\Eloquent\Model;

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
}
