<?php

namespace App\Support;

use App\Models\CompanyMember;
use App\Models\User;

/**
 * Who is really at the keyboard. When a team member works in someone else's
 * business, the request runs as that business's owner (so every existing
 * ownership rule holds) and the member is kept here - for permissions, the
 * menu and the activity log. For the owner nothing is set and every check
 * passes.
 */
final class Team
{
    /**
     * Pages that are always about the person signed in, not the business.
     * Only the member's own profile pages: the activity log (profile.activity)
     * lists the whole business's entries, expenses and salaries included, so
     * it follows the role rules like any other business page. The app's
     * profile actions are named after their controller methods
     * (profile.me, profile.updatePassword...).
     */
    public const OWN = [
        'profile.edit', 'profile.update', 'profile.destroy', 'profile.privacy', 'profile.privacy.*',
        'profile.me', 'profile.logout', 'profile.updatePassword',
        'logout', 'verification.*', 'password.*', 'companies.switch', 'companies.index', 'team.join', 'team.accept', 'team.leave', 'guide.complete',
    ];

    /** Where a role starts: the dashboard, or for billing staff (no dashboard) the invoice list. */
    private const HOME_FALLBACKS = ['invoices.index', 'quotations.index', 'customers.index'];

    public static function isOwnRoute(?string $routeName): bool
    {
        return $routeName !== null && collect(self::OWN)->contains(fn ($p) => \Illuminate\Support\Str::is($p, $routeName));
    }

    public static function act(User $member, CompanyMember $membership): void
    {
        app()->instance('team.member', $member);
        app()->instance('team.membership', $membership);
    }

    public static function member(): ?User
    {
        return app()->bound('team.member') ? app('team.member') : null;
    }

    public static function membership(): ?CompanyMember
    {
        return app()->bound('team.membership') ? app('team.membership') : null;
    }

    public static function isMember(): bool
    {
        return self::member() !== null;
    }

    /** The person signed in: the member when acting, else the user. */
    public static function realUser(): ?User
    {
        return self::member() ?? auth()->user();
    }

    /**
     * The first page a member lands on in a business: the dashboard when
     * their role opens it, else the first billing page it does. Used after
     * accepting an invitation, switching business and a refused page, so
     * nobody arrives on a "your role does not include this page" error.
     */
    public static function homeRoute(?CompanyMember $membership = null): string
    {
        $membership ??= self::membership();
        if ($membership === null || CompanyMember::allows($membership->role, 'dashboard')) {
            return 'dashboard';
        }
        foreach (self::HOME_FALLBACKS as $route) {
            if (CompanyMember::allows($membership->role, $route)) {
                return $route;
            }
        }

        return 'invoices.index';
    }

    /** May the person at the keyboard open this route? */
    public static function can(string $routeName): bool
    {
        $m = self::membership();

        return $m === null || self::isOwnRoute($routeName) || CompanyMember::allows($m->role, $routeName);
    }
}
