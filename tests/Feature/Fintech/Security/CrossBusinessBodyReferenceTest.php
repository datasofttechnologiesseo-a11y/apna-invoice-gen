<?php

namespace Tests\Feature\Fintech\Security;

use App\Models\BankStatementLine;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\PurchaseBill;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\TestCase;

/**
 * The URL sweep proves A cannot open B's records by id in the PATH. This
 * file covers ids A puts in the request BODY: a customer, item, vendor,
 * invoice line, bank match or import preview that belongs to B. None may
 * be attached to A's documents, and none of B's data may come back.
 */
class CrossBusinessBodyReferenceTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;

    private array $a;
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
        $this->a = $this->buildBusiness('A');
        $this->b = $this->buildBusiness('B');
    }

    private function owner()
    {
        return $this->a['owner']->fresh();
    }

    private function line(array $override = []): array
    {
        return array_merge(['description' => 'Line', 'hsn_sac' => '8471', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 100, 'gst_rate' => 18], $override);
    }

    private function bStockOnHand(): float
    {
        // Opening 100 - 2 sold on INV-0001 + 5 adjusted in = 103 (fixture).
        return (float) $this->b['product']->fresh()->stockOnHand();
    }

    public function test_an_invoice_cannot_be_raised_on_another_businesss_customer_or_item(): void
    {
        $invoices = Invoice::count();
        $this->actingAs($this->owner())->post(route('invoices.store'), [
            'customer_id' => $this->b['customer']->id, 'invoice_date' => '2026-09-29', 'items' => [$this->line()],
        ])->assertSessionHasErrors('customer_id');
        $this->actingAs($this->owner())->post(route('invoices.store'), [
            'customer_id' => $this->a['customer']->id, 'invoice_date' => '2026-09-29',
            'items' => [$this->line(['product_id' => $this->b['product']->id, 'description' => ''])],
        ])->assertSessionHasErrors('items.0.product_id');

        Sanctum::actingAs($this->owner());
        $this->postJson('/api/invoices', ['customer_id' => $this->b['customer']->id, 'invoice_date' => '2026-09-29', 'items' => [$this->line()]])
            ->assertStatus(422);
        Sanctum::actingAs($this->owner());
        $this->postJson('/api/invoices', ['customer_id' => $this->a['customer']->id, 'invoice_date' => '2026-09-29',
            'items' => [$this->line(['product_id' => $this->b['product']->id])]])->assertStatus(422);

        $this->assertSame($invoices, Invoice::count());
    }

    public function test_a_draft_cannot_be_moved_onto_another_businesss_customer(): void
    {
        $draft = $this->a['invoiceDraft'];
        $this->actingAs($this->owner())->put(route('invoices.update', $draft), [
            'customer_id' => $this->b['customer']->id, 'invoice_date' => '2026-09-14', 'items' => [$this->line()],
        ]);
        $this->assertSame($this->a['customer']->id, (int) $draft->fresh()->customer_id);

        Sanctum::actingAs($this->owner());
        $this->putJson("/api/invoices/{$draft->id}", ['customer_id' => $this->b['customer']->id, 'invoice_date' => '2026-09-14', 'items' => [$this->line()]])
            ->assertStatus(422);
        $this->assertSame($this->a['customer']->id, (int) $draft->fresh()->customer_id);
    }

    public function test_a_quotation_cannot_use_another_businesss_customer_or_item_on_the_web(): void
    {
        $quotes = Quotation::count();
        $this->actingAs($this->owner())->post(route('quotations.store'), [
            'customer_id' => $this->b['customer']->id, 'quote_date' => '2026-09-29', 'items' => [$this->line()],
        ])->assertNotFound();
        $this->actingAs($this->owner())->post(route('quotations.store'), [
            'customer_id' => $this->a['customer']->id, 'quote_date' => '2026-09-29',
            'items' => [$this->line(['product_id' => $this->b['product']->id])],
        ])->assertSessionHasErrors('items.0.product_id');
        $this->assertSame($quotes, Quotation::count());
    }

    /**
     * The API's quotation rules check items.*.product_id only as "an
     * integer", not "an item of this business" (the web form and the API
     * invoice both check the business). B's item id is stored on A's
     * quotation line, carried onto the invoice it converts to, and B's item
     * name can then come back to A.
     */
    public function test_an_api_quotation_cannot_link_a_line_to_another_businesss_item(): void
    {
        Sanctum::actingAs($this->owner());
        $res = $this->postJson('/api/quotations', [
            'customer_id' => $this->a['customer']->id, 'quote_date' => '2026-09-29',
            'items' => [$this->line(['product_id' => $this->b['product']->id, 'description' => 'Probe'])],
        ]);

        if ($res->getStatusCode() === 422) {
            $this->assertArrayHasKey('items.0.product_id', $res->json('errors'));

            return;
        }
        $res->assertCreated();
        $quote = Quotation::latest('id')->firstOrFail();

        // Impact: B's item name is printed on A's own quotation page. Item ids
        // are sequential, so walking them reads every business's item list.
        $html = $this->actingAs($this->owner())->get(route('quotations.show', $quote))->assertOk()->getContent();
        $this->assertFalse(str_contains($html, 'SECRET-ITEM-B'), "A's quotation page shows B's item name");

        $linked = QuotationItem::where('quotation_id', $quote->id)->value('product_id');
        $this->assertNull($linked, "A's quotation line was linked to B's item #{$this->b['product']->id}");
    }

    public function test_a_purchase_invoice_cannot_use_another_businesss_vendor_or_bring_stock_into_its_item(): void
    {
        $bills = PurchaseBill::count();
        $this->actingAs($this->owner())->post(route('purchases.store'), [
            'vendor_id' => $this->b['vendor']->id, 'bill_number' => 'X-1', 'bill_date' => '2026-09-29',
            'items' => [$this->line()],
        ])->assertNotFound();
        $this->assertSame($bills, PurchaseBill::count());

        $stock = $this->bStockOnHand();
        $this->actingAs($this->owner())->post(route('purchases.store'), [
            'vendor_id' => $this->a['vendor']->id, 'bill_number' => 'X-2', 'bill_date' => '2026-09-29',
            'items' => [$this->line(['product_id' => $this->b['product']->id, 'quantity' => 40])],
        ])->assertSessionHasNoErrors();
        $bill = PurchaseBill::latest('id')->firstOrFail();
        $this->assertNull($bill->items()->first()->product_id, "the line kept B's item");
        $this->assertSame($stock, $this->bStockOnHand(), "B's stock moved");
        $this->assertSame(0, StockMovement::where('company_id', $this->a['company']->id)->where('product_id', $this->b['product']->id)->count());
    }

    public function test_a_credit_note_cannot_return_another_invoices_line(): void
    {
        // B's invoice INV-0001 line (2 x SECRET-ITEM-B) offered as a return on A's INV-0001.
        $bLine = $this->b['invoiceOpen']->items()->firstOrFail();
        $stock = $this->bStockOnHand();
        $notes = CreditNote::count();

        $this->actingAs($this->owner())->post(route('credit-notes.store', $this->a['invoiceOpen']), [
            'credit_note_date' => '2026-09-29', 'reason' => 'sales_return', 'lines' => [$bLine->id => 2],
        ]);

        $made = CreditNote::count() - $notes;
        if ($made === 1) {
            $cn = CreditNote::latest('id')->firstOrFail();
            $this->assertSame(0, $cn->items()->where('invoice_item_id', $bLine->id)->count(), "A's credit note carries B's invoice line");
        }
        $this->assertSame($stock, $this->bStockOnHand(), "B's stock came back on A's credit note");
    }

    public function test_a_bank_line_cannot_be_matched_to_another_businesss_receipt(): void
    {
        $line = BankStatementLine::where('company_id', $this->a['company']->id)->where('credit', '>', 0)->firstOrFail();
        if ($line->status === 'matched') {
            $this->actingAs($this->owner())->post(route('bank.lines.unmatch', $line))->assertSessionHasNoErrors();
        }

        $this->actingAs($this->owner())->post(route('bank.lines.match', $line), [
            'candidate' => \App\Models\Payment::class . '#' . $this->b['payment']->id,
        ])->assertStatus(422);
        $this->assertSame('unmatched', $line->fresh()->status);
        $this->assertNull($line->fresh()->matched_id);
    }

    public function test_an_import_preview_belongs_to_the_person_and_business_that_made_it(): void
    {
        // B previews a customer import; A presents B's token.
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.csv';
        file_put_contents($path, "Name,State,Country\nImported By B,Maharashtra,India\n");
        $file = new \Illuminate\Http\UploadedFile($path, 'customers.csv', 'text/csv', null, true);
        $preview = $this->actingAs($this->b['owner']->fresh())->post(route('imports.preview', 'customers'), ['file' => $file])->assertOk();
        preg_match('/name="token" value="([0-9a-f-]{36})"/', $preview->getContent(), $m);
        $this->assertNotEmpty($m, 'preview page carries its token');

        $this->actingAs($this->owner())->post(route('imports.confirm', 'customers'), ['token' => $m[1]])->assertSessionHasErrors('file');
        $this->assertSame(0, \App\Models\Customer::where('name', 'Imported By B')->count());
    }
}
