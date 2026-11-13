<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Expense;
use App\Models\Gstr2bEntry;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The mobile API for vendors, purchase invoices (and their payments),
 * the GSTR-2B match and item stock. These go through the same services as
 * the web pages, so the assertions here are about the API surface: shapes,
 * status codes, ownership, the books lock answering 422, and team roles
 * following the web route of the same name.
 */
class PurchasesApiTest extends TestCase
{
    use RefreshDatabase;

    private State $mh;

    private State $ka;

    private User $owner;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-15 10:00'));
        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);
        [$this->owner, $this->company] = $this->business('27ABCDE1234F1Z5');
    }

    private function business(?string $gstin = null, ?User $owner = null): array
    {
        $owner ??= User::factory()->create();
        $company = Company::factory()->create([
            'user_id' => $owner->id, 'state_id' => $this->mh->id, 'gstin' => $gstin, 'onboarded_at' => now(),
        ]);
        $owner->forceFill(['active_company_id' => $company->id])->save();

        return [$owner->fresh(), $company];
    }

    private function vendor(string $name = 'Mumbai Metals', ?string $gstin = '27PQRST1234U1Z1', ?Company $company = null): Vendor
    {
        $company ??= $this->company;

        return $company->vendors()->create(['user_id' => $company->user_id, 'name' => $name, 'gstin' => $gstin, 'state_id' => $this->mh->id]);
    }

    private function product(array $attrs = []): Product
    {
        return Product::create($attrs + [
            'user_id' => $this->owner->id, 'company_id' => $this->company->id, 'name' => 'Steel rod', 'kind' => 'goods',
            'hsn_sac' => '72085100', 'unit' => 'NOS', 'rate' => 150, 'gst_rate' => 18, 'track_stock' => true, 'opening_stock' => 10,
        ]);
    }

    private function billPayload(Vendor $vendor, array $extra = []): array
    {
        return $extra + [
            'vendor_id' => $vendor->id, 'bill_number' => 'INV/001', 'bill_date' => '2026-10-05', 'itc_eligible' => true,
            'expense_category' => 'office_supplies',
            'items' => [['description' => 'Goods', 'hsn_sac' => '72085100', 'quantity' => 2, 'unit' => 'NOS', 'rate' => 5000, 'gst_rate' => 18]],
        ];
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        Company::factory()->create(['user_id' => $user->id, 'state_id' => $this->mh->id]);
        CompanyMember::create([
            'company_id' => $this->company->id, 'user_id' => $user->id, 'email' => $user->email, 'role' => $role,
            'token' => Str::random(48), 'invited_by' => $this->owner->id, 'accepted_at' => now(),
        ]);
        $user->forceFill(['active_company_id' => $this->company->id])->save();

        return $user->fresh();
    }

    // ---- Vendors -----------------------------------------------------------

    public function test_vendor_crud_and_search(): void
    {
        Sanctum::actingAs($this->owner);

        // State left out: the GSTIN says where they are.
        $res = $this->postJson('/api/vendors', ['name' => 'Mysore Motors', 'gstin' => '27aaact2727q1zw'])->assertCreated();
        $res->assertJsonPath('data.gstin', '27AAACT2727Q1ZW')
            ->assertJsonPath('data.state_id', $this->mh->id)
            ->assertJsonPath('data.state_name', 'Maharashtra')
            ->assertJsonPath('data.country', 'India');
        $id = $res->json('data.id');
        $this->assertSame($this->company->id, Vendor::find($id)->company_id);

        $this->vendor();
        $this->getJson('/api/vendors')->assertOk()->assertJsonCount(2, 'data')->assertJsonStructure(['data' => [['id', 'name', 'gstin', 'payable', 'purchase_bills_count']], 'meta', 'links']);
        $this->getJson('/api/vendors?search=mysore')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Mysore Motors');

        $this->getJson("/api/vendors/{$id}")->assertOk()->assertJsonPath('data.payable', 0)->assertJsonPath('data.purchase_bills_count', 0);
        $this->putJson("/api/vendors/{$id}", ['name' => 'Mysore Motors Pvt Ltd', 'state_id' => $this->mh->id])->assertOk()
            ->assertJsonPath('data.name', 'Mysore Motors Pvt Ltd');

        $this->postJson('/api/vendors', [])->assertStatus(422)->assertJsonValidationErrors('name');

        $this->deleteJson("/api/vendors/{$id}")->assertOk();
        $this->assertNull(Vendor::find($id));
    }

    public function test_a_vendor_with_purchase_invoices_cannot_be_deleted(): void
    {
        $vendor = $this->vendor();
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/purchases', $this->billPayload($vendor))->assertCreated();

        $this->deleteJson("/api/vendors/{$vendor->id}")->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'cannot be deleted'));
        $this->assertNotNull($vendor->fresh());
    }

    public function test_another_users_vendor_is_out_of_reach(): void
    {
        $vendor = $this->vendor();
        [$intruder] = $this->business();
        Sanctum::actingAs($intruder);

        $this->getJson("/api/vendors/{$vendor->id}")->assertForbidden();
        $this->putJson("/api/vendors/{$vendor->id}", ['name' => 'Hijacked'])->assertForbidden();
        $this->deleteJson("/api/vendors/{$vendor->id}")->assertForbidden();
        $this->getJson('/api/vendors')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame('Mumbai Metals', $vendor->fresh()->name);
    }

    // ---- Purchase invoices -------------------------------------------------

    public function test_a_purchase_invoice_round_trip(): void
    {
        $vendor = $this->vendor();
        $item = $this->product();
        Sanctum::actingAs($this->owner);

        $payload = $this->billPayload($vendor, [
            'paid_now' => 5000, 'paid_on' => '2026-10-06', 'payment_method' => 'upi',
            'items' => [['product_id' => $item->id, 'description' => 'Steel rod', 'hsn_sac' => '72085100', 'quantity' => 2, 'unit' => 'NOS', 'rate' => 5000, 'gst_rate' => 18]],
        ]);
        $res = $this->postJson('/api/purchases', $payload)->assertCreated();
        $res->assertJsonPath('data.subtotal', 10000)
            ->assertJsonPath('data.total_tax', 1800)
            ->assertJsonPath('data.grand_total', 11800)
            ->assertJsonPath('data.paid_amount', 5000)
            ->assertJsonPath('data.balance', 6800)
            ->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.vendor.name', 'Mumbai Metals')
            ->assertJsonPath('data.items.0.product_id', $item->id)
            ->assertJsonPath('data.payments.0.method', 'upi')
            ->assertJsonStructure(['data' => ['credit_notes'], 'message']);
        $id = $res->json('data.id');
        $bill = PurchaseBill::find($id);

        // Same side effects as the web form: an expense row and stock in.
        $this->assertNotNull(Expense::where('purchase_bill_id', $id)->first());
        $this->assertEqualsWithDelta(12.0, $item->fresh()->stockOnHand(), 0.001);

        // List: filters and the payables summary.
        $this->getJson('/api/purchases?status=unpaid')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.summary.payable', 6800)
            ->assertJsonStructure(['meta' => ['summary' => ['payable', 'open_count', 'overdue', 'due_7_days', 'itc_this_month'], 'categories', 'methods', 'current_page', 'total']]);
        $this->getJson('/api/purchases?status=paid')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/purchases?search=metals')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/purchases?vendor={$vendor->id}")->assertOk()->assertJsonCount(1, 'data');

        $this->getJson("/api/purchases/{$id}")->assertOk()->assertJsonPath('data.bill_number', 'INV/001');

        // Edit: quantity 3, stock follows.
        $payload['items'][0]['quantity'] = 3;
        unset($payload['paid_now']);
        $this->putJson("/api/purchases/{$id}", $payload)->assertOk()->assertJsonPath('data.grand_total', 17700)->assertJsonPath('data.balance', 12700);
        $this->assertEqualsWithDelta(13.0, $item->fresh()->stockOnHand(), 0.001);

        // Payments.
        $pay = $this->postJson("/api/purchases/{$id}/payments", ['amount' => 2700, 'method' => 'neft', 'paid_on' => '2026-10-10', 'reference_number' => 'UTR123'])
            ->assertCreated()->assertJsonPath('data.amount', 2700)->assertJsonPath('bill.balance', 10000);
        $this->postJson("/api/purchases/{$id}/payments", ['amount' => 100, 'method' => 'neft', 'paid_on' => '2026-10-10', 'reference_number' => 'utr123'])
            ->assertStatus(422)->assertJsonValidationErrors('reference_number');
        $this->postJson("/api/purchases/{$id}/payments", ['amount' => 20000, 'method' => 'neft', 'paid_on' => '2026-10-10'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->deleteJson("/api/purchases/{$id}/payments/{$pay->json('data.id')}")->assertOk()->assertJsonPath('bill.balance', 12700);

        // Delete: expense and stock go with it.
        $this->deleteJson("/api/purchases/{$id}")->assertOk();
        $this->assertNull($bill->fresh());
        $this->assertNull(Expense::where('purchase_bill_id', $id)->first());
        $this->assertEqualsWithDelta(10.0, $item->fresh()->stockOnHand(), 0.001);
    }

    public function test_purchase_validation_and_duplicate_supplier_invoice(): void
    {
        $vendor = $this->vendor();
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/purchases', [])->assertStatus(422)->assertJsonValidationErrors(['vendor_id', 'bill_number', 'bill_date', 'items']);
        $this->postJson('/api/purchases', $this->billPayload($vendor))->assertCreated();

        // Same GSTIN on a second vendor record, number written differently.
        $twin = $this->vendor('Mumbai Metals (2)', '27PQRST1234U1Z1');
        $this->postJson('/api/purchases', $this->billPayload($twin, ['bill_number' => 'INV-1']))
            ->assertStatus(422)->assertJsonValidationErrors('bill_number');
    }

    public function test_the_books_lock_answers_422(): void
    {
        $vendor = $this->vendor();
        Sanctum::actingAs($this->owner);
        $id = $this->postJson('/api/purchases', $this->billPayload($vendor, ['bill_date' => '2026-09-05']))->assertCreated()->json('data.id');
        $this->company->forceFill(['books_locked_until' => '2026-09-30'])->save();

        $this->deleteJson("/api/purchases/{$id}")->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Books are locked'));
        $this->postJson("/api/purchases/{$id}/payments", ['amount' => 100, 'method' => 'cash', 'paid_on' => '2026-09-20'])
            ->assertStatus(422)->assertJsonValidationErrors('paid_on');
        $this->putJson("/api/purchases/{$id}", $this->billPayload($vendor, ['bill_date' => '2026-09-05']))->assertStatus(422);
        $this->assertNotNull(PurchaseBill::find($id));

        // A bill dated in the locked month is booked today instead.
        $late = $this->postJson('/api/purchases', $this->billPayload($vendor, ['bill_number' => 'INV/002', 'bill_date' => '2026-09-10']))->assertCreated();
        $late->assertJsonPath('data.booked_on', '2026-10-15');
        $this->assertStringContainsString('locked period', $late->json('message'));
    }

    public function test_another_users_purchase_invoice_is_out_of_reach(): void
    {
        $vendor = $this->vendor();
        Sanctum::actingAs($this->owner);
        $id = $this->postJson('/api/purchases', $this->billPayload($vendor))->json('data.id');
        $paymentId = $this->postJson("/api/purchases/{$id}/payments", ['amount' => 100, 'method' => 'cash', 'paid_on' => '2026-10-10'])->json('data.id');

        [$intruder] = $this->business();
        Sanctum::actingAs($intruder);
        $this->getJson("/api/purchases/{$id}")->assertForbidden();
        $this->putJson("/api/purchases/{$id}", $this->billPayload($vendor))->assertForbidden();
        $this->deleteJson("/api/purchases/{$id}")->assertForbidden();
        $this->postJson("/api/purchases/{$id}/payments", ['amount' => 1, 'method' => 'cash', 'paid_on' => '2026-10-10'])->assertForbidden();
        $this->deleteJson("/api/purchases/{$id}/payments/{$paymentId}")->assertForbidden();
        $this->getJson('/api/purchases')->assertOk()->assertJsonCount(0, 'data');

        $this->assertNotNull(PurchaseBill::find($id));
        $this->assertEqualsWithDelta(100.0, (float) PurchaseBill::find($id)->paid_amount, 0.001);
    }

    public function test_the_new_write_routes_refuse_an_anonymous_caller(): void
    {
        $vendor = $this->vendor();
        $item = $this->product();

        foreach ([
            ['POST', '/api/vendors'], ['PUT', "/api/vendors/{$vendor->id}"], ['DELETE', "/api/vendors/{$vendor->id}"],
            ['POST', '/api/purchases'], ['PUT', '/api/purchases/1'], ['DELETE', '/api/purchases/1'],
            ['POST', '/api/purchases/1/payments'], ['DELETE', '/api/purchases/1/payments/1'],
            ['POST', '/api/finance/gstr2b'], ['GET', '/api/finance/gstr2b'], ['GET', '/api/finance/gstr2b/export'],
            ['GET', "/api/products/{$item->id}/stock"], ['POST', "/api/products/{$item->id}/stock"], ['POST', "/api/products/{$item->id}/restore"],
        ] as [$verb, $uri]) {
            $this->assertSame(401, $this->json($verb, $uri)->getStatusCode(), "{$verb} {$uri}");
        }
    }

    // ---- GSTR-2B -----------------------------------------------------------

    private function portalJson(string $gstin = '27ABCDE1234F1Z5'): UploadedFile
    {
        $item = fn ($tx, $c, $s) => ['num' => 1, 'rt' => 18, 'txval' => $tx, 'igst' => 0, 'cgst' => $c, 'sgst' => $s, 'cess' => 0];
        $json = json_encode(['chksum' => 'x', 'data' => [
            'gstin' => $gstin, 'rtnprd' => '092026', 'version' => '1.0', 'gendt' => '14-10-2026',
            'docdata' => ['b2b' => [
                ['ctin' => '27PQRST1234U1Z1', 'trdnm' => 'Mumbai Metals', 'inv' => [
                    ['inum' => 'INV-1', 'typ' => 'R', 'dt' => '05-09-2026', 'val' => 11800, 'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y', 'items' => [$item(10000, 900, 900)]],
                ]],
                ['ctin' => '27KLMNO9876P1Z3', 'trdnm' => 'Kolhapur Kraft', 'inv' => [
                    ['inum' => 'K-9', 'typ' => 'R', 'dt' => '12-09-2026', 'val' => 4720, 'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y', 'items' => [$item(4000, 360, 360)]],
                ]],
            ]],
        ]]);
        $path = tempnam(sys_get_temp_dir(), '2b').'.json';
        file_put_contents($path, $json);

        return new UploadedFile($path, 'returns_092026_R2B_27ABCDE1234F1Z5.json', 'application/json', null, true);
    }

    public function test_gstr2b_upload_match_and_export(): void
    {
        $vendor = $this->vendor();
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/purchases', $this->billPayload($vendor, [
            'bill_date' => '2026-09-05',
            'items' => [['description' => 'Goods', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 10000, 'gst_rate' => 18]],
        ]))->assertCreated();

        $res = $this->post('/api/finance/gstr2b', ['file' => $this->portalJson()], ['Accept' => 'application/json'])->assertCreated();
        $res->assertJsonPath('period', '2026-09')
            ->assertJsonPath('documents', 2)
            ->assertJsonPath('summary.counts.matched', 1)
            ->assertJsonPath('summary.counts.missing', 1)
            ->assertJsonPath('buckets.matched.0.bill.bill_number', 'INV/001')
            ->assertJsonPath('buckets.matched.0.entry.document_number', 'INV-1')
            ->assertJsonPath('buckets.missing.0.entry.supplier_name', 'Kolhapur Kraft')
            ->assertJsonPath('uploaded_periods', ['2026-09']);
        $this->assertSame(2, Gstr2bEntry::count());

        $this->getJson('/api/finance/gstr2b?month=2026-09')->assertOk()
            ->assertJsonPath('period_label', 'September 2026')
            ->assertJsonStructure(['summary' => ['uploaded', 'documents', 'itc_in_2b', 'counts', 'tax', 'no_itc'], 'buckets' => ['matched', 'mismatch', 'missing', 'not_in_2b', 'no_gstin', 'notes', 'no_itc'], 'labels']);
        // Default month is last month.
        $this->getJson('/api/finance/gstr2b')->assertOk()->assertJsonPath('period', '2026-09');

        $csv = $this->get('/api/finance/gstr2b/export?month=2026-09')->assertOk()->streamedContent();
        $this->assertStringContainsString('In 2B, missing in books', $csv);
        $this->assertStringContainsString('K-9', $csv);
    }

    public function test_gstr2b_for_another_gstin_or_no_file_is_refused(): void
    {
        Sanctum::actingAs($this->owner);

        $this->post('/api/finance/gstr2b', ['file' => $this->portalJson('29AAAAA1111A1Z1')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');
        $this->postJson('/api/finance/gstr2b', [])->assertStatus(422)->assertJsonValidationErrors('file');
        $this->assertSame(0, Gstr2bEntry::count());
    }

    // ---- Stock -------------------------------------------------------------

    public function test_stock_ledger_adjustments_and_count(): void
    {
        $item = $this->product();
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/products/{$item->id}/stock", ['direction' => 'in', 'quantity' => 5, 'moved_on' => '2026-10-01', 'reason' => 'Found in godown'])
            ->assertCreated()->assertJsonPath('on_hand', 15)->assertJsonPath('movement.kind', 'adjustment')->assertJsonPath('changed', true);
        $this->postJson("/api/products/{$item->id}/stock", ['direction' => 'out', 'quantity' => 2, 'moved_on' => '2026-10-02', 'reason' => 'Damaged'])
            ->assertCreated()->assertJsonPath('on_hand', 13);
        // Shelf count: books are set to the count.
        $this->postJson("/api/products/{$item->id}/stock", ['direction' => 'count', 'quantity' => 11, 'moved_on' => '2026-10-03', 'reason' => 'Stock take'])
            ->assertCreated()->assertJsonPath('on_hand', 11)->assertJsonPath('movement.quantity', -2);
        $this->postJson("/api/products/{$item->id}/stock", ['direction' => 'count', 'quantity' => 11, 'moved_on' => '2026-10-04', 'reason' => 'Stock take'])
            ->assertOk()->assertJsonPath('changed', false)->assertJsonPath('movement', null);

        $this->postJson("/api/products/{$item->id}/stock", ['direction' => 'out', 'quantity' => 0, 'moved_on' => '2026-10-04', 'reason' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('quantity');

        $ledger = $this->getJson("/api/products/{$item->id}/stock")->assertOk();
        $ledger->assertJsonPath('opening_stock', 10)
            ->assertJsonPath('on_hand', 11)
            ->assertJsonCount(3, 'movements')
            ->assertJsonPath('movements.0.balance', 11) // latest first
            ->assertJsonPath('movements.2.balance', 15)
            ->assertJsonPath('product.id', $item->id);

        $this->company->forceFill(['books_locked_until' => '2026-09-30'])->save();
        $this->postJson("/api/products/{$item->id}/stock", ['direction' => 'in', 'quantity' => 1, 'moved_on' => '2026-09-15', 'reason' => 'Late'])
            ->assertStatus(422)->assertJsonValidationErrors('moved_on');
    }

    public function test_an_item_that_does_not_track_stock_cannot_be_adjusted(): void
    {
        $item = $this->product(['track_stock' => false]);
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/products/{$item->id}/stock", ['direction' => 'in', 'quantity' => 1, 'moved_on' => '2026-10-01', 'reason' => 'x'])
            ->assertStatus(422);
        $this->assertSame(0, $item->stockMovements()->count());
    }

    public function test_restore_and_stock_are_owner_scoped(): void
    {
        $item = $this->product(['is_active' => false]);
        [$intruder] = $this->business();

        Sanctum::actingAs($intruder);
        $this->postJson("/api/products/{$item->id}/restore")->assertForbidden();
        $this->getJson("/api/products/{$item->id}/stock")->assertForbidden();
        $this->postJson("/api/products/{$item->id}/stock", ['direction' => 'in', 'quantity' => 1, 'moved_on' => '2026-10-01', 'reason' => 'x'])->assertForbidden();
        $this->assertFalse((bool) $item->fresh()->is_active);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/products/{$item->id}/restore")->assertOk()->assertJsonPath('data.is_active', true);
        $this->assertTrue((bool) $item->fresh()->is_active);
    }

    // ---- Team roles --------------------------------------------------------

    public function test_billing_staff_cannot_reach_purchases_vendors_gstr2b_or_stock(): void
    {
        $vendor = $this->vendor();
        $item = $this->product(['is_active' => false]);
        $staff = $this->member('staff');

        foreach ([
            ['GET', '/api/vendors'], ['POST', '/api/vendors'], ['GET', "/api/vendors/{$vendor->id}"], ['DELETE', "/api/vendors/{$vendor->id}"],
            ['GET', '/api/purchases'], ['POST', '/api/purchases'],
            ['GET', '/api/finance/gstr2b'], ['POST', '/api/finance/gstr2b'], ['GET', '/api/finance/gstr2b/export'],
            ['GET', "/api/products/{$item->id}/stock"], ['POST', "/api/products/{$item->id}/stock"], ['POST', "/api/products/{$item->id}/restore"],
        ] as [$verb, $uri]) {
            Sanctum::actingAs($staff->fresh());
            $this->assertSame(403, $this->json($verb, $uri)->getStatusCode(), "staff reached {$verb} {$uri}");
        }
        $this->assertNotNull($vendor->fresh());
        $this->assertFalse((bool) $item->fresh()->is_active);
    }

    public function test_an_accountant_works_in_the_owners_business_only(): void
    {
        $vendor = $this->vendor();
        // The owner's second business, which the accountant was not invited to.
        [, $other] = $this->business(null, $this->owner);
        $otherVendor = $this->vendor('Elsewhere', null, $other);
        $this->owner->forceFill(['active_company_id' => $this->company->id])->save();

        $accountant = $this->member('accountant');
        // A request run as the owner leaves the owner on the test's guard, so
        // each request signs the member in again (as a real app token does).
        $as = function () use ($accountant) {
            Sanctum::actingAs($accountant->fresh());

            return $this;
        };

        $res = $as()->postJson('/api/purchases', $this->billPayload($vendor))->assertCreated();
        $bill = PurchaseBill::find($res->json('data.id'));
        // Runs as the owner, in the business the member joined.
        $this->assertSame($this->owner->id, $bill->user_id);
        $this->assertSame($this->company->id, $bill->company_id);

        $as()->getJson('/api/vendors')->assertOk()->assertJsonCount(1, 'data');
        $as()->getJson("/api/vendors/{$otherVendor->id}")->assertForbidden();
        $as()->deleteJson("/api/vendors/{$otherVendor->id}")->assertForbidden();
        $this->assertNotNull($otherVendor->fresh());
    }
}
