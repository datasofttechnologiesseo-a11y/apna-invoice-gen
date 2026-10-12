<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile app is a second door onto the same books. Whatever goes through
 * /api must come out with the same rupees, the same numbers in the same
 * series and the same refusals as the web app - otherwise the GST documents
 * depend on which door they went out of.
 *
 * Home business: Sharma Traders, Maharashtra (27). Customer Patil Stores is in
 * Maharashtra too (intra-state: CGST + SGST). A second, unrelated business
 * (Rival Traders, Karnataka) owns records the first must never reach.
 */
class ApiMoneyParityTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private Company $biz;
    private User $owner;
    private Customer $patil;
    private Company $rival;
    private User $rivalOwner;
    private Customer $rivalCustomer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();

        $this->biz = $this->makeBusiness();
        $this->owner = $this->ownerOf($this->biz);
        $this->patil = $this->customerOf($this->biz);

        $this->rival = $this->makeBusiness([
            'name' => 'Rival Traders', 'gstin' => '29ABCDE1234F1ZW',
            'state_id' => $this->state('29')->id, 'city' => 'Bengaluru',
        ]);
        $this->rivalOwner = $this->ownerOf($this->rival);
        $this->rivalCustomer = $this->customerOf($this->rival, '29', ['name' => 'Rival Customer', 'email' => 'rival@example.test']);
    }

    // ---- helpers ----------------------------------------------------------

    private function apiDraft(User $user, Customer $customer, array $items, array $over = []): Invoice
    {
        $res = $this->apiAs($user)->postJson('/api/invoices', array_merge([
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => $items,
        ], $over));
        $res->assertCreated();

        return Invoice::findOrFail($res->json('data.id'));
    }

    private function apiIssue(User $user, Invoice $invoice): Invoice
    {
        $this->apiAs($user)->postJson("/api/invoices/{$invoice->id}/finalize")->assertOk();

        return $invoice->fresh();
    }

    private function apiPay(User $user, Invoice $invoice, float $amount, array $extra = [])
    {
        return $this->apiAs($user)->postJson("/api/invoices/{$invoice->id}/payments", array_merge([
            'amount' => $amount,
            'method' => 'upi',
            'received_at' => now()->toDateString(),
        ], $extra));
    }

    private function memberOf(Company $company, string $role): User
    {
        $member = User::factory()->create(['name' => 'Member ' . $role]);
        CompanyMember::create([
            'company_id' => $company->id, 'user_id' => $member->id, 'email' => $member->email,
            'role' => $role, 'token' => CompanyMember::newToken(), 'invited_by' => $company->user_id,
            'accepted_at' => now(),
        ]);
        $member->forceFill(['active_company_id' => $company->id])->save();

        return $member->fresh();
    }

    // ---- PUT /api/invoices/{invoice} ---------------------------------------

    public function test_a_draft_edited_over_the_api_works_out_the_same_figures_as_the_web_form(): void
    {
        // Web draft: 3 x 333.33 @ 18%, Maharashtra -> Maharashtra.
        // 3 x 333.33 = 999.99 taxable; CGST 9% = 89.9991 -> 90.00; SGST 90.00;
        // tax 180.00; 999.99 + 180.00 = 1,179.99 -> grand 1,180, round off +0.01.
        $web = $this->webDraft($this->owner, $this->patil, [$this->line(333.33, 18, 3)]);

        // The phone starts with 1 x 1,000 (1,000 + 90 + 90 = 1,180) ...
        $api = $this->apiDraft($this->owner, $this->patil, [$this->line(1000, 18, 1)]);
        $this->assertMoney(1180, $api->grand_total);

        // ... and is then edited to the same lines as the web draft.
        $res = $this->apiAs($this->owner)->putJson("/api/invoices/{$api->id}", [
            'customer_id' => $this->patil->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'items' => [$this->line(333.33, 18, 3)],
            'notes' => 'Edited on the phone',
        ])->assertOk();

        $expected = ['subtotal' => 999.99, 'total_cgst' => 90.00, 'total_sgst' => 90.00, 'total_igst' => 0,
            'total_tax' => 180.00, 'round_off' => 0.01, 'grand_total' => 1180, 'balance' => 1180];
        foreach ($expected as $field => $value) {
            $this->assertMoney($value, $res->json("data.{$field}"), "API {$field}");
            $this->assertMoney($value, $web->{$field}, "web {$field}");
            $this->assertMoney($value, $api->fresh()->{$field}, "stored {$field}");
        }
        $api->refresh();
        $this->assertSame('draft', $api->status);
        $this->assertNull($api->invoice_number, 'an edited draft has no number yet');
        $this->assertSame(1, $api->items()->count(), 'the old line was replaced, not kept');
        $this->assertSame('Edited on the phone', $api->notes);
        $this->assertSame(now()->addDays(15)->toDateString(), $api->due_date->toDateString());

        // Switching the draft to a Karnataka customer makes it inter-state:
        // 999.99 x 18% = 179.9982 -> IGST 180.00; no CGST/SGST; grand 1,180.
        $kaveri = $this->customerOf($this->biz, '29', ['name' => 'Kaveri Agencies']);
        $this->apiAs($this->owner)->putJson("/api/invoices/{$api->id}", [
            'customer_id' => $kaveri->id,
            'invoice_date' => now()->toDateString(),
            'items' => [$this->line(333.33, 18, 3)],
        ])->assertOk()
            ->assertJsonPath('data.is_interstate', true);
        $api->refresh();
        $this->assertMoney(180.00, $api->total_igst);
        $this->assertMoney(0, $api->total_cgst);
        $this->assertMoney(0, $api->total_sgst);
        $this->assertMoney(1180, $api->grand_total);
        $this->assertSame($this->state('29')->id, (int) $api->place_of_supply_state_id);

        // Refusals: an invalid GST slab, someone else's customer, someone else's draft.
        $this->apiAs($this->owner)->putJson("/api/invoices/{$api->id}", [
            'customer_id' => $kaveri->id, 'invoice_date' => now()->toDateString(),
            'items' => [$this->line(100, 13, 1)],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.gst_rate');

        $this->apiAs($this->owner)->putJson("/api/invoices/{$api->id}", [
            'customer_id' => $this->rivalCustomer->id, 'invoice_date' => now()->toDateString(),
            'items' => [$this->line(100, 18, 1)],
        ])->assertStatus(422)->assertJsonValidationErrors('customer_id');
        $this->assertSame($kaveri->id, (int) $api->fresh()->customer_id);

        $rivalDraft = $this->webDraft($this->rivalOwner, $this->rivalCustomer);
        $this->apiAs($this->owner)->putJson("/api/invoices/{$rivalDraft->id}", [
            'customer_id' => $this->patil->id, 'invoice_date' => now()->toDateString(),
            'items' => [$this->line(1, 18, 1)],
        ])->assertForbidden();
        $this->assertMoney(1180, $rivalDraft->fresh()->grand_total, 'the other business draft is untouched');
    }

    public function test_a_rate_with_fractions_of_a_paisa_is_refused_over_the_api_as_on_the_web(): void
    {
        // The web form refuses a rate of 333.335 (decimal:0,2): a rate has to be
        // printable in paise. The API must refuse the same input instead of
        // silently rounding the price the customer agreed to.
        $web = $this->webDraft($this->owner, $this->patil);
        $this->actingAs($this->owner)->put(route('invoices.update', $web), [
            'customer_id' => $this->patil->id, 'invoice_date' => now()->toDateString(),
            'items' => [$this->line(333.335, 18, 3)],
        ])->assertSessionHasErrors('items.0.rate');

        $api = $this->apiDraft($this->owner, $this->patil, [$this->line(1000, 18, 1)]);
        $this->apiAs($this->owner)->putJson("/api/invoices/{$api->id}", [
            'customer_id' => $this->patil->id, 'invoice_date' => now()->toDateString(),
            'items' => [$this->line(333.335, 18, 3)],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.rate');
    }

    public function test_an_issued_invoice_keeps_its_amounts_when_edited_over_the_api(): void
    {
        // 1 x 1,000 @ 18% intra = 1,000 + 90 + 90 = 1,180.
        $inv = $this->webIssued($this->owner, $this->patil);
        $this->assertSame('INV-0001', $inv->invoice_number);

        // The phone tries to change the lines of an issued invoice: only the
        // soft fields move, exactly as on the web.
        $this->apiAs($this->owner)->putJson("/api/invoices/{$inv->id}", [
            'customer_id' => $this->patil->id,
            'invoice_date' => now()->toDateString(),
            'items' => [$this->line(9999, 18, 5)],
            'notes' => 'Deliver by Friday',
            'vehicle_number' => 'MH12AB1234',
        ])->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-0001')
            ->assertJsonPath('data.notes', 'Deliver by Friday');

        $inv->refresh();
        $this->assertMoney(1180, $inv->grand_total, 'amounts are frozen after issue');
        $this->assertMoney(1000, $inv->subtotal);
        $this->assertSame(1, $inv->items()->count());
        $this->assertMoney(1, $inv->items()->first()->quantity);
        $this->assertSame('MH12AB1234', $inv->vehicle_number);

        // Web: same rule, same result.
        $this->actingAs($this->owner)->put(route('invoices.update', $inv), [
            'items' => [$this->line(9999, 18, 5)],
            'notes' => 'Web note',
        ])->assertRedirect(route('invoices.show', $inv))
            ->assertSessionHas('status', 'Invoice details updated. Amounts, items and customer cannot be changed after issue.');
        $this->assertMoney(1180, $inv->fresh()->grand_total);

        // A cancelled invoice cannot be edited at all, on either door.
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/cancel", ['cancellation_reason' => 'Raised by mistake'])->assertOk();
        $this->apiAs($this->owner)->putJson("/api/invoices/{$inv->id}", ['notes' => 'after cancel'])
            ->assertStatus(422)->assertJsonPath('message', 'This invoice cannot be edited.');
        $this->actingAs($this->owner)->put(route('invoices.update', $inv), ['notes' => 'after cancel'])->assertForbidden();
        $this->assertSame('Web note', $inv->fresh()->notes);
    }

    // ---- POST /api/invoices/{invoice}/finalize -----------------------------

    public function test_api_finalize_continues_the_same_number_series_as_the_web(): void
    {
        $first = $this->webIssued($this->owner, $this->patil);
        $this->assertSame('INV-0001', $first->invoice_number);

        $phone = $this->apiDraft($this->owner, $this->patil, [$this->line(500, 5, 2)]);
        $res = $this->apiAs($this->owner)->postJson("/api/invoices/{$phone->id}/finalize")->assertOk();
        $res->assertJsonPath('data.invoice_number', 'INV-0002')
            ->assertJsonPath('data.status', 'final');
        // 2 x 500 = 1,000; 5% intra = CGST 25 + SGST 25; grand 1,050.
        $this->assertMoney(1050, $res->json('data.grand_total'));
        $this->assertNotNull($phone->fresh()->finalized_at);

        $third = $this->webIssued($this->owner, $this->patil);
        $this->assertSame('INV-0003', $third->invoice_number, 'the web continues after the API number');
        $this->assertSame(3, (int) $this->biz->fresh()->invoice_counter);

        // A second "issue" for the same invoice is refused and burns no number.
        $this->apiAs($this->owner)->postJson("/api/invoices/{$phone->id}/finalize")
            ->assertStatus(422)->assertJsonPath('message', 'Only draft invoices can be issued.');
        $this->assertSame('INV-0002', $phone->fresh()->invoice_number);
        $this->assertSame(3, (int) $this->biz->fresh()->invoice_counter);

        // A number typed on a web draft is kept when the phone issues it, and
        // the series carries on from it (Rule 46(b): consecutive).
        $custom = $this->webDraft($this->owner, $this->patil, null, ['invoice_number' => 'INV-0040']);
        $this->apiIssue($this->owner, $custom);
        $this->assertSame('INV-0040', $custom->fresh()->invoice_number);
        $next = $this->apiIssue($this->owner, $this->apiDraft($this->owner, $this->patil, [$this->line()]));
        $this->assertSame('INV-0041', $next->invoice_number);
        $this->assertSame(5, Invoice::where('company_id', $this->biz->id)->distinct()->count('invoice_number'));

        // Someone else's draft cannot be issued from this account.
        $rivalDraft = $this->webDraft($this->rivalOwner, $this->rivalCustomer);
        $this->apiAs($this->owner)->postJson("/api/invoices/{$rivalDraft->id}/finalize")->assertForbidden();
        $this->assertSame('draft', $rivalDraft->fresh()->status);
        $this->assertNull($rivalDraft->fresh()->invoice_number);
        $this->assertSame(0, (int) $this->rival->fresh()->invoice_counter);
    }

    // ---- POST /api/invoices/{invoice}/cancel -------------------------------

    public function test_cancelling_over_the_api_follows_the_web_rules(): void
    {
        $almirah = $this->productOf($this->biz, ['track_stock' => true, 'opening_stock' => 10]);
        // 2 x 1,000 = 2,000; CGST 180 + SGST 180; grand 2,360. Issuing takes 2 off the shelf.
        $inv = $this->apiIssue($this->owner, $this->apiDraft($this->owner, $this->patil, [
            $this->line(1000, 18, 2, ['product_id' => $almirah->id]),
        ]));
        $this->assertMoney(2360, $inv->grand_total);
        $this->assertEqualsWithDelta(8.0, $almirah->fresh()->stockOnHand(), 0.001, '10 - 2 sold');

        // The reason is required (5+ characters), as on the web.
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/cancel", [])
            ->assertStatus(422)->assertJsonValidationErrors('cancellation_reason');
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/cancel", ['cancellation_reason' => 'No'])
            ->assertStatus(422)->assertJsonValidationErrors('cancellation_reason');
        $this->assertSame('final', $inv->fresh()->status);

        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/cancel", ['cancellation_reason' => 'Customer cancelled the order'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $inv->refresh();
        $this->assertSame('cancelled', $inv->status);
        $this->assertNotNull($inv->cancelled_at);
        $this->assertSame('Customer cancelled the order', $inv->cancellation_reason);
        $this->assertSame('INV-0001', $inv->invoice_number, 'the number stays on the cancelled invoice');
        $this->assertEqualsWithDelta(10.0, $almirah->fresh()->stockOnHand(), 0.001, 'the goods are back on the shelf');
        $this->assertSame(1, AuditLog::where('action', 'invoice.cancelled')->count());

        // Cancelled once, cancelled for good: no second cancel, no payment.
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/cancel", ['cancellation_reason' => 'Again please'])
            ->assertStatus(422)->assertJsonPath('message', 'This invoice cannot be cancelled.');
        $this->apiPay($this->owner, $inv, 100)
            ->assertStatus(422)->assertJsonPath('message', 'Cancelled invoices cannot accept payments.');
        $this->assertEqualsWithDelta(10.0, $almirah->fresh()->stockOnHand(), 0.001, 'stock is returned only once');

        // Money already received against an invoice blocks cancelling on both doors.
        $paid = $this->webIssued($this->owner, $this->patil);
        $this->webPay($this->owner, $paid, 500)->assertSessionHasNoErrors();
        $this->apiAs($this->owner)->postJson("/api/invoices/{$paid->id}/cancel", ['cancellation_reason' => 'Changed my mind'])
            ->assertStatus(422);
        $this->actingAs($this->owner)->post(route('invoices.cancel', $paid), ['cancellation_reason' => 'Changed my mind'])
            ->assertStatus(422);
        $this->assertSame('partially_paid', $paid->fresh()->status);

        // Billing staff may bill but not cancel; another business is out of reach.
        $open = $this->webIssued($this->owner, $this->patil);
        $staff = $this->memberOf($this->biz, 'staff');
        $this->apiAs($staff)->postJson("/api/invoices/{$open->id}/cancel", ['cancellation_reason' => 'Staff tries to cancel'])
            ->assertForbidden();
        $rivalInv = $this->webIssued($this->rivalOwner, $this->rivalCustomer);
        $this->apiAs($this->owner)->postJson("/api/invoices/{$rivalInv->id}/cancel", ['cancellation_reason' => 'Not mine to cancel'])
            ->assertForbidden();
        $this->assertSame('final', $open->fresh()->status);
        $this->assertSame('final', $rivalInv->fresh()->status);
    }

    public function test_a_cancelled_invoice_cannot_be_deleted_over_the_api_either(): void
    {
        // An issued number belongs to the series reported in GSTR-1 Table 13.
        // The web refuses to delete a cancelled invoice (it stays on record as
        // "cancelled"); the API must refuse too, or INV-0001 vanishes from the
        // books and Table 13 shows a gap nobody can explain.
        $inv = $this->webIssued($this->owner, $this->patil);
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/cancel", ['cancellation_reason' => 'Raised twice'])->assertOk();

        $this->actingAs($this->owner)->delete(route('invoices.destroy', $inv))
            ->assertRedirect(route('invoices.show', $inv))
            ->assertSessionHas('error');
        $this->assertNotNull(Invoice::find($inv->id), 'web keeps the cancelled invoice');

        $this->apiAs($this->owner)->deleteJson("/api/invoices/{$inv->id}")->assertStatus(422);
        $this->assertNotNull(Invoice::find($inv->id), 'the API must keep the cancelled invoice INV-0001 on record');
    }

    // ---- POST /api/invoices/{invoice}/payments -----------------------------

    public function test_an_overpayment_over_the_api_is_refused_exactly_like_the_web(): void
    {
        $inv = $this->webIssued($this->owner, $this->patil); // 1,180

        // Web receipt of 1,000: RCPT-0001, 180 left.
        $this->webPay($this->owner, $inv, 1000)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Payment recorded. Receipt RCPT-0001 issued.');
        $this->assertMoney(180, $inv->fresh()->balance);

        // 180.01 is one paisa more than is owed: refused through both doors,
        // no receipt row and no receipt number used up.
        $this->webPay($this->owner, $inv, 180.01)->assertSessionHasErrors('amount');
        $this->apiPay($this->owner, $inv, 180.01)->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, (int) $this->biz->fresh()->receipt_counter);

        // Exactly the balance through the API: next receipt in the same series.
        $res = $this->apiPay($this->owner, $inv, 180, ['reference_number' => 'UPI-778899'])->assertCreated();
        $res->assertJsonPath('data.receipt_number', 'RCPT-0002')
            ->assertJsonPath('data.reference_number', 'UPI-778899');
        $this->assertMoney(180, $res->json('data.amount'));
        $inv->refresh();
        $this->assertMoney(1180, $inv->paid_amount); // 1,000 + 180
        $this->assertMoney(0, $inv->balance);
        $this->assertSame('paid', $inv->status);

        // Nothing more is owed: even a rupee is refused.
        $this->apiPay($this->owner, $inv, 1)->assertStatus(422)->assertJsonValidationErrors('amount');

        // Other refusals the web also makes.
        $draft = $this->apiDraft($this->owner, $this->patil, [$this->line()]);
        $this->apiPay($this->owner, $draft, 100)->assertStatus(422)
            ->assertJsonPath('message', 'Issue the invoice before recording payments.');
        $open = $this->webIssued($this->owner, $this->patil);
        $this->apiPay($this->owner, $open, 100, ['received_at' => now()->addDay()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('received_at');
        $this->apiPay($this->owner, $open, 100, ['method' => 'bitcoin'])
            ->assertStatus(422)->assertJsonValidationErrors('method');
        $this->apiPay($this->owner, $open, 0)->assertStatus(422)->assertJsonValidationErrors('amount');
        $rivalInv = $this->webIssued($this->rivalOwner, $this->rivalCustomer);
        $this->apiPay($this->owner, $rivalInv, 100)->assertForbidden();
        $this->assertSame(2, Payment::count());
        $this->assertMoney(1180, $rivalInv->fresh()->balance);
    }

    public function test_a_receipt_dated_inside_locked_books_is_refused_over_the_api_like_the_web(): void
    {
        // A September invoice issued before the books were closed.
        $inv = $this->webIssued($this->owner, $this->patil, null, ['invoice_date' => '2026-09-15', 'due_date' => '2026-10-15']);
        $this->biz->forceFill(['books_locked_until' => '2026-09-30'])->save();

        $this->webPay($this->owner, $inv, 500, ['received_at' => '2026-09-20'])->assertSessionHasErrors('received_at');
        $this->apiPay($this->owner, $inv, 500, ['received_at' => '2026-09-20'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('received_at')
            ->assertJsonPath('message', 'Books are locked up to 30 Sep 2026. Date the receipt after that.');
        $this->assertSame(0, Payment::count());
        $this->assertMoney(1180, $inv->fresh()->balance);

        // Money received today against the September invoice is fine: the lock
        // is on the receipt date, not the invoice date. 1,180 - 500 = 680.
        $this->apiPay($this->owner, $inv, 500)->assertCreated();
        $this->assertMoney(680, $inv->fresh()->balance);
        $this->assertSame('partially_paid', $inv->fresh()->status);
    }

    // ---- DELETE /api/payments/{payment} ------------------------------------

    public function test_reversing_a_payment_over_the_api_restores_the_balance_and_status(): void
    {
        $inv = $this->webIssued($this->owner, $this->patil); // 1,180
        $this->webPay($this->owner, $inv, 1000)->assertSessionHasNoErrors();
        $webReceipt = $this->latestPayment();
        $phoneReceipt = Payment::findOrFail($this->apiPay($this->owner, $inv, 180)->assertCreated()->json('data.id'));
        $this->assertSame('paid', $inv->fresh()->status);

        // Reverse the 180: 1,180 - 1,000 = 180 owed again, part paid.
        $this->apiAs($this->owner)->deleteJson("/api/payments/{$phoneReceipt->id}")
            ->assertOk()->assertJsonPath('message', 'Payment reversed.');
        $inv->refresh();
        $this->assertNull(Payment::find($phoneReceipt->id));
        $this->assertMoney(1000, $inv->paid_amount);
        $this->assertMoney(180, $inv->balance);
        $this->assertSame('partially_paid', $inv->status);

        // Reverse the web receipt from the phone too: back to fully owed.
        $this->apiAs($this->owner)->deleteJson("/api/payments/{$webReceipt->id}")->assertOk();
        $inv->refresh();
        $this->assertMoney(0, $inv->paid_amount);
        $this->assertMoney(1180, $inv->balance);
        $this->assertSame('final', $inv->status);
        $this->assertSame(2, AuditLog::where('action', 'payment.reversed')->count());

        // Someone else's receipt cannot be reversed from this account.
        $rivalInv = $this->webIssued($this->rivalOwner, $this->rivalCustomer);
        $this->webPay($this->rivalOwner, $rivalInv, 100)->assertSessionHasNoErrors();
        $rivalReceipt = $this->latestPayment();
        $this->apiAs($this->owner)->deleteJson("/api/payments/{$rivalReceipt->id}")->assertForbidden();
        $this->assertNotNull(Payment::find($rivalReceipt->id));
        $this->assertMoney(1080, $rivalInv->fresh()->balance);

        // A receipt inside closed books stays: 1,180 - 200 = 980 owed, unchanged.
        $old = $this->webIssued($this->owner, $this->patil, null, ['invoice_date' => '2026-09-10', 'due_date' => '2026-10-10']);
        $this->webPay($this->owner, $old, 200, ['received_at' => '2026-09-12'])->assertSessionHasNoErrors();
        $locked = $this->latestPayment();
        $this->biz->forceFill(['books_locked_until' => '2026-09-30'])->save();
        $this->apiAs($this->owner)->deleteJson("/api/payments/{$locked->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Books are locked up to 30 Sep 2026. This payment cannot be reversed.');
        $this->assertNotNull(Payment::find($locked->id));
        $this->assertMoney(980, $old->fresh()->balance);
    }

    // ---- /api/invoices/{invoice}/credit-notes ------------------------------

    public function test_api_credit_notes_split_tax_pro_rata_and_never_exceed_the_invoice(): void
    {
        // 1 x 1,000 @ 18% intra: taxable 1,000, CGST 90, SGST 90, grand 1,180.
        $inv = $this->webIssued($this->owner, $this->patil);

        $list = $this->apiAs($this->owner)->getJson("/api/invoices/{$inv->id}/credit-notes")->assertOk();
        $this->assertMoney(1180, $list->json('meta.creditable'));
        $list->assertJsonPath('meta.can_create', true)
            ->assertJsonPath('meta.window_closed', false)
            ->assertJsonPath('meta.deadline', '2027-11-30') // s.34(2): 30 Nov after the FY 2026-27 ends
            ->assertJsonCount(0, 'data');
        $this->assertContains('sales_return', collect($list->json('meta.reasons'))->pluck('value')->all());

        // Note 1 (API) 590 = half of 1,180: taxable 500, CGST 45, SGST 45.
        $one = $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 590, 'reason' => 'rate_correction',
        ])->assertCreated();
        $one->assertJsonPath('data.credit_note_number', 'CRN-0001');
        $this->assertMoney(500, $one->json('data.taxable_value'));
        $this->assertMoney(45, $one->json('data.total_cgst'));
        $this->assertMoney(45, $one->json('data.total_sgst'));
        $this->assertMoney(0, $one->json('data.total_igst'));
        $inv->refresh();
        $this->assertMoney(590, $inv->credited_amount);
        $this->assertMoney(590, $inv->balance);
        $this->assertSame('final', $inv->status);

        // Note 2 (web) 300: ratio 300 / 1,180 = 0.254237...
        // taxable 1,000 x 0.254237 = 254.24; CGST 90 x 0.254237 = 22.88; SGST 22.88;
        // 254.24 + 22.88 + 22.88 = 300.00.
        $this->actingAs($this->owner)->post(route('credit-notes.store', $inv), [
            'credit_note_date' => now()->toDateString(), 'amount' => 300, 'reason' => 'post_sale_discount',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Credit note CRN-0002 issued for ₹300');
        $two = CreditNote::latest('id')->firstOrFail();
        $this->assertSame('CRN-0002', $two->credit_note_number, 'one series across both doors');
        $this->assertMoney(254.24, $two->taxable_value);
        $this->assertMoney(22.88, $two->total_cgst);
        $this->assertMoney(22.88, $two->total_sgst);

        // 1,180 - 590 - 300 = 290 left. One paisa more is refused on both doors.
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 290.01, 'reason' => 'other',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->actingAs($this->owner)->post(route('credit-notes.store', $inv), [
            'credit_note_date' => now()->toDateString(), 'amount' => 290.01, 'reason' => 'other',
        ])->assertSessionHasErrors('amount');
        $this->assertSame(2, CreditNote::count());

        // Note 3 (API) 290 clears the invoice and takes the exact remainder:
        // taxable 1,000 - 500 - 254.24 = 245.76; CGST 90 - 45 - 22.88 = 22.12;
        // SGST 22.12; 245.76 + 44.24 = 290.00. All notes reverse the invoice to the paisa.
        $three = $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 290, 'reason' => 'other', 'notes' => 'Final settlement',
        ])->assertCreated();
        $three->assertJsonPath('data.credit_note_number', 'CRN-0003');
        $this->assertMoney(245.76, $three->json('data.taxable_value'));
        $this->assertMoney(22.12, $three->json('data.total_cgst'));
        $this->assertMoney(22.12, $three->json('data.total_sgst'));
        $this->assertMoney(1000, CreditNote::sum('taxable_value'));
        $this->assertMoney(90, CreditNote::sum('total_cgst'));
        $this->assertMoney(90, CreditNote::sum('total_sgst'));

        $inv->refresh();
        $this->assertMoney(1180, $inv->credited_amount);
        $this->assertMoney(0, $inv->balance);
        $this->assertSame('paid', $inv->status);

        $after = $this->apiAs($this->owner)->getJson("/api/invoices/{$inv->id}/credit-notes")->assertOk();
        $this->assertMoney(0, $after->json('meta.creditable'));
        $after->assertJsonPath('meta.can_create', false)->assertJsonCount(3, 'data');
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 1, 'reason' => 'other',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        // Another business's invoice: neither listed nor credited.
        $rivalInv = $this->webIssued($this->rivalOwner, $this->rivalCustomer);
        $this->apiAs($this->owner)->getJson("/api/invoices/{$rivalInv->id}/credit-notes")->assertForbidden();
        $this->apiAs($this->owner)->postJson("/api/invoices/{$rivalInv->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 100, 'reason' => 'other',
        ])->assertForbidden();
        $this->assertMoney(0, $rivalInv->fresh()->credited_amount);
    }

    public function test_an_api_credit_note_is_refused_where_the_web_refuses_it(): void
    {
        $draft = $this->webDraft($this->owner, $this->patil);
        $this->apiAs($this->owner)->postJson("/api/invoices/{$draft->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 100, 'reason' => 'other',
        ])->assertStatus(422)->assertJsonPath('message', 'Issue the invoice before creating a credit note.');

        $cancelled = $this->webIssued($this->owner, $this->patil);
        $this->apiAs($this->owner)->postJson("/api/invoices/{$cancelled->id}/cancel", ['cancellation_reason' => 'Raised by mistake'])->assertOk();
        $this->apiAs($this->owner)->postJson("/api/invoices/{$cancelled->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 100, 'reason' => 'other',
        ])->assertStatus(422)->assertJsonPath('message', 'Cancelled invoices cannot be credited.');

        $inv = $this->webIssued($this->owner, $this->patil, null, ['invoice_date' => '2026-10-05', 'due_date' => '2026-11-04']);
        // Dated before the invoice, in the future, or with an unknown reason.
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => '2026-10-04', 'amount' => 100, 'reason' => 'other',
        ])->assertStatus(422)->assertJsonValidationErrors('credit_note_date');
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => now()->addDay()->toDateString(), 'amount' => 100, 'reason' => 'other',
        ])->assertStatus(422)->assertJsonValidationErrors('credit_note_date');
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 100, 'reason' => 'goodwill',
        ])->assertStatus(422)->assertJsonValidationErrors('reason');

        // Inside closed books.
        $this->biz->forceFill(['books_locked_until' => '2026-10-06'])->save();
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => '2026-10-06', 'amount' => 100, 'reason' => 'other',
        ])->assertStatus(422)->assertJsonPath('message', 'Books are locked up to 06 Oct 2026. Credit note date must be after that.');

        $this->assertSame(0, CreditNote::count());
        $this->assertSame(0, (int) $this->biz->fresh()->credit_note_counter, 'no credit note number was used up');
    }

    public function test_item_lines_sent_to_the_api_are_not_silently_dropped(): void
    {
        // On the web a credit note can name the invoice lines that came back
        // (1 of 7 almirahs): the note credits that line and the goods go back
        // into stock. The API has no "lines" field. A phone that sends them
        // must either get the same line-based sales return or a clear refusal;
        // today the lines are ignored and an amount-only note is booked, so
        // stock and GSTR-1 Table 12 quantities silently disagree with what the
        // user did.
        $almirah = $this->productOf($this->biz, ['track_stock' => true, 'opening_stock' => 20]);
        $inv = $this->webIssued($this->owner, $this->patil, [$this->line(1000, 18, 7, ['product_id' => $almirah->id])]);
        $itemId = $inv->items()->value('id');
        $this->assertEqualsWithDelta(13.0, $almirah->fresh()->stockOnHand(), 0.001, '20 - 7 sold');

        // 1 of 7: taxable 1,000; CGST 90; SGST 90; note 1,180.
        $res = $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 1180, 'reason' => 'sales_return',
            'lines' => [$itemId => 1],
        ]);

        if ($res->getStatusCode() === 422) {
            $this->assertSame(0, CreditNote::count());

            return;
        }
        $res->assertCreated();
        $cn = CreditNote::findOrFail($res->json('data.id'));
        $this->assertSame(1, $cn->items()->count(), 'the returned line must be recorded on the note');
        $this->assertEqualsWithDelta(14.0, $almirah->fresh()->stockOnHand(), 0.001, 'the returned almirah must be back in stock');
    }

    // ---- DELETE /api/credit-notes/{creditNote} ------------------------------

    public function test_reversing_a_credit_note_over_the_api_restores_the_invoice(): void
    {
        $inv = $this->webIssued($this->owner, $this->patil); // 1,180
        $this->webPay($this->owner, $inv, 500)->assertSessionHasNoErrors();
        $cnId = $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 118, 'reason' => 'deficient_service',
        ])->assertCreated()->json('data.id');
        // 1,180 - 500 paid - 118 credited = 562.
        $this->assertMoney(562, $inv->fresh()->balance);

        $this->apiAs($this->owner)->deleteJson("/api/credit-notes/{$cnId}")
            ->assertOk()->assertJsonPath('message', 'Credit note reversed. Invoice balance restored.');
        $inv->refresh();
        $this->assertNull(CreditNote::find($cnId));
        $this->assertMoney(0, $inv->credited_amount);
        $this->assertMoney(680, $inv->balance); // 1,180 - 500
        $this->assertSame('partially_paid', $inv->status);
        $this->assertSame(1, AuditLog::where('action', 'credit_note.deleted')->count());

        // Someone else's credit note is out of reach.
        $rivalInv = $this->webIssued($this->rivalOwner, $this->rivalCustomer);
        $this->actingAs($this->rivalOwner)->post(route('credit-notes.store', $rivalInv), [
            'credit_note_date' => now()->toDateString(), 'amount' => 100, 'reason' => 'other',
        ])->assertSessionHasNoErrors();
        $rivalNote = CreditNote::where('company_id', $this->rival->id)->firstOrFail();
        $this->apiAs($this->owner)->deleteJson("/api/credit-notes/{$rivalNote->id}")->assertForbidden();
        $this->assertNotNull(CreditNote::find($rivalNote->id));

        // A note inside closed books stays.
        $old = $this->webIssued($this->owner, $this->patil, null, ['invoice_date' => '2026-09-10', 'due_date' => '2026-10-10']);
        $this->actingAs($this->owner)->post(route('credit-notes.store', $old), [
            'credit_note_date' => '2026-09-20', 'amount' => 100, 'reason' => 'other',
        ])->assertSessionHasNoErrors();
        $lockedNote = CreditNote::latest('id')->firstOrFail();
        $this->biz->forceFill(['books_locked_until' => '2026-09-30'])->save();
        $this->apiAs($this->owner)->deleteJson("/api/credit-notes/{$lockedNote->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Books are locked up to 30 Sep 2026. This credit note cannot be reversed.');
        $this->assertNotNull(CreditNote::find($lockedNote->id));
        $this->assertMoney(1080, $old->fresh()->balance);
    }

    public function test_reversing_a_sales_return_over_the_api_takes_the_goods_back_out_of_stock(): void
    {
        // Web sales return of 1 almirah out of 7 puts it back on the shelf.
        // Reversing that note must take it off again - the web destroy() calls
        // StockLedger::reverseCreditNote(); the API destroy() must too, or
        // the shelf shows an almirah that was never returned.
        $almirah = $this->productOf($this->biz, ['track_stock' => true, 'opening_stock' => 20]);
        // 7 x 1,000 = 7,000; CGST 630 + SGST 630; grand 8,260.
        $inv = $this->webIssued($this->owner, $this->patil, [$this->line(1000, 18, 7, ['product_id' => $almirah->id])]);
        $this->assertMoney(8260, $inv->grand_total);
        $this->assertEqualsWithDelta(13.0, $almirah->fresh()->stockOnHand(), 0.001);

        // 1 of 7: taxable 1,000; CGST 630/7 = 90; SGST 90; note 1,180.
        $this->actingAs($this->owner)->post(route('credit-notes.store', $inv), [
            'credit_note_date' => now()->toDateString(), 'reason' => 'sales_return',
            'lines' => [$inv->items()->value('id') => 1],
        ])->assertSessionHasNoErrors();
        $cn = CreditNote::latest('id')->firstOrFail();
        $this->assertMoney(1180, $cn->amount);
        $this->assertEqualsWithDelta(14.0, $almirah->fresh()->stockOnHand(), 0.001, 'the return came back in');

        $this->apiAs($this->owner)->deleteJson("/api/credit-notes/{$cn->id}")->assertOk();
        $this->assertNull(CreditNote::find($cn->id));
        $this->assertSame(0, CreditNoteItem::count(), 'the note lines go with the note');
        $this->assertMoney(8260, $inv->fresh()->balance);

        $this->assertSame(0, StockMovement::where('kind', 'sale_return')->count(),
            'the sale_return stock movement must be removed with the note');
        $this->assertEqualsWithDelta(13.0, $almirah->fresh()->stockOnHand(), 0.001,
            'after the return is reversed only 13 almirahs are on hand (20 - 7)');
    }

    // ---- PDFs and the share link -------------------------------------------

    public function test_invoice_credit_note_and_cash_memo_pdfs_download_over_the_api(): void
    {
        $inv = $this->webIssued($this->owner, $this->patil);

        $pdf = $this->apiAs($this->owner)->get("/api/invoices/{$inv->id}/pdf");
        $this->assertIsPdf($pdf);
        $this->assertStringContainsString('invoice-INV-0001.pdf', (string) $pdf->headers->get('content-disposition'));
        $web = $this->actingAs($this->owner)->get(route('invoices.pdf', $inv));
        $this->assertIsPdf($web);
        $this->assertSame($web->headers->get('content-disposition'), $pdf->headers->get('content-disposition'), 'same file name on both doors');

        $cnId = $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 118, 'reason' => 'other',
        ])->assertCreated()->json('data.id');
        $note = $this->apiAs($this->owner)->get("/api/credit-notes/{$cnId}/pdf");
        $this->assertIsPdf($note);
        $this->assertStringContainsString('credit-note-CRN-0001.pdf', (string) $note->headers->get('content-disposition'));

        // 4 x 125 = 500; 18% = 90 -> CGST 45 + SGST 45; grand 590.
        $memo = $this->apiAs($this->owner)->postJson('/api/cash-memos', [
            'memo_date' => now()->toDateString(), 'seller_name' => 'Ganesh Hardware', 'payment_mode' => 'cash',
            'gst_rate' => 18, 'items' => [['description' => 'Door hinges', 'quantity' => 4, 'rate' => 125]],
        ])->assertCreated();
        $memo->assertJsonPath('data.memo_number', 'CM/26-27/0001');
        $this->assertMoney(590, $memo->json('data.grand_total'));
        $memoPdf = $this->apiAs($this->owner)->get('/api/cash-memos/' . $memo->json('data.id') . '/pdf');
        $this->assertIsPdf($memoPdf);
        $this->assertStringContainsString('cash-memo-CM-26-27-0001.pdf', (string) $memoPdf->headers->get('content-disposition'));

        // Another business's documents are refused.
        $rivalInv = $this->webIssued($this->rivalOwner, $this->rivalCustomer);
        $this->actingAs($this->rivalOwner)->post(route('credit-notes.store', $rivalInv), [
            'credit_note_date' => now()->toDateString(), 'amount' => 100, 'reason' => 'other',
        ])->assertSessionHasNoErrors();
        $rivalNote = CreditNote::where('company_id', $this->rival->id)->firstOrFail();
        $rivalMemoId = $this->apiAs($this->rivalOwner)->postJson('/api/cash-memos', [
            'memo_date' => now()->toDateString(), 'seller_name' => 'Rival Seller', 'payment_mode' => 'cash',
            'items' => [['description' => 'Tape', 'quantity' => 1, 'rate' => 50]],
        ])->assertCreated()->json('data.id');

        $this->apiAs($this->owner)->getJson("/api/invoices/{$rivalInv->id}/pdf")->assertForbidden();
        $this->apiAs($this->owner)->getJson("/api/credit-notes/{$rivalNote->id}/pdf")->assertForbidden();
        $this->apiAs($this->owner)->getJson("/api/cash-memos/{$rivalMemoId}/pdf")->assertForbidden();
    }

    public function test_a_draft_pdf_over_the_api_is_a_clear_refusal_not_a_server_error(): void
    {
        // Api\InvoiceController::pdf() refuses a draft with a JSON 422, but the
        // method is declared to return Illuminate\Http\Response, so PHP throws
        // a TypeError and the phone gets a 500 instead of the message.
        $draft = $this->apiDraft($this->owner, $this->patil, [$this->line()]);
        $this->apiAs($this->owner)->getJson("/api/invoices/{$draft->id}/pdf")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Issue the invoice before downloading the PDF.');
    }

    public function test_the_share_link_opens_without_signing_in(): void
    {
        $inv = $this->webIssued($this->owner, $this->patil); // 1,180 to Patil Stores, phone 98765 00001

        $res = $this->apiAs($this->owner)->getJson("/api/invoices/{$inv->id}/share-link")->assertOk();
        $url = (string) $res->json('url');
        $this->assertStringContainsString('/i/' . $inv->id, $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringStartsWith('https://wa.me/919876500001?text=', (string) $res->json('whatsapp_link'),
            'a bare 10-digit mobile gets the 91 country code');

        // The customer opens it with no account and no session.
        $this->app['auth']->forgetGuards();
        $page = $this->get($url)->assertOk();
        $text = $this->flatten($page->getContent());
        $this->assertStringContainsString('INV-0001', $text);
        $this->assertStringContainsString('1,180', $text);

        // A tampered link is refused: pointing the signature at another
        // business's invoice, or forging the signature itself.
        $rivalInv = $this->webIssued($this->rivalOwner, $this->rivalCustomer);
        $this->app['auth']->forgetGuards();
        $this->get(str_replace('/i/' . $inv->id . '?', '/i/' . $rivalInv->id . '?', $url))->assertForbidden();
        $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature=deadbeef', $url))->assertForbidden();

        // Drafts have nothing to share; someone else's invoice is refused.
        $draft = $this->apiDraft($this->owner, $this->patil, [$this->line()]);
        $this->apiAs($this->owner)->getJson("/api/invoices/{$draft->id}/share-link")
            ->assertStatus(422)->assertJsonPath('message', 'Issue the invoice before sharing it.');
        $this->apiAs($this->owner)->getJson("/api/invoices/{$rivalInv->id}/share-link")->assertForbidden();

        // Once cancelled, the old link no longer presents a payable bill.
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/cancel", ['cancellation_reason' => 'Raised twice'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertStatus(410);
    }

    // ---- GET /api/customers/{customer} and /ledger --------------------------

    public function test_the_customer_ledger_over_the_api_matches_the_web_ledger(): void
    {
        // A: 1 Oct, 1 x 1,000 @ 18% = 1,180 (web).
        $a = $this->webIssued($this->owner, $this->patil, null, ['invoice_date' => '2026-10-01', 'due_date' => '2026-10-31']);
        // 3 Oct: 1,000 received against A (web).
        $this->webPay($this->owner, $a, 1000, ['received_at' => '2026-10-03', 'reference_number' => 'UTR001'])->assertSessionHasNoErrors();
        // B: 5 Oct, 2 x 500 @ 5% = 1,000 + CGST 25 + SGST 25 = 1,050 (phone).
        $b = $this->apiIssue($this->owner, $this->apiDraft($this->owner, $this->patil, [$this->line(500, 5, 2)],
            ['invoice_date' => '2026-10-05', 'due_date' => '2026-11-04']));
        $this->assertMoney(1050, $b->grand_total);
        // 8 Oct: credit note 118 on A (phone): 118/1,180 = 10% -> taxable 100, CGST 9, SGST 9.
        $this->apiAs($this->owner)->postJson("/api/invoices/{$a->id}/credit-notes", [
            'credit_note_date' => '2026-10-08', 'amount' => 118, 'reason' => 'post_sale_discount',
        ])->assertCreated();
        // 10 Oct: 50 received against B (phone).
        $this->apiPay($this->owner, $b, 50, ['received_at' => '2026-10-10'])->assertCreated();
        // Not in the ledger: a draft and a cancelled invoice.
        $this->webDraft($this->owner, $this->patil, [$this->line(99999, 18, 1)]);
        $cancelled = $this->webIssued($this->owner, $this->patil, [$this->line(5000, 18, 1)]);
        $this->apiAs($this->owner)->postJson("/api/invoices/{$cancelled->id}/cancel", ['cancellation_reason' => 'Raised twice'])->assertOk();

        $card = $this->apiAs($this->owner)->getJson("/api/customers/{$this->patil->id}")->assertOk();
        $card->assertJsonPath('data.name', 'Patil Stores')->assertJsonPath('data.state.gst_code', '27');

        $ledger = $this->apiAs($this->owner)->getJson("/api/customers/{$this->patil->id}/ledger")->assertOk();
        // Running balance, worked by hand:
        //   01 Oct INV-0001 +1,180 -> 1,180
        //   03 Oct receipt  -1,000 ->   180
        //   05 Oct INV-0002 +1,050 -> 1,230
        //   08 Oct CRN-0001   -118 -> 1,112
        //   10 Oct receipt     -50 -> 1,062
        $expected = [
            ['2026-10-01', 'invoice', 1180, 0, 1180],
            ['2026-10-03', 'payment', 0, 1000, 180],
            ['2026-10-05', 'invoice', 1050, 0, 1230],
            ['2026-10-08', 'credit_note', 0, 118, 1112],
            ['2026-10-10', 'payment', 0, 50, 1062],
        ];
        $entries = $ledger->json('entries');
        $this->assertCount(5, $entries, 'drafts and cancelled invoices stay out of the ledger');
        foreach ($expected as $i => [$date, $type, $debit, $credit, $balance]) {
            $this->assertSame($date, $entries[$i]['date'], "row {$i} date");
            $this->assertSame($type, $entries[$i]['type'], "row {$i} type");
            $this->assertMoney($debit, $entries[$i]['debit'], "row {$i} debit");
            $this->assertMoney($credit, $entries[$i]['credit'], "row {$i} credit");
            $this->assertMoney($balance, $entries[$i]['balance'], "row {$i} running balance");
        }
        $this->assertSame('INV-0001', $entries[0]['ref']);
        $this->assertSame('UTR001', $entries[1]['ref']);
        $this->assertSame('CRN-0001', $entries[3]['ref']);
        // Totals: invoiced 1,180 + 1,050 = 2,230; received 1,050; credited 118; outstanding 1,062.
        $this->assertMoney(2230, $ledger->json('totals.invoiced'));
        $this->assertMoney(1050, $ledger->json('totals.received'));
        $this->assertMoney(118, $ledger->json('totals.credited'));
        $this->assertMoney(1062, $ledger->json('totals.outstanding'));

        // The web ledger shows the same rows and balances.
        $web = $this->actingAs($this->owner)->get(route('customers.ledger', $this->patil))->assertOk();
        $webEntries = collect($web->viewData('entries'))->values();
        $this->assertSame([1180.0, 180.0, 1230.0, 1112.0, 1062.0], $webEntries->map(fn ($e) => round((float) $e['balance'], 2))->all());
        $this->assertMoney(1062, $web->viewData('totals')['outstanding']);
        $web->assertSee('₹1,062');

        // Another business's customer is out of reach.
        $this->apiAs($this->owner)->getJson("/api/customers/{$this->rivalCustomer->id}")->assertForbidden();
        $this->apiAs($this->owner)->getJson("/api/customers/{$this->rivalCustomer->id}/ledger")->assertForbidden();
    }

    public function test_on_one_day_the_ledger_lists_the_invoice_before_what_settles_it(): void
    {
        // Invoice, credit note and receipt all dated today. A ledger runs in
        // document order: the debit first, then what reduces it. Sorting the
        // day's rows by type name ("credit_note" < "invoice") prints the credit
        // note first and a running balance of -118 before the invoice exists.
        $inv = $this->webIssued($this->owner, $this->patil); // 1,180
        $this->apiAs($this->owner)->postJson("/api/invoices/{$inv->id}/credit-notes", [
            'credit_note_date' => now()->toDateString(), 'amount' => 118, 'reason' => 'other',
        ])->assertCreated();
        $this->apiPay($this->owner, $inv, 1000)->assertCreated();

        $entries = $this->apiAs($this->owner)->getJson("/api/customers/{$this->patil->id}/ledger")->assertOk()->json('entries');
        $this->assertCount(3, $entries);
        $this->assertSame('invoice', $entries[0]['type'], 'the invoice opens the day');
        // 1,180 -> then 1,180 - 118 = 1,062 / 1,180 - 1,000 = 180 -> finally 62.
        $this->assertMoney(1180, $entries[0]['balance']);
        $this->assertMoney(62, $entries[2]['balance']);
        foreach ($entries as $e) {
            $this->assertGreaterThanOrEqual(0, (float) $e['balance'], 'the running balance never dips below zero before the invoice');
        }
    }
}
