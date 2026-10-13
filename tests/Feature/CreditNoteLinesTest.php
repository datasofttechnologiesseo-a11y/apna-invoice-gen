<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\State;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CreditNoteSplit;
use App\Services\Gstr1Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A sales return of 1 almirah out of 7 on an invoice that also bills an
 * installation service. Picking the line must credit that line only: its
 * value and GST, its HSN in GSTR-1 Table 12 with the quantity that came
 * back, and the almirah back in stock. A note with only an amount (a price
 * reduction) must behave exactly as it always did.
 */
class CreditNoteLinesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Invoice $invoice;
    private Product $almirah;

    protected function setUp(): void
    {
        parent::setUp();
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id, 'state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5', 'invoice_prefix' => 'INV', 'invoice_counter' => 0,
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $customer = Customer::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'state_id' => $mh->id, 'gstin' => null]);

        $this->actingAs($this->user)->post(route('products.store'), [
            'name' => 'Steel almirah', 'kind' => 'goods', 'hsn_sac' => '94032010', 'unit' => 'NOS', 'rate' => 12187.50, 'gst_rate' => 18,
            'is_active' => 1, 'track_stock' => 1, 'opening_stock' => 20,
        ])->assertSessionHasNoErrors();
        $this->almirah = Product::where('name', 'Steel almirah')->firstOrFail();

        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $customer->id, 'invoice_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $this->almirah->id, 'description' => 'Steel almirah', 'hsn_sac' => '94032010', 'quantity' => 7, 'unit' => 'NOS', 'rate' => 12187.50, 'gst_rate' => 18],
                ['description' => 'Installation', 'hsn_sac' => '998719', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 5000, 'gst_rate' => 18],
            ],
        ])->assertSessionHasNoErrors();
        $this->invoice = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $this->invoice))->assertSessionHasNoErrors();
        $this->invoice->refresh();
    }

    private function almirahLine()
    {
        return $this->invoice->items()->where('hsn_sac', '94032010')->firstOrFail();
    }

    private function hsnRow(string $code): array
    {
        $g = app(Gstr1Builder::class)->build($this->company, now()->startOfMonth(), now()->endOfMonth());

        return collect($g['hsn'])->firstWhere('hsn', $code);
    }

    private function returnOne(): CreditNote
    {
        $this->actingAs($this->user)->post(route('credit-notes.store', $this->invoice), [
            'credit_note_date' => now()->toDateString(),
            'reason' => 'sales_return',
            'amount' => 1, // ignored: the lines decide the amount
            'lines' => [$this->almirahLine()->id => 1],
        ])->assertSessionHasNoErrors();

        return CreditNote::latest('id')->firstOrFail();
    }

    public function test_a_line_based_return_credits_that_line_and_restores_stock(): void
    {
        $this->assertEqualsWithDelta(13.0, $this->almirah->fresh()->stockOnHand(), 0.001, '7 sold out of 20');

        $cn = $this->returnOne();

        // One almirah: 12,187.50 taxable, CGST and SGST each 1/7 of the line's.
        $line = $this->almirahLine();
        $this->assertEqualsWithDelta(12187.50, (float) $cn->taxable_value, 0.001);
        $this->assertEqualsWithDelta(round((float) $line->cgst_amount / 7, 2), (float) $cn->total_cgst, 0.001);
        $this->assertEqualsWithDelta(round((float) $line->sgst_amount / 7, 2), (float) $cn->total_sgst, 0.001);
        $this->assertEqualsWithDelta(
            round((float) $cn->taxable_value + (float) $cn->total_cgst + (float) $cn->total_sgst, 2),
            (float) $cn->amount, 0.001, 'the note is the sum of its lines'
        );
        $this->assertCount(1, $cn->items);
        $this->assertSame('94032010', $cn->items->first()->hsn_sac);

        // The almirah is back on the shelf, once, as a sales return.
        $this->assertEqualsWithDelta(14.0, $this->almirah->fresh()->stockOnHand(), 0.001);
        $this->assertSame(1, StockMovement::where('kind', 'sale_return')->count());

        // The invoice balance drops by exactly the note.
        $this->assertEqualsWithDelta((float) $this->invoice->grand_total - (float) $cn->amount, (float) $this->invoice->fresh()->balance, 0.001);
    }

    public function test_table_12_reduces_only_the_returned_hsn_and_its_quantity(): void
    {
        $before94 = $this->hsnRow('94032010');
        $before99 = $this->hsnRow('998719');

        $this->returnOne();

        $after94 = $this->hsnRow('94032010');
        $after99 = $this->hsnRow('998719');
        $this->assertEqualsWithDelta($before94['qty'] - 1, $after94['qty'], 0.001, '7 sold, 1 back: 6');
        $this->assertEqualsWithDelta($before94['taxable'] - 12187.50, $after94['taxable'], 0.001);
        $this->assertEquals($before99, $after99, 'the installation was not returned');
    }

    public function test_the_last_of_a_line_takes_the_exact_remainder(): void
    {
        $this->returnOne();
        $this->actingAs($this->user)->post(route('credit-notes.store', $this->invoice->fresh()), [
            'credit_note_date' => now()->toDateString(), 'reason' => 'sales_return',
            'lines' => [$this->almirahLine()->id => 6],
        ])->assertSessionHasNoErrors();

        $line = $this->almirahLine();
        $notes = CreditNote::all();
        $this->assertEqualsWithDelta((float) $line->amount, (float) $notes->sum('taxable_value'), 0.001);
        $this->assertEqualsWithDelta((float) $line->cgst_amount, (float) $notes->sum('total_cgst'), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->hsnRow('94032010')['qty'], 0.001);

        // Nothing of the almirah line is left to credit.
        $this->actingAs($this->user)->post(route('credit-notes.store', $this->invoice->fresh()), [
            'credit_note_date' => now()->toDateString(), 'reason' => 'sales_return',
            'lines' => [$line->id => 1],
        ])->assertSessionHasErrors('lines.' . $line->id);
    }

    public function test_more_than_was_billed_is_refused(): void
    {
        $line = $this->almirahLine();
        $this->actingAs($this->user)->post(route('credit-notes.store', $this->invoice), [
            'credit_note_date' => now()->toDateString(), 'reason' => 'sales_return',
            'lines' => [$line->id => 8],
        ])->assertSessionHasErrors('lines.' . $line->id);

        $this->assertSame(0, CreditNote::count());
        $this->assertSame(0, StockMovement::where('kind', 'sale_return')->count());
    }

    public function test_reversing_a_return_takes_the_stock_back_out(): void
    {
        $cn = $this->returnOne();
        $this->assertEqualsWithDelta(14.0, $this->almirah->fresh()->stockOnHand(), 0.001);

        $this->actingAs($this->user)->delete(route('credit-notes.destroy', $cn))->assertRedirect();

        $this->assertEqualsWithDelta(13.0, $this->almirah->fresh()->stockOnHand(), 0.001);
        $this->assertSame(0, StockMovement::where('kind', 'sale_return')->count());
        $this->assertDatabaseCount('credit_note_items', 0);
    }

    public function test_a_price_reduction_on_lines_moves_no_stock_and_keeps_the_quantity(): void
    {
        $line = $this->almirahLine();
        $this->actingAs($this->user)->post(route('credit-notes.store', $this->invoice), [
            'credit_note_date' => now()->toDateString(), 'reason' => 'rate_correction',
            'lines' => [$line->id => 1],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, StockMovement::where('kind', 'sale_return')->count());
        $this->assertEqualsWithDelta(7.0, $this->hsnRow('94032010')['qty'], 0.001, 'no goods came back');
    }

    public function test_an_amount_only_note_behaves_exactly_as_before(): void
    {
        $before94 = $this->hsnRow('94032010');
        $before99 = $this->hsnRow('998719');
        $expected = app(CreditNoteSplit::class)->for($this->invoice, 5000.0);

        $this->actingAs($this->user)->post(route('credit-notes.store', $this->invoice), [
            'credit_note_date' => now()->toDateString(), 'reason' => 'sales_return', 'amount' => 5000,
        ])->assertSessionHasNoErrors();

        $cn = CreditNote::latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(5000.0, (float) $cn->amount, 0.001);
        foreach ($expected as $k => $v) {
            $this->assertEqualsWithDelta($v, (float) $cn->{$k}, 0.001, $k);
        }
        $this->assertCount(0, $cn->items);
        $this->assertSame(0, StockMovement::where('kind', 'sale_return')->count(), 'no lines, no stock');

        // Table 12: both lines reduced pro rata, quantities untouched.
        $fraction = (float) $cn->taxable_value / (float) $this->invoice->subtotal;
        $after94 = $this->hsnRow('94032010');
        $after99 = $this->hsnRow('998719');
        $this->assertEqualsWithDelta($before94['qty'], $after94['qty'], 0.001);
        $this->assertEqualsWithDelta($before99['qty'], $after99['qty'], 0.001);
        $this->assertEqualsWithDelta(round($before94['taxable'] * (1 - $fraction), 2), $after94['taxable'], 0.02);
        $this->assertEqualsWithDelta(round($before99['taxable'] * (1 - $fraction), 2), $after99['taxable'], 0.02);
    }

    public function test_the_form_offers_the_lines_and_the_pdf_prints_the_rate(): void
    {
        $this->actingAs($this->user)->get(route('credit-notes.create', $this->invoice))
            ->assertOk()
            ->assertSee('Items returned or credited')
            ->assertSee('94032010');

        $cn = $this->returnOne();
        $html = view('credit-notes.pdf', [
            'creditNote' => $cn->load(['items', 'invoice.items', 'invoice.customer.state', 'invoice.company.state']),
            'amountInWords' => 'words', 'print' => true,
        ])->render();
        $text = preg_replace('/\s+/', ' ', strip_tags($html));
        $this->assertStringContainsString('Goods or services credited', $text);
        $this->assertMatchesRegularExpression('/94032010 1 NOS 12,187\.50 18% 2,193\.76 14,381\.26/u', $text);
        $this->assertStringContainsString('Rate of tax 18%', $text);
    }
}
