<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'company_id',
        'subject_type', 'subject_id',
        'action', 'summary', 'changes', 'ip_address',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Best-effort logger. Never throws — audit logging must NOT break business flows.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $subject
     */
    public static function record(string $action, ?string $summary = null, $subject = null, ?array $changes = null): void
    {
        try {
            $user = auth()->user();

            self::create([
                'user_id' => $user?->id,
                'company_id' => self::companyIdFor($subject, $user),
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'action' => $action,
                // A team member's action is the owner's data, but says who did it.
                'summary' => ($member = \App\Support\Team::member())
                    ? trim(($summary ?? '') . ' · by ' . $member->name . ' (' . \App\Support\Team::membership()?->roleLabel() . ')')
                    : $summary,
                'changes' => $changes,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Swallow — audit logging is best-effort. Better to have a missing log
            // entry than to break an invoice creation because of a logging hiccup.
            \Log::warning('AuditLog::record failed: ' . $e->getMessage());
        }
    }

    /**
     * The business an entry belongs to: the subject's own business when it
     * has one (an owner can act on business A's invoice while business B is
     * the active one), else the active business.
     */
    private static function companyIdFor($subject, $user): ?int
    {
        if ($subject instanceof Company && $subject->getKey()) {
            return (int) $subject->getKey();
        }
        if ($subject instanceof Model && array_key_exists('company_id', $subject->getAttributes()) && $subject->getAttribute('company_id')) {
            return (int) $subject->getAttribute('company_id');
        }

        return $user?->ensureCompany()?->id;
    }
}
