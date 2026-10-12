<?php

namespace Tests\Feature\Fintech\Security;

use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\User;
use App\Support\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDef;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\Feature\Fintech\Security\Concerns\SweepsRecordRoutes;
use Tests\TestCase;

/**
 * Team roles (CompanyMember::ROLES / RULES, App\Support\Team, the
 * ActAsTeamMember middleware).
 *
 * The expected matrix below is written from the role descriptions the team
 * page shows the owner (CompanyMember::ROLES[*]['help']), not copied from
 * the RULES table, so a rule that drifts from what the owner was promised
 * shows up here:
 *
 *   admin       "Everything you can do, except managing the team, backups and
 *                deleting the business."
 *   accountant  "Sales, purchases, expenses, GST returns, banking and reports.
 *                Cannot change company settings or the team."
 *   staff       "Makes quotations and invoices, records payments, adds
 *                customers and items. Cannot see purchases, accounts or GST,
 *                or cancel and delete."
 *
 * Owner O runs two businesses: P (members are invited here) and Q (nobody
 * is invited to Q).
 */
class TeamRolesTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;
    use SweepsRecordRoutes;

    private array $p;
    private array $q;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
        $this->p = $this->buildBusiness('P');
        $this->q = $this->buildBusiness('Q', $this->p['owner']);
        // The owner works in P when inviting.
        $this->owner()->forceFill(['active_company_id' => $this->p['company']->id])->save();
    }

    private function owner(): User
    {
        return $this->p['owner']->fresh();
    }

    /** Invite by email, then the invitee accepts with their own account (as the UI does). */
    private function join(string $role): User
    {
        Mail::fake();
        $email = "{$role}." . Str::lower(Str::random(5)) . '@team.test';
        $this->actingAs($this->owner())->post(route('team.invite'), ['email' => $email, 'role' => $role])->assertSessionHasNoErrors();
        $user = User::factory()->create(['name' => 'Team ' . ucfirst($role), 'email' => $email]);
        Company::factory()->create(['user_id' => $user->id, 'name' => "Own firm of {$role}", 'state_id' => $this->maharashtra()->id]);
        $token = CompanyMember::where('email', $email)->value('token');
        $this->actingAs($user)->post(route('team.accept', $token))->assertRedirect(route('dashboard'));
        $user = $user->fresh();
        $this->assertSame($this->p['company']->id, (int) $user->active_company_id);

        return $user;
    }

    // ------------------------------------------------------------------
    // The matrix
    // ------------------------------------------------------------------

    /** Route names of every signed-in web page, from the router. */
    private function signedInRouteNames(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->reject(fn (RouteDef $r) => str_starts_with($r->uri(), 'api/'))
            ->filter(fn (RouteDef $r) => $r->getName() && collect($r->gatherMiddleware())->contains(fn ($m) => $m === 'auth' || str_starts_with((string) $m, 'auth:')))
            ->map(fn (RouteDef $r) => $r->getName())->unique()->values()->all();
    }

    /**
     * What each description promises, as [must allow, must deny] patterns.
     * Routes matching neither are a judgement call the descriptions do not
     * settle and are not asserted (e.g. onboarding, imports, the refer page).
     */
    private function promised(string $role): array
    {
        $staffBilling = ['invoices.index', 'invoices.create', 'invoices.store', 'invoices.show', 'invoices.edit', 'invoices.update',
            'invoices.finalize', 'invoices.pdf', 'invoices.print', 'invoices.duplicate', 'invoices.share.*', 'invoices.remind',
            'invoices.payments', 'payments.receipt', 'invoices.templates*',
            'quotations.index', 'quotations.create', 'quotations.store', 'quotations.show', 'quotations.edit', 'quotations.update',
            'quotations.pdf', 'quotations.send', 'quotations.accept', 'quotations.decline', 'quotations.convert', 'quotations.share.*',
            'customers.index', 'customers.create', 'customers.store', 'customers.edit', 'customers.update', 'customers.quick-create',
            'products.index', 'products.create', 'products.store', 'products.edit', 'products.update', 'products.quick-create', 'products.search'];
        $everyoneDenied = ['team.index', 'team.invite', 'team.update', 'team.destroy', 'admin.dashboard', 'admin.users*', 'admin.blog.*', 'admin.breaches.*'];

        return match ($role) {
            'admin' => [
                ['dashboard', 'invoices.*', 'quotations.*', 'customers.*', 'products.*', 'payments.*', 'credit-notes.*', 'purchases.*',
                    'vendors.*', 'finance.*', 'bank.*', 'reports.*', 'companies.edit', 'companies.update', 'company.edit', 'company.update'],
                [...$everyoneDenied, 'backup.*', 'companies.destroy'],
            ],
            'accountant' => [
                ['invoices.*', 'quotations.*', 'customers.*', 'payments.*', 'credit-notes.*', 'purchases.*', 'vendors.*',
                    'finance.expenses*', 'finance.cash-memos.*', 'finance.gstr*', 'finance.aging*', 'finance.index', 'bank.*', 'reports.*'],
                [...$everyoneDenied, 'companies.edit', 'companies.update', 'companies.create', 'companies.store', 'companies.destroy',
                    'company.edit', 'company.update'],
            ],
            'staff' => [
                $staffBilling,
                [...$everyoneDenied, 'backup.*', 'purchases.*', 'vendors.*', 'finance.*', 'bank.*', 'reports.*', 'invoices.gstr1',
                    'invoices.cancel', 'invoices.destroy', 'quotations.destroy', 'customers.destroy', 'products.destroy',
                    'payments.destroy', 'credit-notes.destroy', 'companies.edit', 'companies.update', 'companies.create',
                    'companies.store', 'companies.destroy', 'company.edit', 'company.update', 'dashboard'],
            ],
        };
    }

    public function test_every_role_matches_the_description_the_owner_was_shown(): void
    {
        $this->assertSame(['admin', 'accountant', 'staff'], array_keys(CompanyMember::ROLES), 'a role was added: describe it in promised()');
        $names = $this->signedInRouteNames();
        $this->assertGreaterThan(150, count($names));
        $wrong = [];

        foreach (array_keys(CompanyMember::ROLES) as $role) {
            [$allow, $deny] = $this->promised($role);
            foreach ($names as $name) {
                $mustDeny = Str::is($deny, $name);
                $mustAllow = ! $mustDeny && Str::is($allow, $name);
                // What the middleware actually decides: own pages always pass.
                $allowed = Team::isOwnRoute($name) || CompanyMember::allows($role, $name);
                if ($mustDeny && $allowed) {
                    $wrong[] = "{$role} may open {$name}, but the description rules it out";
                }
                if ($mustAllow && ! $allowed) {
                    $wrong[] = "{$role} is refused {$name}, but the description includes it";
                }
            }
        }

        $this->assertSame([], $wrong, implode("\n", $wrong));
    }

    public function test_a_members_own_pages_never_run_as_the_owner(): void
    {
        foreach (['profile.edit', 'profile.update', 'profile.destroy', 'password.update', 'logout', 'companies.switch', 'team.leave'] as $own) {
            $this->assertTrue(Team::isOwnRoute($own), "{$own} must run as the member");
        }
        $member = $this->join('admin');
        $this->actingAs($member)->get(route('profile.edit'))->assertOk()->assertSee($member->email)->assertDontSee($this->owner()->email);
    }

    // ------------------------------------------------------------------
    // Staff: what the description rules out, over HTTP
    // ------------------------------------------------------------------

    public function test_billing_staff_cannot_open_gst_bank_reports_purchases_or_settings(): void
    {
        $staff = $this->join('staff');
        foreach (['bank.index', 'finance.gstr1', 'finance.gstr3b', 'finance.gstr2b', 'reports.index', 'purchases.index', 'vendors.index',
            'finance.expenses', 'finance.cash-memos.index', 'finance.index', 'finance.aging', 'team.index', 'backup.index', 'dashboard'] as $name) {
            $res = $this->actingAs($staff->fresh())->get(route($name));
            $this->assertTrue($res->isRedirect(route('invoices.index')) || $res->getStatusCode() === 403, "staff opened {$name} ({$res->getStatusCode()})");
        }
        foreach ([route('companies.edit', $this->p['company']), route('reports.export', ['sales-register', 'csv']),
            route('finance.gstr1.export.json'), route('invoices.gstr1')] as $url) {
            $res = $this->actingAs($staff->fresh())->get($url);
            $this->assertTrue($res->isRedirect(route('invoices.index')) || $res->getStatusCode() === 403, "staff opened {$url} ({$res->getStatusCode()})");
        }
    }

    public function test_billing_staff_cannot_change_the_companys_bank_details_on_web_or_api(): void
    {
        $staff = $this->join('staff');
        $company = $this->p['company'];
        $payload = $this->companyPayload($company, ['bank_account_number' => '999999999999']);

        $this->actingAs($staff->fresh())->patch(route('companies.update', $company), $payload);
        $this->actingAs($staff->fresh())->patch(route('company.update'), $payload);
        Sanctum::actingAs($staff->fresh());
        $this->putJson("/api/companies/{$company->id}", $payload)->assertForbidden();

        $this->assertSame('SECRET-ACCT-P', $company->fresh()->bank_account_number);
    }

    public function test_an_accountant_cannot_change_the_companys_bank_details_but_an_admin_can(): void
    {
        $company = $this->p['company'];
        $accountant = $this->join('accountant');
        $this->actingAs($accountant->fresh())->patch(route('companies.update', $company), $this->companyPayload($company, ['bank_account_number' => '111']));
        Sanctum::actingAs($accountant->fresh());
        $this->putJson("/api/companies/{$company->id}", $this->companyPayload($company, ['bank_account_number' => '222']))->assertForbidden();
        $this->assertSame('SECRET-ACCT-P', $company->fresh()->bank_account_number);

        // "Everything you can do" includes the company settings for an admin.
        $admin = $this->join('admin');
        $this->actingAs($admin->fresh())->patch(route('companies.update', $company), $this->companyPayload($company, ['bank_account_number' => '333']))
            ->assertSessionHasNoErrors();
        $this->assertSame('333', $company->fresh()->bank_account_number);
    }

    public function test_no_role_can_invite_members_or_change_a_role(): void
    {
        foreach (['admin', 'accountant', 'staff'] as $role) {
            $member = $this->join($role);
            $before = CompanyMember::count();
            $this->actingAs($member->fresh())->post(route('team.invite'), ['email' => "friend.{$role}@x.test", 'role' => 'admin']);
            $this->assertSame($before, CompanyMember::count(), "{$role} invited someone");
        }
    }

    public function test_a_member_cannot_raise_their_own_role_from_either_business(): void
    {
        $staff = $this->join('staff');
        $membership = CompanyMember::where('user_id', $staff->id)->sole();

        // Working in the owner's business.
        $this->actingAs($staff->fresh())->patch(route('team.update', $membership), ['role' => 'admin']);
        $this->assertSame('staff', $membership->fresh()->role);

        // From their own firm, where they ARE an owner and the team page is theirs.
        $own = Company::where('user_id', $staff->id)->sole();
        $this->actingAs($staff->fresh())->post(route('companies.switch', $own));
        $this->actingAs($staff->fresh())->patch(route('team.update', $membership), ['role' => 'admin'])->assertForbidden();
        $this->actingAs($staff->fresh())->delete(route('team.destroy', $membership))->assertForbidden();
        $this->assertSame('staff', $membership->fresh()->role);

        // Accepting the same invite link again does not change anything either.
        $this->actingAs($staff->fresh())->post(route('team.accept', $membership->token));
        $this->assertSame('staff', $membership->fresh()->role);
    }

    public function test_a_removed_member_loses_web_and_api_access_at_once_and_the_old_link_is_dead(): void
    {
        $accountant = $this->join('accountant');
        $membership = CompanyMember::where('user_id', $accountant->id)->sole();
        $token = $membership->token;
        Sanctum::actingAs($accountant->fresh());
        $this->getJson('/api/invoices')->assertOk()->assertSee('SECRET-CUSTOMER-P');

        $this->actingAs($this->owner())->delete(route('team.destroy', $membership))->assertSessionHasNoErrors();

        Sanctum::actingAs($accountant->fresh());
        $this->getJson('/api/invoices')->assertOk()->assertDontSee('SECRET-CUSTOMER-P');
        $this->actingAs($accountant->fresh())->get(route('invoices.show', $this->p['invoiceOpen']))->assertForbidden();
        $this->actingAs($accountant->fresh())->post(route('invoices.payments', $this->p['invoiceOpen']), [
            'amount' => 10, 'method' => 'cash', 'received_at' => '2026-09-29',
        ])->assertForbidden();
        $this->assertSame(0.0, (float) $this->p['invoiceOpen']->fresh()->paid_amount);

        $this->actingAs($accountant->fresh())->post(route('team.accept', $token))->assertNotFound();
        $this->assertNull(CompanyMember::where('user_id', $accountant->id)->first());
    }

    public function test_a_pending_invite_grants_nothing_until_accepted(): void
    {
        Mail::fake();
        $this->actingAs($this->owner())->post(route('team.invite'), ['email' => 'later@team.test', 'role' => 'admin']);
        $invitee = User::factory()->create(['email' => 'later@team.test']);
        $invitee->forceFill(['active_company_id' => $this->p['company']->id])->save();

        $this->actingAs($invitee->fresh())->get(route('invoices.show', $this->p['invoiceOpen']))->assertForbidden();
        Sanctum::actingAs($invitee->fresh());
        $this->getJson("/api/invoices/{$this->p['invoiceOpen']->id}")->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Leaks inside the signed-in app chrome
    // ------------------------------------------------------------------

    /**
     * The notification bell in the layout lists overdue invoices, recent
     * receipts and invoices issued today by user_id. A member runs as the
     * owner, so the bell shows the owner's OTHER business (Q) - invoice
     * numbers, customer names and amounts the member was never invited to.
     */
    public function test_the_notification_bell_shows_a_member_nothing_from_the_owners_other_business(): void
    {
        $admin = $this->join('admin');
        // Q's open invoice is overdue by now (due 25 Sep, today 30 Sep) and was issued in the last 24 h.
        $html = $this->actingAs($admin->fresh())->get(route('invoices.index'))->assertOk()->getContent();

        $this->assertFalse(str_contains($html, 'SECRET-CUSTOMER-Q'), 'the bell shows a customer of the business the member was not invited to');
    }

    /**
     * profile.* is a member's "own" route, so it runs as the member - but the
     * activity log it shows is the BUSINESS's audit trail, including
     * purchase invoices, vendor names and amounts that billing staff
     * "cannot see".
     */
    public function test_billing_staff_cannot_read_purchases_in_the_activity_log(): void
    {
        $staff = $this->join('staff');
        $html = $this->actingAs($staff->fresh())->get(route('profile.activity'))->getContent();
        $this->assertFalse(str_contains($html, 'SECRET-VENDOR-P'), 'staff read a purchase invoice entry in the activity log');

        Sanctum::actingAs($staff->fresh());
        $json = $this->getJson('/api/profile/activity')->getContent();
        $this->assertFalse(str_contains($json, 'PB-P-1'), 'staff read a purchase invoice entry through the API activity feed');
    }

    // ------------------------------------------------------------------
    // Router sweep: a member of P against the owner's other business Q
    // ------------------------------------------------------------------

    public function test_an_admin_member_of_one_business_cannot_reach_the_owners_other_business_on_any_route(): void
    {
        $this->seedPlatformRecords();
        $admin = $this->join('admin');
        $failures = [];
        $missing = [];

        foreach ([false, true] as $api) {
            foreach ($this->recordRoutes($api) as $route) {
                $url = $this->urlFor($this->q, $route, $missing);
                if ($url === null) {
                    continue;
                }
                $verb = $this->verb($route);
                app('cache')->store()->flush();
                $before = $this->snapshot();
                if ($api) {
                    Sanctum::actingAs($admin->fresh());
                    $res = $this->json($verb, $url, $verb === 'GET' ? [] : $this->sweepPayload($this->q));
                } else {
                    $res = $this->actingAs($admin->fresh())->call($verb, $url, $verb === 'GET' ? [] : $this->sweepPayload($this->q));
                }
                $name = $route->getName();
                $status = $res->getStatusCode();
                // A page outside the admin role is turned away with a redirect to the dashboard.
                $roleRedirect = $name && ! Team::isOwnRoute($name) && ! CompanyMember::allows('admin', $name) && $res->isRedirect(route('dashboard'));
                if (! in_array($status, [403, 404], true) && ! $roleRedirect) {
                    $failures[] = "{$verb} {$url} [" . ($name ?? $route->getActionName()) . "] answered {$status}";
                }
                if ($changed = $this->changedTables($before, $this->snapshot())) {
                    $failures[] = "{$verb} {$url} changed " . implode(', ', $changed);
                }
                foreach (['SECRET-CUSTOMER-Q', 'SECRET-ACCT-Q', 'SECRET-VENDOR-Q', 'SECRET-RENT-Q'] as $m) {
                    if (str_contains((string) $res->getContent(), $m) && $res->getStatusCode() !== 200) {
                        // 200 pages are already failures above; a refusal page must not carry Q's data either.
                        $failures[] = "{$verb} {$url} refusal page contains {$m}";
                    }
                }
                $this->assertSame($this->p['company']->id, (int) $admin->fresh()->active_company_id, "{$url} moved the member into Q");
            }
        }

        $this->assertSame([], $missing, implode("\n", $missing));
        $this->assertSame([], $failures, "A member reached the owner's other business:\n" . implode("\n", $failures));
    }

    /**
     * Every list, report and export a P admin can open, web and API, carries
     * nothing from Q. The notification bell is cut out of the HTML first so
     * that one leak (test above) does not hide any other.
     */
    public function test_no_page_or_export_shows_a_member_the_owners_other_business(): void
    {
        $admin = $this->join('admin');
        $leaks = [];
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDef $r) => in_array('GET', $r->methods(), true) && $r->parameterNames() === [])
            ->filter(fn (RouteDef $r) => collect($r->gatherMiddleware())->contains(fn ($m) => $m === 'auth' || str_starts_with((string) $m, 'auth:')))
            ->reject(fn (RouteDef $r) => in_array($r->uri(), ['logout', 'backup/download', 'api/backup/download'], true));

        foreach ($routes as $route) {
            app('cache')->store()->flush();
            if (str_starts_with($route->uri(), 'api/')) {
                Sanctum::actingAs($admin->fresh());
                $res = $this->getJson('/' . $route->uri());
            } else {
                $res = $this->actingAs($admin->fresh())->get('/' . $route->uri());
            }
            $body = $res->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? $res->streamedContent() : (string) $res->getContent();
            $start = strpos($body, '<div x-data="{ open: false, unseen:');
            if ($start !== false && ($end = strpos($body, 'View unpaid invoices</a>', $start)) !== false) {
                $body = substr($body, 0, $start) . substr($body, $end);
            }
            foreach (['SECRET-CUSTOMER-Q', 'SECRET-PROSPECT-Q', 'SECRET-ITEM-Q', 'SECRET-VENDOR-Q', 'SECRET-SELLER-Q', 'SECRET-RENT-Q',
                'SECRET-BANK-Q', 'SECRET-ACCT-Q', 'SECRET-2B-SUPPLIER-Q', 'SECRET-QUOTE-Q'] as $m) {
                if (str_contains($body, $m)) {
                    $leaks[] = "/{$route->uri()} shows {$m}";
                }
            }
        }
        $this->assertGreaterThan(60, $routes->count(), "the page list shrank");
        $this->assertSame([], $leaks, implode("\n", $leaks));
    }

    private function companyPayload(Company $c, array $override): array
    {
        return array_merge([
            'name' => $c->name, 'state_id' => $c->state_id, 'country' => 'India', 'default_currency' => 'INR',
            'invoice_prefix' => $c->invoice_prefix, 'invoice_number_padding' => $c->invoice_number_padding,
            'bank_ifsc' => $c->bank_ifsc, 'upi_id' => $c->upi_id,
        ], $override);
    }
}
