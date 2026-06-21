<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataBreach extends Model
{
    protected $fillable = [
        'title', 'description', 'status', 'severity',
        'occurred_at', 'discovered_at', 'affected_users',
        'data_categories', 'remediation',
        'board_notified_at', 'users_notified_at', 'logged_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'discovered_at' => 'datetime',
        'board_notified_at' => 'datetime',
        'users_notified_at' => 'datetime',
        'affected_users' => 'integer',
    ];

    public const STATUSES = ['open', 'contained', 'reported', 'closed'];

    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public function logger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    /**
     * Whether we are still inside the 72-hour window in which DPDP expects the
     * Board and affected principals to be notified. Drives the urgency badge.
     */
    public function notificationOverdue(): bool
    {
        if ($this->board_notified_at && $this->users_notified_at) {
            return false;
        }

        $deadline = $this->discovered_at->copy()->addHours(config('legal.breach_report_hours', 72));

        return now()->greaterThan($deadline);
    }
}
