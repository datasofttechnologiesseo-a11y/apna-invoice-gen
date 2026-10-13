<?php

namespace Tests\Feature\Audit\Gst;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PurchaseBill;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Small books for the GST audit repros, written through the real HTTP
 * routes (same helpers as Fintech\Reconciliation\SeedsFy2026Books).
 * Supplier: Pune, Maharashtra (27), regular GST.
 */
trait BuildsGstBooks
{
    protected User $user;
    protected Company $company;
    /** @var array<string, State> */
    protected array $st = [];
    /** @var array<string, Customer> */
    protected array $cust = [];
    /** @var array<string, Vendor> */
    protected array $vend = [];
    /** @var array<string, Invoice> */
    protected array $inv = [];
    /** @var array<string, CreditNote> */
    protected array $cn = [];

    protected function setUpBooks(string $at = '2026-09-01 10:00'): void
    {
        $this->at($at);
        $this->st['MH'] = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra', 'code' => 'MH']);
        $this->st['KA'] = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka', 'code' => 'KA']);
        $this->st['XX'] = State::factory()->create(['gst_code' => '96', 'name' => 'Foreign Country', 'code' => 'OT']);

        $this->user = User::factory()->create(['name' => 'Audit Owner']);
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id, 'name' => 'Audit Traders', 'state_id' => $this->st['MH']->id,
            'gstin' => '27AAKCS1234M1Z5', 'composition_dealer' => false,
            'invoice_prefix' => 'AUD', 'invoice_number_format' => 'AUD/{FY_SHORT}/{N}', 'invoice_number_padding' => 4,
            'invoice_counter' => 0, 'lut_number' => 'AD270326012345X',
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    protected function customer(string $key, string $state, ?string $gstin, string $name, string $country = 'India'): Customer
    {
        return $this->cust[$key] = Customer::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'state_id' => $this->st[$state]->id,
            'gstin' => $gstin, 'name' => $name, 'country' => $country, 'phone' => null, 'email' => null,
        ]);
    }

    protected function vendor(string $key, string $state, ?string $gstin, string $name): Vendor
    {
        return $this->vend[$key] = $this->company->vendors()->create([
            'user_id' => $this->user->id, 'name' => $name, 'state_id' => $this->st[$state]->id, 'gstin' => $gstin,
        ]);
    }

    protected function at(string $ist): void
    {
        $this->travelTo(Carbon::parse($ist, 'Asia/Kolkata'));
    }

    protected function today(): string
    {
        return now('Asia/Kolkata')->toDateString();
    }

    protected function l(string $desc, string $hsn, float $qty, float $rate, float $gst, string $unit = 'NOS'): array
    {
        return ['description' => $desc, 'hsn_sac' => $hsn, 'quantity' => $qty, 'unit' => $unit, 'rate' => $rate, 'gst_rate' => $gst];
    }

    protected function issue(string $key, string $cust, string $when, array $items, array $extra = []): Invoice
    {
        $this->at($when);
        $this->actingAs($this->user)->post(route('invoices.store'), array_merge([
            'customer_id' => $this->cust[$cust]->id, 'invoice_date' => $this->today(),
            'due_date' => Carbon::parse($this->today())->addDays(30)->toDateString(), 'items' => $items,
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();
        $inv = Invoice::where('company_id', $this->company->id)->latest('id')->firstOrFail();
        $this->actingAs($this->user)->post(route('invoices.finalize', $inv))->assertSessionHasNoErrors()->assertRedirect();
        $inv = $inv->fresh(['items']);
        $this->assertNotSame('draft', $inv->status, "{$key} was not issued");

        return $this->inv[$key] = $inv;
    }

    /** A credit note issued on $issuedOn (the clock) but dated $dated. */
    protected function creditNote(string $key, string $invKey, string $issuedOn, string $dated, float $amount, string $reason = 'post_sale_discount'): CreditNote
    {
        $this->at($issuedOn);
        $before = CreditNote::count();
        $this->actingAs($this->user)->post(route('credit-notes.store', $this->inv[$invKey]), [
            'credit_note_date' => $dated, 'amount' => $amount, 'reason' => $reason,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($before + 1, CreditNote::count(), "{$key} was not issued");

        return $this->cn[$key] = CreditNote::latest('id')->firstOrFail();
    }

    protected function bill(string $vendor, string $when, string $number, array $items, array $extra = []): PurchaseBill
    {
        $this->at($when);
        $this->actingAs($this->user)->post(route('purchases.store'), array_merge([
            'vendor_id' => $this->vend[$vendor]->id, 'bill_number' => $number, 'bill_date' => $this->today(),
            'itc_eligible' => 1, 'reverse_charge' => 0, 'payment_method' => 'neft', 'items' => $items,
            'expense_category' => 'purchases',
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();

        return PurchaseBill::where('company_id', $this->company->id)->latest('id')->firstOrFail();
    }

    protected function gstr1Page(string $ym, bool $quarterly = false): array
    {
        $res = $this->actingAs($this->user)->get(route('finance.gstr1', array_filter(['month' => $ym, 'frequency' => $quarterly ? 'quarterly' : null])));
        $res->assertOk();

        return $res->original->getData();
    }

    protected function gstr1Json(string $ym, bool $quarterly = false): array
    {
        $res = $this->actingAs($this->user)->get(route('finance.gstr1.export.json', array_filter(['month' => $ym, 'frequency' => $quarterly ? 'quarterly' : null])));
        $res->assertOk();

        return json_decode($res->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function gstr3bPage(string $ym, bool $quarterly = false): array
    {
        $res = $this->actingAs($this->user)->get(route('finance.gstr3b', array_filter(['month' => $ym, 'frequency' => $quarterly ? 'quarterly' : null])));
        $res->assertOk();

        return $res->original->getData();
    }

    protected function uploadJson2b(string $json, string $name = 'returns_R2B.json'): void
    {
        $path = tempnam(sys_get_temp_dir(), '2b') . '.json';
        file_put_contents($path, $json);
        $this->actingAs($this->user)->post(route('finance.gstr2b.upload'), [
            'file' => new UploadedFile($path, $name, 'application/json', null, true),
        ])->assertSessionHasNoErrors()->assertRedirect();
    }
}
