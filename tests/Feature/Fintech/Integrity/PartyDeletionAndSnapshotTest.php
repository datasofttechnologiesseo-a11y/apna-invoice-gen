<?php

namespace Tests\Feature\Fintech\Integrity;

use App\Http\Controllers\InvoiceShareController;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PurchaseBill;
use App\Models\Quotation;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Parties and documents. A customer or vendor with documents is never deleted
 * out from under them, and an issued invoice shows the recipient exactly as
 * it was issued (Rule 46(d)/(e)) wherever it is shown - on screen, in print,
 * in GSTR-1, on the public share page and in the mobile app.
 *
 * Standard invoice: 1 x 1,000 @ 18%, Maharashtra -> Maharashtra:
 * CGST 90 + SGST 90 = Rs 1,180.
 */
class PartyDeletionAndSnapshotTest extends TestCase
{
    use RefreshDatabase;
    use IntegrityFixtures;

    private const NEW_GSTIN = '27AAPFU0939F1ZV';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeToday();
        $this->setUpBusiness();
    }

    public function test_a_customer_with_any_invoice_even_a_draft_cannot_be_deleted_on_the_web_or_the_app(): void
    {
        $this->draft();

        $this->actingAs($this->user)->delete(route('customers.destroy', $this->customer))->assertSessionHasErrors('customer');
        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/customers/{$this->customer->id}")->assertStatus(422);

        $this->assertNotNull(Customer::find($this->customer->id));
    }

    public function test_the_database_itself_refuses_to_orphan_an_issued_invoice(): void
    {
        $inv = $this->issued();

        try {
            Customer::whereKey($this->customer->id)->delete();
            $this->fail('the customer row was deleted from under an issued invoice');
        } catch (QueryException) {
            $this->assertSame($this->customer->id, $inv->fresh()->customer_id);
        }
    }

    public function test_a_customer_with_only_a_quotation_gets_a_clear_refusal_not_a_server_error(): void
    {
        Quotation::factory()->sent()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'customer_id' => $this->customer->id]);

        $web = $this->actingAs($this->user)->delete(route('customers.destroy', $this->customer));
        $this->assertNotSame(500, $web->status(), 'deleting a customer who has a quotation crashes (the quotation FK restricts it)');
        Sanctum::actingAs($this->user);
        $app = $this->deleteJson("/api/customers/{$this->customer->id}");
        $this->assertNotSame(500, $app->status(), 'the app gets a server error');

        $this->assertNotNull(Customer::find($this->customer->id));
        $this->assertSame(1, Quotation::count());
    }

    public function test_a_vendor_with_purchase_invoices_cannot_be_deleted(): void
    {
        $vendor = $this->company->vendors()->create(['user_id' => $this->user->id, 'name' => 'Mumbai Metals', 'state_id' => $this->mh->id]);
        $this->actingAs($this->user)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'MM/1', 'bill_date' => '2026-10-10', 'itc_eligible' => 1,
            'items' => [['description' => 'Steel', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 1000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->delete(route('vendors.destroy', $vendor))->assertSessionHas('error');
        $this->assertNotNull(Vendor::find($vendor->id));

        try {
            Vendor::whereKey($vendor->id)->delete();
            $this->fail('the vendor row was deleted from under a purchase invoice');
        } catch (QueryException) {
            $this->assertSame(1, PurchaseBill::where('vendor_id', $vendor->id)->count());
        }
    }

    public function test_editing_the_customer_after_issue_never_rewrites_the_issued_invoice(): void
    {
        $inv = $this->issued(); // to Patil Stores, unregistered, Maharashtra

        $this->actingAs($this->user)->put(route('customers.update', $this->customer), [
            'name' => 'Patil Enterprises', 'gstin' => self::NEW_GSTIN, 'address_line1' => '9 New Market Yard',
            'city' => 'Pune', 'state_id' => $this->mh->id, 'country' => 'India',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Patil Enterprises', $this->customer->fresh()->name);

        $fresh = $inv->fresh();
        $billed = $fresh->billedCustomer();
        $this->assertSame('Patil Stores', $billed->name);
        $this->assertNull($billed->gstin, 'issued as B2C; a GSTIN added later must not turn it into B2B');
        $this->assertMoney(90.00, $fresh->total_cgst);
        $this->assertMoney(90.00, $fresh->total_sgst);

        $this->actingAs($this->user)->get(route('invoices.show', $inv))->assertOk()->assertSee('Patil Stores')->assertDontSee(self::NEW_GSTIN);
        $this->actingAs($this->user)->get(route('invoices.print', $inv))->assertOk()->assertSee('Patil Stores')->assertDontSee('Patil Enterprises')->assertDontSee(self::NEW_GSTIN);

        $csv = $this->actingAs($this->user)->get(route('invoices.gstr1', ['from' => '2026-10-01', 'to' => '2026-10-31']))->streamedContent();
        $row = collect(explode("\n", $csv))->first(fn ($l) => str_starts_with($l, 'INV-0001'));
        $this->assertNotNull($row);
        $this->assertStringContainsString('Patil Stores', $row);
        $this->assertStringContainsString('B2CS (7)', $row, 'GSTR-1 keeps the invoice in B2C as issued');
        $this->assertStringNotContainsString(self::NEW_GSTIN, $row);

        // A draft follows the live customer.
        $this->assertSame('Patil Enterprises', $this->draft()->billedCustomer()->name);
    }

    public function test_a_customer_moving_state_after_issue_never_changes_the_tax_heads_or_place_of_supply(): void
    {
        $inv = $this->issued();
        $this->actingAs($this->user)->put(route('customers.update', $this->customer), [
            'name' => 'Patil Stores', 'state_id' => $this->ka->id, 'country' => 'India',
        ])->assertSessionHasNoErrors();

        $fresh = $inv->fresh();
        $this->assertFalse((bool) $fresh->is_interstate);
        $this->assertSame($this->mh->id, $fresh->place_of_supply_state_id);
        $this->assertMoney(90.00, $fresh->total_cgst);
        $this->assertMoney(0.00, $fresh->total_igst);
        $this->assertSame('Maharashtra', $fresh->billedCustomer()->state->name);
    }

    public function test_the_public_share_page_shows_the_customer_as_issued(): void
    {
        $inv = $this->issued();
        $url = InvoiceShareController::makePublicUrl($inv);
        $this->actingAs($this->user)->put(route('customers.update', $this->customer), [
            'name' => 'Patil Enterprises', 'state_id' => $this->mh->id, 'country' => 'India',
        ])->assertSessionHasNoErrors();

        auth()->logout();
        $this->get($url)->assertOk()
            ->assertDontSee('Patil Enterprises')   // the PDF behind this page says Patil Stores
            ->assertSee('Patil Stores');
    }

    public function test_the_app_shows_the_customer_as_issued_on_an_issued_invoice(): void
    {
        $inv = $this->issued();
        $this->actingAs($this->user)->put(route('customers.update', $this->customer), [
            'name' => 'Patil Enterprises', 'gstin' => self::NEW_GSTIN, 'state_id' => $this->mh->id, 'country' => 'India',
        ])->assertSessionHasNoErrors();

        Sanctum::actingAs($this->user);
        $body = $this->getJson("/api/invoices/{$inv->id}")->assertOk()->getContent();

        $this->assertStringContainsString('"Patil Stores"', $body, 'the app shows today\'s customer (Patil Enterprises, with a GSTIN) on an invoice issued to Patil Stores without one');
    }
}
