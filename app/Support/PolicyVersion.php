<?php

namespace App\Support;

/**
 * Single source of truth for legal-document versions.
 *
 * Bump the relevant constant whenever a policy's substance changes. Every
 * consent we record stores the version in force at the moment it was given,
 * so the audit trail survives future edits.
 */
class PolicyVersion
{
    public const TERMS = '2026-04-24';

    public const PRIVACY = '2026-04-24';

    public const COOKIES = '2026-04-24';

    /** Map a consent_type value to the version currently in force. */
    public static function for(string $consentType): string
    {
        return match ($consentType) {
            'terms' => self::TERMS,
            'privacy' => self::PRIVACY,
            'marketing' => self::PRIVACY,
            'cookies_analytics', 'cookies_marketing' => self::COOKIES,
            default => 'unversioned',
        };
    }
}
