<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The mobile Home screen (GET /api/dashboard) shows the web dashboard's
 * numbers. Both come from App\Services\DashboardSummary; this pins that the
 * API carries them through unchanged, in the app's shape, and that a team
 * member sees what the web shows them.
 */
class ApiDashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    private Customer $acme;

    private Customer $bharat;

    protected function setUp(): void
    {
        parent::setUp();
        // 13 Nov: GSTR-1 for October was due on the 11th, GSTR-3B is due on the 20th.
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(10, 0));

        $state = State::factory()->create(['gst_code' => '27']);
        $this->owner = User::factory()->create();
        $this->company = Company::factory()->recycle($this->owner)->create([
            'state_id' => $state->id, 'onboarded_at' => now(), 'gstin' => '27ABCDE1234F1Z5', 'name' => 'Arbaz Traders',
        ]);
        $this->owner->forceFill(['active_company_id' => $this->company->id])->save();
        $this->acme = Customer::factory()->recycle($this->owner)->recycle($this->company)->create(['name' => 'Acme', 'state_id' => $state->id]);
        $this->bharat = Customer::factory()->recycle($this->owner)->recycle($this->company)->create(['name' => 'Bharat', 'state_id' => $state->id]);

        $inv = fn (Customer $c, array $a) => Invoice::factory()->recycle($this->owner)->recycle($this->company)->recycle($c)->create($a + [
            'finalized_at' => now()->subDays(2), 'invoice_number' => 'INV-'.fake()->unique()->numberBetween(1, 99999),
        ]);
        $day = fn (int $d) => now()->addDays($d)->toDateString();

        $inv($this->acme, ['status' => 'paid', 'invoice_date' => $day(-20), 'due_date' => $day(-5), 'grand_total' => 1000, 'paid_amount' => 1000, 'balance' => 0]);
        $inv($this->acme, ['status' => 'partially_paid', 'invoice_date' => $day(-3), 'due_date' => $day(10), 'grand_total' => 2000, 'paid_amount' => 500, 'balance' => 1500]);
        $inv($this->bharat, ['status' => 'final', 'invoice_date' => $day(-70), 'due_date' => $day(-40), 'grand_total' => 3000, 'balance' => 3000]);
        $inv($this->bharat, ['status' => 'final', 'invoice_date' => $day(-1), 'due_date' => $day(5), 'grand_total' => 500, 'balance' => 500, 'finalized_at' => now()]);
        $inv($this->acme, ['status' => 'draft', 'invoice_date' => $day(0), 'grand_total' => 700, 'balance' => 700, 'finalized_at' => null, 'invoice_number' => null]);
        $inv($this->acme, ['status' => 'cancelled', 'invoice_date' => $day(-4), 'grand_total' => 900, 'balance' => 0]);

        Payment::create([
            'user_id' => $this->owner->id, 'company_id' => $this->company->id,
            'invoice_id' => Invoice::where('status', 'partially_paid')->value('id'),
            'receipt_number' => 'RCPT-0001', 'received_at' => now(), 'amount' => 500, 'method' => 'upi',
        ]);

        // Eight categories: six slices and "Everything else".
        foreach (['rent', 'utilities', 'salaries', 'travel', 'fuel', 'marketing', 'contractor', 'purchases'] as $i => $cat) {
            Expense::factory()->recycle($this->owner)->recycle($this->company)->create([
                'category' => $cat, 'amount' => 1000 * (8 - $i), 'gst_amount' => 100, 'entry_date' => $day(-$i * 20),
            ]);
        }
    }

    private function web(): TestResponse
    {
        return $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
    }

    private function api(User $as): array
    {
        Sanctum::actingAs($as);

        return $this->getJson('/api/dashboard')->assertOk()->json('data');
    }

    public function test_the_home_screen_carries_the_web_dashboards_numbers(): void
    {
        $web = $this->web();
        $data = $this->api($this->owner);

        // Existing keys are unchanged; the new ones are added.
        $this->assertSame(['id' => $this->company->id, 'name' => 'Arbaz Traders', 'gstin' => '27ABCDE1234F1Z5'], $data['company']);
        $this->assertSame(['invoices' => 6, 'drafts' => 1, 'customers' => 2, 'products' => 0, 'quotations_awaiting' => 0], $data['counts']);
        $this->assertEqualsWithDelta(5000, $data['kpis']['outstanding'], 0.001);
        $this->assertEqualsWithDelta(3000, $data['kpis']['overdue'], 0.001);
        $this->assertCount(5, $data['recent_invoices']);

        $this->assertSame($web->viewData('todayIssued'), $data['kpis']['today_issued']);
        $this->assertEqualsWithDelta($web->viewData('todayCollected'), $data['kpis']['today_collected'], 0.001);
        $this->assertEqualsWithDelta((float) $web->viewData('stats')['received_this_month'], $data['kpis']['collected_this_month'], 0.001);
        $this->assertEqualsWithDelta(8100, $data['kpis']['expenses_this_month'], 0.001); // rent, today

        // The bars: the web's a/b, renamed.
        $series = $web->viewData('chartSeries');
        foreach (['sales' => ['billed', 'collected'], 'purchases' => ['spend', 'gst']] as $view => [$a, $b]) {
            foreach (['monthly', 'yearly'] as $span) {
                $this->assertCount(count($series[$view][$span]), $data[$view][$span]);
                foreach ($series[$view][$span] as $i => $bar) {
                    $got = $data[$view][$span][$i];
                    $this->assertSame([$bar['label'], $bar['full'], $bar['from'], $bar['to']], [$got['label'], $got['full'], $got['from'], $got['to']]);
                    $this->assertEqualsWithDelta($bar['a'], $got[$a], 0.001);
                    $this->assertEqualsWithDelta($bar['b'], $got[$b], 0.001);
                }
            }
        }
        $this->assertSame('Nov', end($data['sales']['monthly'])['label']);
        $this->assertSame('FY 26-27', end($data['sales']['yearly'])['label']);

        // The spend ring.
        $ring = $web->viewData('spendByCategory');
        $this->assertCount(7, $data['spend_by_category']);
        foreach ($ring as $i => $slice) {
            $this->assertSame($slice['label'], $data['spend_by_category'][$i]['label']);
            $this->assertSame($slice['color'], $data['spend_by_category'][$i]['color']);
            $this->assertEqualsWithDelta($slice['amount'], $data['spend_by_category'][$i]['amount'], 0.001);
        }
        $this->assertSame('rent', $data['spend_by_category'][0]['key']);
        $this->assertSame('other', $data['spend_by_category'][6]['key']);

        // Who owes you.
        $owes = $web->viewData('whoOwes');
        $this->assertSame($owes->pluck('name')->all(), array_column($data['top_debtors'], 'name'));
        $this->assertSame([
            'customer_id' => $this->bharat->id, 'name' => 'Bharat', 'amount' => 3500.0,
            'oldest_due_date' => now()->subDays(40)->toDateString(), 'overdue_days' => 40,
        ], $data['top_debtors'][0]);

        // Aging adds up to the Outstanding tile; the 40-day-late bill is in 31-60.
        $this->assertSame(['0-30', '31-60', '61-90', '90+'], array_column($data['aging'], 'bucket'));
        $this->assertEqualsWithDelta((float) $web->viewData('stats')['outstanding'], array_sum(array_column($data['aging'], 'amount')), 0.001);
        $this->assertEquals(['bucket' => '31-60', 'label' => '31-60 days', 'amount' => 3000, 'count' => 1], $data['aging'][1]);
        $this->assertSame(2, $data['aging'][0]['count']);

        // This FY's bills by payment state.
        $states = collect($data['status_breakdown'])->keyBy('status');
        $this->assertSame(['paid', 'partially_paid', 'unpaid', 'overdue', 'draft', 'cancelled'], $states->keys()->all());
        foreach (['paid' => [1, 1000], 'partially_paid' => [1, 2000], 'unpaid' => [1, 500], 'overdue' => [1, 3000], 'draft' => [1, 700], 'cancelled' => [1, 900]] as $s => [$n, $amt]) {
            $this->assertSame($n, $states[$s]['count'], $s);
            $this->assertEqualsWithDelta($amt, $states[$s]['amount'], 0.001, $s);
        }

        // The GST card.
        $gst = $web->viewData('gstCalendar');
        $this->assertSame($gst['label'], $data['gst']['label']);
        $this->assertSame(['month' => '2026-10'], $data['gst']['month_query']);
        $this->assertSame('2026-11-11', $data['gst']['gstr1_due']);
        $this->assertSame($gst['gstr3b_due']->toDateString(), $data['gst']['gstr3b_due']);
        $this->assertFalse($data['gst']['gstr2b_uploaded']);

        // Action items: the web's, in the web's order, with app targets.
        $this->assertSame(array_column($web->viewData('actionItems'), 'title'), array_column($data['action_items'], 'title'));
        $byIcon = collect($data['action_items'])->groupBy('icon');
        $this->assertSame(['screen' => 'invoices', 'params' => ['status' => 'outstanding']], $byIcon['overdue'][0]['target']);
        $this->assertSame('danger', $byIcon['overdue'][0]['tone']);
        $this->assertSame(['screen' => 'gstr1', 'params' => ['month' => '2026-10']], $byIcon['gst'][0]['target']);
        $this->assertSame('gstr3b', $byIcon['gst'][1]['target']['screen']);
    }

    public function test_an_accountant_sees_the_owners_home_screen_and_billing_staff_none(): void
    {
        $owner = $this->api($this->owner);

        $member = function (string $role) {
            $user = User::factory()->create();
            CompanyMember::forceCreate([
                'company_id' => $this->company->id, 'user_id' => $user->id, 'email' => $user->email,
                'role' => $role, 'token' => Str::random(32), 'accepted_at' => now(),
            ]);
            $user->forceFill(['active_company_id' => $this->company->id])->save();

            return $user->fresh();
        };

        $accountant = $this->api($member('accountant'));
        unset($owner['recent_invoices'], $accountant['recent_invoices']);
        $this->assertSame($owner, $accountant);

        Sanctum::actingAs($member('staff'));
        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_a_new_account_gets_empty_sections_not_errors(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create(['gstin' => '27ABCDE1234F1Z5']);
        $user->forceFill(['active_company_id' => $company->id])->save();

        $data = $this->api($user->fresh());
        $this->assertNull($data['gst']); // nothing to file on a first run
        $this->assertSame([], $data['top_debtors']);
        $this->assertSame([], $data['spend_by_category']);
        $this->assertSame([], $data['action_items']);
        $this->assertCount(6, $data['sales']['monthly']);
        $this->assertSame(0.0, (float) array_sum(array_column($data['aging'], 'amount')));
    }
}
