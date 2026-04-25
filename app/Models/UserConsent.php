<?php

namespace App\Models;

use App\Support\PolicyVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class UserConsent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'consent_type', 'policy_version', 'given',
        'context', 'ip_address', 'user_agent',
    ];

    protected $casts = ['given' => 'bool'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a consent event. Stores the policy version in force *at the moment
     * of giving*, so future policy edits do not rewrite history.
     */
    public static function record(
        ?int $userId,
        string $consentType,
        bool $given,
        string $context,
        ?Request $request = null,
    ): self {
        return self::create([
            'user_id' => $userId,
            'consent_type' => $consentType,
            'policy_version' => PolicyVersion::for($consentType),
            'given' => $given,
            'context' => $context,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) ($request?->userAgent() ?? ''), 0, 512) ?: null,
        ]);
    }
}
