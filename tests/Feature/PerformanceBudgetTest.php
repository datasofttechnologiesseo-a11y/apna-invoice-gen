<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PurchaseBill;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards two speed regressions that tests asserting on content cannot see.
 *
 * 1. Lists and registers must not run one query per row. Eloquent's cursor()
 *    silently ignores ->with(), so the sales register ran two extra queries
 *    for every invoice (362 queries for 175 invoices). Each screen here is
 *    loaded with a small book and again with three times the rows; the query
 *    count must not grow with the rows.
 * 2. Invoice PDFs embed only the font glyphs they use. With the whole of
 *    DejaVu Sans embedded each invoice was about 1.7 MB; subset it is ~65 KB.
 */
class PerformanceBudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private State $state;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->state = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create([
            'state_id' => $this->state->id, 'gstin' => '27ABCDE1234F1Z5', 'onboarded_at' => now(),
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    /** A customer with paid invoices, a credit note and a quotation; a vendor with a bill. */
    private function addBusiness(int $times): ?Customer
    {
        $customer = null;
        for ($n = 0; $n < $times; $n++) {
            $i = ++$this->seq;
            $customer = Customer::factory()->recycle($this->user)->recycle($this->company)
                ->create(['state_id' => $this->state->id]);
            $invoice = Invoice::factory()->recycle($this->user)->recycle($this->company)->recycle($customer)->create([
                'status' => 'paid', 'invoice_number' => sprintf('INV-%04d', $i), 'finalized_at' => now(),
                'place_of_supply_state_id' => $this->state->id, 'paid_amount' => 1180, 'balance' => 0,
            ]);
            InvoiceItem::factory()->recycle($invoice)->create();
            Payment::create([
                'user_id' => $this->user->id, 'company_id' => $this->company->id, 'invoice_id' => $invoice->id,
                'receipt_number' => "RCPT-$i", 'received_at' => now(), 'amount' => 1180, 'method' => 'upi',
            ]);
            CreditNote::create([
                'user_id' => $this->user->id, 'company_id' => $this->company->id, 'invoice_id' => $invoice->id,
                'credit_note_number' => "CN-$i", 'credit_note_date' => now(), 'amount' => 10, 'reason' => 'rate_correction',
            ]);
            Quotation::factory()->recycle($this->user)->recycle($this->company)->recycle($customer)->create();
            $vendor = Vendor::create([
                'user_id' => $this->user->id, 'company_id' => $this->company->id,
                'name' => "Vendor $i", 'state_id' => $this->state->id,
            ]);
            PurchaseBill::create([
                'user_id' => $this->user->id, 'company_id' => $this->company->id, 'vendor_id' => $vendor->id,
                'bill_number' => "B-$i", 'bill_date' => now(), 'due_date' => now()->addDays(30),
                'subtotal' => 1000, 'total_tax' => 180, 'total_cgst' => 90, 'total_sgst' => 90,
                'grand_total' => 1180, 'paid_amount' => 0, 'balance' => 1180, 'status' => 'unpaid',
            ]);
        }

        return $customer;
    }

    private function queriesFor(string $url): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $this->actingAs($this->user)->get($url)->assertOk();

        return $count;
    }

    public function test_registers_and_lists_do_not_query_once_per_row(): void
    {
        $this->addBusiness(2);
        // the customer whose ledger is read gets three invoices of its own
        $ledgerCustomer = Customer::first();
        foreach ([1, 2] as $k) {
            $extra = Invoice::factory()->recycle($this->user)->recycle($this->company)->recycle($ledgerCustomer)->create([
                'status' => 'final', 'invoice_number' => 'LEDGER-' . $k, 'finalized_at' => now(),
            ]);
            Payment::create([
                'user_id' => $this->user->id, 'company_id' => $this->company->id, 'invoice_id' => $extra->id,
                'receipt_number' => 'LR-' . $k, 'received_at' => now(), 'amount' => 100, 'method' => 'cash',
            ]);
        }

        $urls = [
            'sales register' => route('reports.show', 'sales-register'),
            'receipts' => route('reports.show', 'receipts'),
            'credit notes' => route('reports.show', 'credit-notes'),
            'quotations' => route('reports.show', 'quotations'),
            'customers' => route('reports.show', 'customers'),
            'purchase register' => route('reports.show', 'purchase-register'),
            'vendors' => route('reports.show', 'vendors'),
            'vendor payments' => route('reports.show', 'vendor-payments'),
            'invoices list' => route('invoices.index'),
            'customers list' => route('customers.index'),
            'quotations list' => route('quotations.index'),
            'purchases list' => route('purchases.index'),
        ];

        $small = array_map(fn ($url) => $this->queriesFor($url), $urls);
        $smallLedger = $this->ledgerQueries($ledgerCustomer, 0);

        $this->addBusiness(4);
        $large = array_map(fn ($url) => $this->queriesFor($url), $urls);
        $largeLedger = $this->ledgerQueries($ledgerCustomer, 4);

        $grew = array_filter(array_keys($urls), fn ($k) => $large[$k] > $small[$k]);
        $this->assertSame([], array_values($grew), 'query count grows with rows on: '
            . implode(', ', array_map(fn ($k) => "$k ({$small[$k]} -> {$large[$k]})", $grew)));
        $this->assertSame($smallLedger, $largeLedger, 'customer ledger queries per invoice');
    }

    /** Ledger query count after giving the customer $extra more paid invoices. */
    private function ledgerQueries(Customer $customer, int $extra): int
    {
        for ($k = 0; $k < $extra; $k++) {
            $inv = Invoice::factory()->recycle($this->user)->recycle($this->company)->recycle($customer)->create([
                'status' => 'paid', 'invoice_number' => 'LX-' . $k, 'finalized_at' => now(), 'balance' => 0,
            ]);
            Payment::create([
                'user_id' => $this->user->id, 'company_id' => $this->company->id, 'invoice_id' => $inv->id,
                'receipt_number' => 'LX-' . $k, 'received_at' => now(), 'amount' => 1180, 'method' => 'cash',
            ]);
        }

        return $this->queriesFor(route('customers.ledger', $customer));
    }

    public function test_invoice_pdf_embeds_only_the_glyphs_it_uses(): void
    {
        $this->assertTrue(config('dompdf.options.enable_font_subsetting'));

        $customer = $this->addBusiness(1);
        $invoice = Invoice::where('customer_id', $customer->id)->first();

        $pdf = $this->actingAs($this->user)->get(route('invoices.pdf', $invoice))->assertOk()->getContent();

        $this->assertStringStartsWith('%PDF-', $pdf);
        // subset fonts are tagged ABCDEF+FontName; full embeds are ~1.7 MB
        $this->assertMatchesRegularExpression('/\/BaseFont \/[A-Z]{6}\+DejaVuSans/', $pdf);
        $this->assertLessThan(300 * 1024, strlen($pdf), 'invoice PDF is ' . round(strlen($pdf) / 1024) . ' KB');
    }
}
