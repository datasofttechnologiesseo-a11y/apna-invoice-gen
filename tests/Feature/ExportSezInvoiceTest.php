<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use App\Services\Gstr1Builder;
use App\Services\Gstr3bBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Zero-rated supplies (s.16 IGST Act): exports and supplies to SEZ, with or
 * without payment of IGST (LUT), and how they reach GSTR-1 (6A / 6B / 9B)
 * and GSTR-3B 3.1(b).
 */
class ExportSezInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $abroad;
    private Customer $sez;
    private Customer $local;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-28 11:00:00'));
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);
        $foreign = State::factory()->create(['gst_code' => '96', 'name' => 'Foreign Country']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id, 'state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5',
            'invoice_prefix' => 'INV', 'invoice_counter' => 0, 'lut_number' => 'AD270326012345X',
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $make = fn (State $s, ?string $gstin, string $name, string $country = 'India') => Customer::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'state_id' => $s->id,
            'gstin' => $gstin, 'name' => $name, 'country' => $country,
        ]);
        $this->abroad = $make($foreign, null, 'Acme Inc', 'United States');
        $this->sez = $make($ka, '29KLMNO1234P1Z9', 'Bengaluru SEZ Unit');
        $this->local = $make($mh, null, 'Walk-in');
    }

    private function issue(Customer $c, string $type, float $rate, float $gst, array $extra = []): Invoice
    {
        $this->actingAs($this->user)->post(route('invoices.store'), array_merge([
            'customer_id' => $c->id, 'invoice_date' => '2026-09-10', 'supply_type' => $type,
            'items' => [['description' => 'Software services', 'hsn_sac' => '998314', 'quantity' => 1, 'rate' => $rate, 'gst_rate' => $gst]],
        ], $extra))->assertSessionHasNoErrors();
        $inv = Invoice::latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertSessionHasNoErrors();

        return $inv->fresh();
    }

    public function test_an_export_under_lut_charges_no_tax_and_says_so(): void
    {
        $inv = $this->issue($this->abroad, 'export_lut', 100000, 18, [
            'export_port_code' => 'innsa1', 'shipping_bill_number' => '1234567', 'shipping_bill_date' => '2026-09-12',
        ]);

        $this->assertTrue($inv->is_interstate);
        $this->assertEqualsWithDelta(0.0, (float) $inv->total_tax, 0.001);
        $this->assertEqualsWithDelta(100000.0, (float) $inv->grand_total, 0.001);
        $this->assertSame('INNSA1', $inv->export_port_code);
        $this->assertEqualsWithDelta(18.0, (float) $inv->items->first()->gst_rate, 0.001, 'the rate stays on the line');

        $this->actingAs($this->user)->get(route('invoices.show', $inv))->assertOk()
            ->assertSee('SUPPLY MEANT FOR EXPORT UNDER BOND OR LETTER OF UNDERTAKING WITHOUT PAYMENT OF INTEGRATED TAX')
            ->assertSee('AD270326012345X')
            ->assertSee('INNSA1');
        $this->actingAs($this->user)->get(route('invoices.pdf', $inv))->assertOk();
    }

    public function test_an_export_with_igst_and_sez_supplies_are_always_igst(): void
    {
        $exp = $this->issue($this->abroad, 'export_igst', 50000, 18);
        $this->assertEqualsWithDelta(9000.0, (float) $exp->total_igst, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $exp->total_cgst, 0.001);

        // The SEZ unit is in Karnataka, but even an SEZ in Maharashtra is IGST.
        $sez = $this->issue($this->sez, 'sez_igst', 10000, 5);
        $this->assertEqualsWithDelta(500.0, (float) $sez->total_igst, 0.001);

        $this->sez->update(['state_id' => $this->company->state_id, 'gstin' => '27FGHIJ5678K1Z3']);
        $sameState = $this->issue($this->sez, 'sez_igst', 10000, 18);
        $this->assertEqualsWithDelta(1800.0, (float) $sameState->total_igst, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $sameState->total_cgst, 0.001);
    }

    public function test_wrong_combinations_are_refused(): void
    {
        $base = ['invoice_date' => '2026-09-10', 'items' => [['description' => 'X', 'hsn_sac' => '998314', 'quantity' => 1, 'rate' => 100, 'gst_rate' => 18]]];

        $this->actingAs($this->user)->post(route('invoices.store'), $base + ['customer_id' => $this->local->id, 'supply_type' => 'export_lut'])
            ->assertSessionHasErrors('supply_type');
        $this->actingAs($this->user)->post(route('invoices.store'), $base + ['customer_id' => $this->local->id, 'supply_type' => 'sez_lut'])
            ->assertSessionHasErrors('supply_type');
        $this->actingAs($this->user)->post(route('invoices.store'), $base + ['customer_id' => $this->abroad->id, 'supply_type' => 'export_igst', 'reverse_charge' => 1])
            ->assertSessionHasErrors('reverse_charge');
        $this->actingAs($this->user)->post(route('invoices.store'), $base + ['customer_id' => $this->abroad->id, 'supply_type' => 'nonsense'])
            ->assertSessionHasErrors('supply_type');
        $this->assertSame(0, Invoice::count());
    }

    public function test_a_regular_invoice_is_untouched(): void
    {
        $inv = $this->issue($this->local, 'regular', 1000, 18);
        $this->assertSame('regular', $inv->supply_type);
        $this->assertEqualsWithDelta(90.0, (float) $inv->total_cgst, 0.001);
        $this->assertFalse($inv->isZeroRated());
    }

    public function test_returns_report_zero_rated_supplies_in_their_own_tables(): void
    {
        $lut = $this->issue($this->abroad, 'export_lut', 100000, 18, ['export_port_code' => 'INNSA1', 'shipping_bill_number' => 'SB1', 'shipping_bill_date' => '2026-09-12']);
        $this->issue($this->abroad, 'export_igst', 50000, 18);
        $this->issue($this->sez, 'sez_lut', 20000, 18);
        $this->issue($this->sez, 'sez_igst', 10000, 5);
        $this->issue($this->local, 'regular', 1000, 18);

        // Credit note on the LUT export: 10%.
        $this->actingAs($this->user)->post(route('credit-notes.store', $lut), [
            'credit_note_date' => '2026-09-20', 'amount' => 10000, 'reason' => 'rate_correction',
        ])->assertSessionHasNoErrors();

        $g = app(Gstr1Builder::class)->build($this->company, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $exp = collect($g['exp']);
        $this->assertCount(2, $exp);
        $this->assertSame(['WOPAY', 'WPAY'], $exp->pluck('exp_typ')->sort()->values()->all());
        $wop = $exp->firstWhere('exp_typ', 'WOPAY');
        $this->assertSame('INNSA1', $wop['port_code']);
        $this->assertEqualsWithDelta(0.0, $wop['igst'], 0.001);
        $this->assertEqualsWithDelta(9000.0, $exp->firstWhere('exp_typ', 'WPAY')['igst'], 0.001);

        $sez = collect($g['b2b'])->whereIn('inv_typ', ['SEWP', 'SEWOP']);
        $this->assertSame(['SEWOP', 'SEWP'], $sez->pluck('inv_typ')->sort()->values()->all());
        $this->assertSame(2, $g['tables']['exp']['records']);
        $this->assertSame(2, $g['tables']['sez']['records']);
        $this->assertSame(0, $g['tables']['b2b']['records'], 'SEZ is 6B, not 4A');

        $this->assertSame('EXPWOP', $g['cdnur'][0]['ur_type']);
        $this->assertEqualsWithDelta(10000.0, $g['cdnur'][0]['taxable'], 0.001);

        // 3.1(b): 1,00,000 + 50,000 + 20,000 + 10,000 - 10,000 (credit note)
        $d = app(Gstr3bBuilder::class)->build($this->company, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
        $this->assertEqualsWithDelta(170000.0, $d['outward_zero']['taxable'], 0.001);
        $this->assertEqualsWithDelta(9500.0, $d['outward_zero']['igst'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $d['outward']['taxable'], 0.001, '3.1(a) has only the regular sale');
        $this->assertEqualsWithDelta(9500.0, $d['netCash']['igst'], 0.001, 'IGST on exports is paid (and refunded later)');

        $json = json_decode($this->actingAs($this->user)->get(route('finance.gstr1.export.json', ['month' => '2026-09']))->streamedContent(), true);
        $expJson = collect($json['exp'])->keyBy('exp_typ');
        $this->assertSame('INNSA1', $expJson['WOPAY']['inv'][0]['sbpcode']);
        $this->assertSame('12-09-2026', $expJson['WOPAY']['inv'][0]['sbdt']);
        $this->assertEquals(0, $expJson['WOPAY']['inv'][0]['itms'][0]['iamt']);
        $this->assertSame(['SEWOP', 'SEWP'], collect($json['b2b'][0]['inv'])->pluck('inv_typ')->sort()->values()->all());
        $this->assertSame('EXPWOP', $json['cdnur'][0]['typ']);
        $this->assertArrayNotHasKey('pos', $json['cdnur'][0], 'an export credit note has no place of supply');

        $this->actingAs($this->user)->get(route('finance.gstr3b', ['month' => '2026-09']))->assertOk()
            ->assertSeeInOrder(['(b)', '1,70,000', '9,500']);
        $this->actingAs($this->user)->get(route('finance.gstr1', ['month' => '2026-09']))->assertOk()->assertSee('Exports (6A)');
    }

    public function test_the_api_bills_an_export_the_same_way(): void
    {
        Sanctum::actingAs($this->user);
        $res = $this->postJson('/api/invoices', [
            'customer_id' => $this->abroad->id, 'invoice_date' => '2026-09-10', 'supply_type' => 'export_lut',
            'items' => [['description' => 'Software services', 'hsn_sac' => '998314', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18]],
        ])->assertCreated();
        $this->assertSame('export_lut', $res->json('data.supply_type') ?? $res->json('supply_type'));
        $inv = Invoice::latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $inv->total_tax, 0.001);

        $this->postJson('/api/invoices', [
            'customer_id' => $this->local->id, 'invoice_date' => '2026-09-10', 'supply_type' => 'export_lut',
            'items' => [['description' => 'X', 'hsn_sac' => '998314', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18]],
        ])->assertStatus(422)->assertJsonValidationErrors('supply_type');
    }
}
