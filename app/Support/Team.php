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
    /** Pages that are always about the person signed in, not the business. */
    public const OWN = ['profile.*', 'logout', 'verification.*', 'password.*', 'companies.switch', 'companies.index', 'team.join', 'team.accept', 'team.leave', 'guide.complete'];

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

    /** May the person at the keyboard open this route? */
    public static function can(string $routeName): bool
    {
        $m = self::membership();

        return $m === null || self::isOwnRoute($routeName) || CompanyMember::allows($m->role, $routeName);
    }
}
