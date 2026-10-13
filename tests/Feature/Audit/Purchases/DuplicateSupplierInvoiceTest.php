<?php

namespace Tests\Feature\Audit\Purchases;

use App\Models\Company;
use App\Models\PurchaseBill;
use App\Models\State;
use App\Models\User;
use App\Services\Gstr3bBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Audit repro: one supplier invoice booked twice, so its input credit is
 * claimed twice in GSTR-3B. Uniqueness is (company, vendor_id, exact
 * bill_number), while the supplier is identified by GSTIN and the app's own
 * GSTR-2B matcher treats "DC/2026/0117" and "DC-2026-117" as one document.
 */
class DuplicateSupplierInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Company $company;
    private State $mh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-12 11:00', 'Asia/Kolkata'));
        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->owner = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id, 'state_id' => $this->mh->id, 'gstin' => '27ABCDE1234F1Z5', 'onboarded_at' => now(),
        ]);
        $this->owner->forceFill(['active_company_id' => $this->company->id])->save();
    }

    private function bill(int $vendorId, string $number)
    {
        return $this->actingAs($this->owner)->post(route('purchases.store'), [
            'vendor_id' => $vendorId, 'bill_number' => $number, 'bill_date' => '2026-10-05', 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Cooling fans', 'hsn_sac' => '84145910', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 10000, 'gst_rate' => 18]],
        ]);
    }

    private function octItc(): float
    {
        return (float) app(Gstr3bBuilder::class)->build($this->company, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'))['itc']['total'];
    }

    public function test_the_same_supplier_invoice_under_a_second_vendor_record_with_the_same_gstin_is_refused(): void
    {
        // The web vendor form has no GSTIN uniqueness (the importer does treat
        // the same GSTIN as the same vendor), so the supplier can exist twice.
        $this->actingAs($this->owner)->post(route('vendors.store'), ['name' => 'Deepcool India', 'gstin' => '27AAPFU0939F1ZV', 'state_id' => $this->mh->id])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('vendors.store'), ['name' => 'Deepcool India Pvt Ltd', 'gstin' => '27AAPFU0939F1ZV', 'state_id' => $this->mh->id])->assertSessionHasNoErrors();
        [$v1, $v2] = $this->company->vendors()->orderBy('id')->pluck('id')->all();

        $this->bill($v1, 'DC/2026/0117')->assertSessionHasNoErrors();
        $this->bill($v2, 'DC/2026/0117');

        $this->assertSame(1, PurchaseBill::count(), 'Supplier 27AAPFU0939F1ZV invoice DC/2026/0117 was booked twice');
        $this->assertEqualsWithDelta(1800.0, $this->octItc(), 0.001, 'ITC claimed twice on one supplier invoice');
    }

    public function test_the_same_supplier_invoice_typed_with_other_separators_is_refused(): void
    {
        $this->actingAs($this->owner)->post(route('vendors.store'), ['name' => 'Deepcool India', 'gstin' => '27AAPFU0939F1ZV', 'state_id' => $this->mh->id])->assertSessionHasNoErrors();
        $v = $this->company->vendors()->value('id');

        $this->bill($v, 'DC/2026/0117')->assertSessionHasNoErrors();
        $this->bill($v, 'DC-2026-117');   // the same document per Gstr2bMatcher::key()

        $this->assertSame(1, PurchaseBill::count(), '"DC/2026/0117" and "DC-2026-117" (one document to GSTR-2B matching) were both booked');
        $this->assertEqualsWithDelta(1800.0, $this->octItc(), 0.001, 'ITC claimed twice on one supplier invoice');
    }
}
