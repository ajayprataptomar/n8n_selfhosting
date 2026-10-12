<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * v4.8 — Raw open/click event log for bot-filtered insights.
 * See migration 2026_07_31_000001_create_email_events_table.php for rationale.
 */
class EmailEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_dispatch_id',
        'dispatch_log_id',
        'contact_id',
        'user_id',
        'event_type',
        'user_agent',
        'ip_address',
        'is_bot',
        'bot_name',
        'url',
    ];

    protected $casts = [
        'is_bot' => 'boolean',
    ];
}
