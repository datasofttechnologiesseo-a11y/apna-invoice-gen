<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\User;
use App\Support\Team;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A team member working in someone else's business.
 *
 * Only on signed-in app pages, and only when the member's chosen business is
 * not their own: the member's role is checked against the page, then the
 * request runs as the business owner - so the data, numbering and every
 * ownership rule are exactly the owner's - while the member is remembered
 * (App\Support\Team) for the menu and the activity log.
 *
 * The member's own pages (profile, password, switching business, the invite
 * link) always run as the member. For an owner this does nothing at all.
 */
class ActAsTeamMember
{
    /**
     * Mobile-app (API) actions mostly have no route name: they are named here
     * after the web page they correspond to, so one set of role rules covers
     * both. An action not listed gets no name and is refused for members.
     */
    private const API_AREA = [
        'InvoiceController' => 'invoices', 'QuotationController' => 'quotations', 'CustomerController' => 'customers',
        'ProductController' => 'products', 'ExpenseController' => 'finance.expenses', 'CashMemoController' => 'finance.cash-memos',
        'CreditNoteController' => 'credit-notes', 'FinanceController' => 'finance', 'DashboardController' => 'dashboard',
        'CompanyController' => 'companies', 'BackupController' => 'backup', 'ReferralController' => 'referrals',
        'AuthController' => 'profile', 'ProfileController' => 'profile',
    ];

    private const API_ALIAS = [
        'invoices.recordPayment' => 'invoices.payments',
        'invoices.deletePayment' => 'payments.destroy',
        'dashboard.index' => 'dashboard',
        'companies.active' => 'companies.index',
        'credit-notes.pdf' => 'credit-notes.pdf',
    ];

    private function routeName(\Illuminate\Routing\Route $route): ?string
    {
        if ($route->getName()) {
            // "api.customers.index" follows the same rule as "customers.index".
            return str_starts_with($route->getName(), 'api.') ? substr($route->getName(), 4) : $route->getName();
        }
        if (! str_contains((string) $route->getActionName(), '\\Api\\')) {
            return null;
        }
        [$class, $method] = array_pad(explode('@', (string) $route->getActionName(), 2), 2, null);
        $area = self::API_AREA[class_basename((string) $class)] ?? null;
        if (! $area || ! $method) {
            return null;
        }
        $name = $area . '.' . $method;

        return self::API_ALIAS[$name] ?? $name;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Nothing from an earlier request may linger (long-running workers, tests).
        app()->forgetInstance('team.member');
        app()->forgetInstance('team.membership');

        $user = $request->user();
        $route = $request->route();
        $signedIn = collect($route?->gatherMiddleware() ?? [])->contains(fn ($m) => $m === 'auth' || str_starts_with((string) $m, 'auth:'));

        // A DPDP-erased account is never usable again, on the web or in the
        // app, even with a session or token from before the erasure.
        if ($user && $route && $signedIn && $user->erased_at !== null) {
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
            }
            throw new \Illuminate\Auth\AuthenticationException('Unauthenticated.');
        }

        if (! $user || ! $route || ! $signedIn || ! $user->active_company_id) {
            return $next($request);
        }
        $company = Company::find($user->active_company_id);
        if (! $company || (int) $company->user_id === (int) $user->id) {
            return $next($request);
        }

        $membership = CompanyMember::where('company_id', $company->id)->where('user_id', $user->id)->whereNotNull('accepted_at')->first();
        if (! $membership) {
            // Access was removed: back to their own business, as themselves.
            $user->forceFill(['active_company_id' => $user->companies()->orderBy('id')->value('id')])->save();

            return $next($request);
        }

        $name = $this->routeName($route);
        if (Team::isOwnRoute($name)) {
            // Their own page, as themselves - but the menu still knows the role.
            Team::act($user, $membership);

            return $next($request);
        }

        if (! CompanyMember::allows($membership->role, $name)) {
            $message = 'Your role in ' . $company->name . ' (' . $membership->roleLabel() . ') does not include this page. Ask the owner if you need it.';
            $fallback = Team::homeRoute($membership);
            if ($request->expectsJson() || $request->is('api/*') || $name === $fallback || ($request->isMethod('post') && ! $request->header('referer'))) {
                abort(403, $message);
            }

            return redirect()->route($fallback)->with('error', $message);
        }

        // The owner's account sees all of the owner's businesses; the member
        // was invited into one. A record named in the URL (an invoice, a
        // customer, a payment...) must belong to that one.
        foreach ($route->parameters() as $param) {
            if ($param instanceof Company) {
                abort_unless((int) $param->id === (int) $company->id, 403, 'That belongs to another business.');
            } elseif ($param instanceof \Illuminate\Database\Eloquent\Model && array_key_exists('company_id', $param->getAttributes())) {
                abort_unless((int) $param->company_id === (int) $company->id, 403, 'That belongs to another business.');
            }
        }

        $owner = User::find($company->user_id);
        if (! $owner) {
            abort(403);
        }
        // The owner's account, pointed at this business for this request only:
        // the change is not marked dirty, so nothing here can save it.
        $owner->setRawAttributes(array_merge($owner->getAttributes(), ['active_company_id' => $company->id]), true);
        Auth::setUser($owner);
        $request->setUserResolver(fn () => $owner);
        Team::act($user, $membership);

        return $next($request);
    }
}
