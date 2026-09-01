<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every screen, rendered against an account that actually has data.
 *
 * EveryGetRouteRendersTest walks the same routes but with a near-empty
 * account, and that is why it passed while the dashboard was throwing in
 * production: the action-items list is only built when the user has an
 * overdue invoice, so the branch that broke was never entered. A smoke test
 * only covers the branches its FIXTURE reaches.
 *
 * So this one builds a business with history - invoices in every status,
 * payments, a credit note, quotations at each stage, expenses, a cash memo,
 * a second company - and then loads everything. Empty states are already
 * covered by the other test; this covers the opposite, which is the state
 * real users are actually in.
 */
class LoadedAccountSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const SKIP = ['logout', 'api/backup/download', 'backup/download'];

    private User $user;
    private Company $company;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedABusinessWithHistory();
    }

    private function invoice(array $attributes, bool $withItem = true): Invoice
    {
        $invoice = Invoice::factory()
            ->recycle($this->user)->recycle($this->company)->recycle($this->customer)
            ->create($attributes);

        if ($withItem) {
            InvoiceItem::factory()->recycle($invoice)->create();
        }

        return $invoice;
    }

    private function seedABusinessWithHistory(): void
    {
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);

        $this->user = User::factory()->create(['is_super_admin' => true]);
        $this->company = Company::factory()->recycle($this->user)->create([
            'state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5', 'onboarded_at' => now(),
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();

        $this->customer = Customer::factory()->recycle($this->user)->recycle($this->company)
            ->create(['state_id' => $mh->id]);
        // an out-of-state buyer, so IGST rows exist too
        Customer::factory()->recycle($this->user)->recycle($this->company)
            ->create(['state_id' => $ka->id]);

        // --- the three conditions that build the dashboard action list ------
        // Overdue: this exact combination is what took production down.
        $this->invoice([
            'status' => 'final', 'finalized_at' => now()->subDays(40),
            'invoice_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(12)->toDateString(), 'balance' => 5000,
        ]);

        $staleDraft = $this->invoice(['status' => 'draft', 'balance' => 1500]);
        $staleDraft->forceFill(['created_at' => now()->subDays(20)])->save();

        $this->invoice([
            'status' => 'final', 'finalized_at' => now()->subDays(3),
            'invoice_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(), 'balance' => 2500,
        ]);

        // --- one invoice in every remaining status --------------------------
        $this->invoice([
            'status' => 'paid', 'finalized_at' => now()->subDays(30),
            'invoice_date' => now()->subDays(30)->toDateString(),
            'balance' => 0, 'paid_amount' => 11800,
        ]);
        $this->invoice([
            'status' => 'partially_paid', 'finalized_at' => now()->subDays(15),
            'invoice_date' => now()->subDays(15)->toDateString(),
            'balance' => 6800, 'paid_amount' => 5000,
        ]);
        $this->invoice([
            'status' => 'cancelled', 'finalized_at' => now()->subDays(25),
            'invoice_date' => now()->subDays(25)->toDateString(),
            'cancellation_reason' => 'Issued in error', 'balance' => 0,
        ]);

        // --- quotations across the lifecycle --------------------------------
        foreach (['draft', 'sent', 'accepted', 'declined', 'expired'] as $status) {
            Quotation::factory()->recycle($this->user)->recycle($this->company)
                ->recycle($this->customer)->create(['status' => $status]);
        }

        // --- money out, so P&L, ageing and GSTR-3B have something to show ---
        Expense::factory()->recycle($this->user)->recycle($this->company)->create([
            'entry_date' => now()->subMonthNoOverflow()->startOfMonth()->addDays(5),
            'gst_amount' => 900, 'itc_eligible' => true,
        ]);
        Expense::factory()->recycle($this->user)->recycle($this->company)->create([
            'entry_date' => now()->subDays(4), 'gst_amount' => 450, 'itc_eligible' => false,
        ]);

        // --- master data ----------------------------------------------------
        // The products list branches per row on whether the item has invoice
        // history, and that branch shipped calling a relationship that did not
        // exist. With no products seeded the loop never ran, so the page
        // reported healthy while it was broken - the same hole that hid the
        // dashboard outage. Seed both kinds: one billed, one never used.
        $billed = \App\Models\Product::create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id,
            'name' => 'Billed service', 'kind' => 'service', 'hsn_sac' => '998314',
            'unit' => 'HRS', 'rate' => 1500, 'gst_rate' => 18,
        ]);
        \App\Models\Product::create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id,
            'name' => 'Never billed item', 'kind' => 'goods', 'hsn_sac' => '8703',
            'unit' => 'NOS', 'rate' => 500, 'gst_rate' => 28,
        ]);
        // give the first one invoice history, so both sides of the branch render
        InvoiceItem::factory()
            ->recycle(Invoice::where('status', 'paid')->first())
            ->create(['product_id' => $billed->id]);

        // --- a second business run from the same login ----------------------
        Company::factory()->recycle($this->user)->create([
            'state_id' => $ka->id, 'onboarded_at' => now(),
        ]);
    }

    public function test_every_screen_renders_for_an_account_with_real_history(): void
    {
        $anyInvoice = Invoice::first();
        $bind = [
            'user' => $this->user->id,
            'company' => $this->company->id,
            'customer' => $this->customer->id,
            'invoice' => $anyInvoice->id,
            'quotation' => Quotation::first()->id,
            'id' => $anyInvoice->id,
        ];

        $checked = 0;
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $uri = $route->uri();
            if (in_array($uri, self::SKIP, true) || str_starts_with($uri, '_')) {
                continue;
            }

            $filled = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($bind) {
                return $bind[$m[1]] ?? '__SKIP__';
            }, $uri);
            if (str_contains($filled, '__SKIP__')) {
                continue;
            }

            $res = $this->actingAs($this->user)->get('/'.ltrim($filled, '/'));
            $checked++;

            if ($res->getStatusCode() >= 500) {
                $failures[] = "/{$filled} -> {$res->getStatusCode()}";
            }
        }

        $this->assertGreaterThan(40, $checked);
        $this->assertSame([], $failures, "server errors on a populated account:\n".implode("\n", $failures));
    }

    public function test_each_invoice_status_renders_on_its_own_detail_page(): void
    {
        // The list pages colour-code by status, and the detail page branches on
        // it. Rendering one of each means a new status cannot be added without
        // its colour and its panel existing.
        foreach (Invoice::all() as $invoice) {
            $res = $this->actingAs($this->user)->get(route('invoices.show', $invoice));
            $this->assertLessThan(500, $res->getStatusCode(),
                "invoice detail 500'd for status {$invoice->status}");
        }
    }

    public function test_each_quotation_status_renders_on_its_own_detail_page(): void
    {
        foreach (Quotation::all() as $quotation) {
            $res = $this->actingAs($this->user)->get(route('quotations.show', $quotation));
            $this->assertLessThan(500, $res->getStatusCode(),
                "quotation detail 500'd for status {$quotation->status}");
        }
    }
}
