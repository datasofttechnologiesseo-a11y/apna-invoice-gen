<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile app's quotation routes, each with a happy path and a refusal,
 * and with the same results as the website for the same input.
 *
 * Standard quote (hand-worked), intra-state Maharashtra -> Maharashtra:
 *   3 x 1,250.50 = 3,751.50 ; CGST 9% = 337.635 -> 337.64 ; SGST 337.64
 *   tax 675.28 ; raw 4,426.78 -> grand total 4,427, round off +0.22
 *   line total 3,751.50 + 675.28 = 4,426.78
 */
class ApiQuotationRoutesTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private Company $biz;
    private User $owner;
    private Customer $customer;
    private Company $rivalBiz;
    private User $rival;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();

        $this->biz = $this->makeBusiness(['quote_prefix' => 'QT', 'quote_counter' => 0]);
        $this->owner = $this->ownerOf($this->biz);
        $this->customer = $this->customerOf($this->biz);

        $this->rivalBiz = $this->makeBusiness(['name' => 'Rival Traders', 'gstin' => '27AAACT2727Q1ZW', 'quote_prefix' => 'RV']);
        $this->rival = $this->ownerOf($this->rivalBiz);
    }

    // -------------------------------------------------------- GET /api/quotations

    public function test_the_app_lists_only_this_businesss_quotations_and_filters_by_status(): void
    {
        $this->getJson('/api/quotations')->assertUnauthorized();

        $draft = $this->apiQuote();
        $sent = $this->apiQuote(['quote_date' => '2026-10-10', 'valid_until' => '2026-11-09']);
        $this->apiAs($this->owner)->postJson("/api/quotations/{$sent->id}/send")->assertOk();
        $theirs = $this->apiQuote([], $this->rival, $this->customerOf($this->rivalBiz, '27', ['name' => 'Rival Customer']));

        $list = $this->apiAs($this->owner)->getJson('/api/quotations')->assertOk();
        // Newest quote date first: 12 Oct (draft) then 10 Oct (sent).
        $this->assertSame([$draft->id, $sent->id], array_map('intval', array_column($list->json('data'), 'id')));
        $this->assertNotContains($theirs->id, array_column($list->json('data'), 'id'), "another business's quote must not be listed");
        $this->assertSame('Patil Stores', $list->json('data.0.customer_name'));
        $this->assertMoney(4427, $list->json('data.0.grand_total'));
        $this->assertSame('Draft #' . $draft->id, $list->json('data.0.display_number'));
        $this->assertSame('QT-0001', $list->json('data.1.quote_number'));

        $onlySent = $this->apiAs($this->owner)->getJson('/api/quotations?status=sent')->assertOk()->json('data');
        $this->assertSame([$sent->id], array_map('intval', array_column($onlySent, 'id')));
    }

    // ------------------------------------------------------- POST /api/quotations

    public function test_creating_a_quotation_in_the_app_prices_it_exactly_like_the_web(): void
    {
        $res = $this->apiAs($this->owner)->postJson('/api/quotations', $this->payload())->assertCreated();
        $res->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.quote_number', null)
            ->assertJsonPath('data.is_interstate', false)
            ->assertJsonPath('data.can.edit', true)
            ->assertJsonPath('data.can.convert', true);
        $this->assertMoney(3751.50, $res->json('data.subtotal'));
        $this->assertMoney(337.64, $res->json('data.total_cgst'));
        $this->assertMoney(337.64, $res->json('data.total_sgst'));
        $this->assertMoney(0, $res->json('data.total_igst'));
        $this->assertMoney(675.28, $res->json('data.total_tax'));
        $this->assertMoney(0.22, $res->json('data.round_off'));
        $this->assertMoney(4427, $res->json('data.grand_total'));
        $this->assertMoney(3751.50, $res->json('data.items.0.amount'));
        $this->assertMoney(4426.78, $res->json('data.items.0.total'));
        $this->assertStringStartsWith('https://wa.me/919876500001?text=', (string) $res->json('data.whatsapp_link'));

        // The same input on the website produces the same quotation, paisa for paisa.
        $this->actingAs($this->owner)->post(route('quotations.store'), $this->payload())->assertSessionHasNoErrors();
        $api = Quotation::findOrFail($res->json('data.id'));
        $web = Quotation::where('company_id', $this->biz->id)->latest('id')->firstOrFail();
        $this->assertNotSame($api->id, $web->id);
        $this->assertSameQuoteMoney($web, $api);
    }

    public function test_creating_a_quotation_in_the_app_refuses_bad_input(): void
    {
        $this->apiAs($this->owner)->postJson('/api/quotations', $this->payload(['items' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('items');
        $this->apiAs($this->owner)->postJson('/api/quotations', $this->payload(['items' => [['description' => 'X', 'quantity' => 1, 'rate' => 100, 'gst_rate' => 17]]]))
            ->assertStatus(422)->assertJsonValidationErrors('items.0.gst_rate');
        $this->apiAs($this->owner)->postJson('/api/quotations', $this->payload(['items' => [['description' => 'X', 'quantity' => 0, 'rate' => 100, 'gst_rate' => 18]]]))
            ->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');
        $this->apiAs($this->owner)->postJson('/api/quotations', $this->payload(['valid_until' => '2026-10-01']))
            ->assertStatus(422)->assertJsonValidationErrors('valid_until');
        // Another business's customer is not this business's to quote.
        $theirCustomer = $this->customerOf($this->rivalBiz, '27', ['name' => 'Rival Customer']);
        $this->apiAs($this->owner)->postJson('/api/quotations', $this->payload(['customer_id' => $theirCustomer->id]))
            ->assertNotFound();

        $this->assertSame(0, Quotation::count());
    }

    public function test_a_quotation_line_cannot_point_at_another_businesss_product(): void
    {
        // The website refuses this (exists:products,id,company_id,{own}); the
        // app's rule is a bare 'integer', so a foreign product id is stored.
        $theirs = $this->productOf($this->rivalBiz, ['name' => 'Rival secret alloy cabinet']);

        $this->actingAs($this->owner)->post(route('quotations.store'), $this->payload(['items' => [
            $this->line(1250.50, 18, 3, ['product_id' => $theirs->id]),
        ]]))->assertSessionHasErrors('items.0.product_id');

        $this->apiAs($this->owner)->postJson('/api/quotations', $this->payload(['items' => [
            $this->line(1250.50, 18, 3, ['product_id' => $theirs->id]),
        ]]))->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
        $this->assertSame(0, Quotation::count());
    }

    public function test_another_businesss_product_name_never_prints_on_a_quote_or_invoice_made_in_the_app(): void
    {
        // Quote and invoice pages print a line's linked product name ahead of
        // its description. A line pointing at another business's product
        // would print THEIR product name on this business's quote, and on
        // the tax invoice it converts into.
        $theirs = $this->productOf($this->rivalBiz, ['name' => 'Rival secret alloy cabinet']);
        $res = $this->apiAs($this->owner)->postJson('/api/quotations', $this->payload(['items' => [
            $this->line(1250.50, 18, 3, ['product_id' => $theirs->id, 'description' => 'Steel almirah']),
        ]]));

        $leaks = [];
        if ($res->status() === 201) {
            $id = (int) $res->json('data.id');
            $quotePage = $this->flatten($this->actingAs($this->owner)->get(route('quotations.show', $id))->assertOk()->getContent());
            if (str_contains($quotePage, 'Rival secret alloy cabinet')) {
                $leaks[] = 'quotation page';
            }
            $this->apiAs($this->owner)->postJson("/api/quotations/{$id}/send")->assertOk();
            $invoiceId = (int) $this->apiAs($this->owner)->postJson("/api/quotations/{$id}/convert")->assertSuccessful()->json('data.id');
            $this->apiAs($this->owner)->postJson("/api/invoices/{$invoiceId}/finalize")->assertOk();
            $invoicePage = $this->flatten($this->actingAs($this->owner)->get(route('invoices.show', $invoiceId))->assertOk()->getContent());
            if (str_contains($invoicePage, 'Rival secret alloy cabinet')) {
                $leaks[] = 'issued tax invoice page';
            }
            if ((int) Invoice::findOrFail($invoiceId)->items()->value('product_id') === $theirs->id) {
                $leaks[] = "invoice line linked to the other business's product #{$theirs->id}";
            }
        } else {
            $res->assertStatus(422);
        }

        $this->assertSame([], $leaks, "another business's product shows on: " . implode(', ', $leaks));
    }

    public function test_a_rate_with_more_than_two_decimals_is_refused_like_the_web(): void
    {
        // Money is to the paisa. The website refuses 1,250.505 (decimal:0,2);
        // the app silently rounds it to 1,250.51 and prices the quote on that.
        $line = [$this->line(1250.505, 18, 3)];
        $this->actingAs($this->owner)->post(route('quotations.store'), $this->payload(['items' => $line]))
            ->assertSessionHasErrors('items.0.rate');
        $this->apiAs($this->owner)->postJson('/api/quotations', $this->payload(['items' => $line]))
            ->assertStatus(422)->assertJsonValidationErrors('items.0.rate');
    }

    // ------------------------------------------- GET /api/quotations/{quotation}

    public function test_the_app_shows_a_quotation_and_refuses_another_businesss(): void
    {
        $q = $this->apiQuote();

        $res = $this->apiAs($this->owner)->getJson("/api/quotations/{$q->id}")->assertOk();
        $res->assertJsonPath('data.id', $q->id)
            ->assertJsonPath('data.customer.name', 'Patil Stores')
            ->assertJsonPath('data.items.0.description', 'Steel almirah')
            ->assertJsonPath('data.valid_until', '2026-11-11')
            ->assertJsonPath('data.days_until_expiry', 30);
        $this->assertMoney(4427, $res->json('data.grand_total'));

        $this->assertContains($this->apiAs($this->rival)->getJson("/api/quotations/{$q->id}")->status(), [403, 404]);
    }

    // ------------------------------------------- PUT /api/quotations/{quotation}

    public function test_the_app_edits_a_draft_and_reprices_it(): void
    {
        $q = $this->apiQuote();

        // 4 x 1,250.50 = 5,002.00 ; CGST 9% = 450.18 ; SGST 450.18 ; tax 900.36
        // raw 5,902.36 -> 5,902, round off -0.36
        $res = $this->apiAs($this->owner)->putJson("/api/quotations/{$q->id}", $this->payload([
            'items' => [$this->line(1250.50, 18, 4)],
            'subject' => 'Revised for 4 almirahs',
        ]))->assertOk();
        $res->assertJsonPath('data.subject', 'Revised for 4 almirahs');
        $q->refresh();
        $this->assertMoney(5002.00, $q->subtotal);
        $this->assertMoney(450.18, $q->total_cgst);
        $this->assertMoney(450.18, $q->total_sgst);
        $this->assertMoney(900.36, $q->total_tax);
        $this->assertMoney(-0.36, $q->round_off);
        $this->assertMoney(5902, $q->grand_total);
        $this->assertCount(1, $q->items, 'the old line is replaced, not kept beside the new one');

        // Bad input and another business are refused, and nothing changes.
        $this->apiAs($this->owner)->putJson("/api/quotations/{$q->id}", $this->payload(['items' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('items');
        $this->assertContains($this->apiAs($this->rival)->putJson("/api/quotations/{$q->id}", $this->payload())->status(), [403, 404]);
        $this->assertMoney(5902, $q->fresh()->grand_total);
    }

    public function test_editing_a_sent_or_converted_quotation_in_the_app_is_refused(): void
    {
        $q = $this->apiQuote();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/send")->assertOk();

        $this->apiAs($this->owner)->putJson("/api/quotations/{$q->id}", $this->payload(['items' => [$this->line(1, 18, 1)]]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'This quotation can no longer be edited. Only drafts can be changed.');

        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/accept")->assertOk();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/convert")->assertSuccessful();
        $this->apiAs($this->owner)->putJson("/api/quotations/{$q->id}", $this->payload(['items' => [$this->line(1, 18, 1)]]))
            ->assertStatus(422);

        $q->refresh();
        $this->assertSame('converted', $q->status);
        $this->assertMoney(4427, $q->grand_total);
        $this->assertMoney(3751.50, $q->items()->sole()->amount);
        // and the website agrees.
        $this->actingAs($this->owner)->put(route('quotations.update', $q), $this->payload())->assertForbidden();
    }

    // ---------------------------------------- DELETE /api/quotations/{quotation}

    public function test_the_app_deletes_only_drafts(): void
    {
        $draft = $this->apiQuote();
        $sent = $this->apiQuote();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$sent->id}/send")->assertOk();

        $this->assertContains($this->apiAs($this->rival)->deleteJson("/api/quotations/{$draft->id}")->status(), [403, 404]);
        $this->assertNotNull(Quotation::find($draft->id));

        $this->apiAs($this->owner)->deleteJson("/api/quotations/{$draft->id}")
            ->assertOk()->assertJsonPath('message', 'Draft quotation deleted.');
        $this->assertNull(Quotation::find($draft->id));

        $this->apiAs($this->owner)->deleteJson("/api/quotations/{$sent->id}")
            ->assertStatus(422)->assertJsonPath('message', 'Only draft quotations can be deleted.');
        $this->assertNotNull(Quotation::find($sent->id), 'a sent quote is kept on record');
    }

    // ------------------------------------- POST /api/quotations/{quotation}/send

    public function test_sending_from_the_app_numbers_the_quote_in_the_same_series_as_the_web(): void
    {
        $a = $this->apiQuote();
        $b = $this->apiQuote();
        $c = $this->apiQuote();

        $this->apiAs($this->owner)->postJson("/api/quotations/{$a->id}/send")
            ->assertOk()->assertJsonPath('data.quote_number', 'QT-0001')->assertJsonPath('data.status', 'sent');
        $this->actingAs($this->owner)->post(route('quotations.send', $b))->assertSessionHasNoErrors();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$c->id}/send")->assertOk()->assertJsonPath('data.quote_number', 'QT-0003');

        $this->assertSame('QT-0002', $b->fresh()->quote_number);
        $this->assertSame(3, (int) $this->biz->fresh()->quote_counter);

        // Sending again is refused and burns no number.
        $this->apiAs($this->owner)->postJson("/api/quotations/{$a->id}/send")
            ->assertStatus(422)->assertJsonPath('message', 'Only drafts can be sent.');
        $this->assertContains($this->apiAs($this->rival)->postJson("/api/quotations/{$b->id}/send")->status(), [403, 404]);
        $this->assertSame('QT-0001', $a->fresh()->quote_number);
        $this->assertSame(3, (int) $this->biz->fresh()->quote_counter);
    }

    public function test_sending_from_the_app_keeps_a_number_the_user_typed(): void
    {
        // On the website, a number typed on the draft is kept when it is sent
        // and the automatic series is left alone (QuotationController::send).
        // The app's send always takes the next auto number, overwriting it.
        $this->actingAs($this->owner)->post(route('quotations.store'), $this->payload(['quote_number' => 'EST/OCT/7']))
            ->assertSessionHasNoErrors();
        $q = Quotation::where('company_id', $this->biz->id)->latest('id')->firstOrFail();
        $this->assertSame('EST/OCT/7', $q->quote_number);

        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/send")->assertOk();

        $this->assertSame('EST/OCT/7', $q->fresh()->quote_number, 'the number the user chose must survive sending from the app');
        $this->assertSame(0, (int) $this->biz->fresh()->quote_counter, 'and no auto number is used up');
    }

    // ------------------------- POST /api/quotations/{quotation}/accept, /decline

    public function test_accept_and_decline_in_the_app_follow_the_web_rules(): void
    {
        $draft = $this->apiQuote();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$draft->id}/accept")
            ->assertStatus(422)->assertJsonPath('message', 'Only sent quotations can be accepted.');
        $this->apiAs($this->owner)->postJson("/api/quotations/{$draft->id}/decline")
            ->assertStatus(422)->assertJsonPath('message', 'Only sent or accepted quotations can be declined.');

        $q = $this->apiQuote();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/send")->assertOk();
        $this->assertContains($this->apiAs($this->rival)->postJson("/api/quotations/{$q->id}/accept")->status(), [403, 404]);
        $this->assertSame('sent', $q->fresh()->status);

        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/accept")
            ->assertOk()->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.can.convert', true);
        $this->assertNotNull($q->fresh()->accepted_at);
        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/accept")->assertStatus(422);

        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/decline", ['decline_reason' => 'Budget cut this quarter'])
            ->assertOk()
            ->assertJsonPath('data.status', 'declined')
            ->assertJsonPath('data.decline_reason', 'Budget cut this quarter')
            ->assertJsonPath('data.can.convert', false);
        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/decline", ['decline_reason' => str_repeat('x', 501)])
            ->assertStatus(422);

        // A converted quote can no longer be declined.
        $c = $this->apiQuote();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$c->id}/send")->assertOk();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$c->id}/accept")->assertOk();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$c->id}/convert")->assertSuccessful();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$c->id}/decline")->assertStatus(422);
        $this->assertSame('converted', $c->fresh()->status);
    }

    // ---------------------------------- POST /api/quotations/{quotation}/convert

    public function test_converting_in_the_app_gives_the_same_invoice_as_the_web(): void
    {
        // Two identical quotes, one taken through the website, one through the app.
        $this->actingAs($this->owner)->post(route('quotations.store'), $this->payload())->assertSessionHasNoErrors();
        $webQuote = Quotation::where('company_id', $this->biz->id)->latest('id')->firstOrFail();
        $this->actingAs($this->owner)->post(route('quotations.send', $webQuote))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('quotations.accept', $webQuote))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('quotations.convert', $webQuote))->assertSessionHasNoErrors();
        $webInvoice = Invoice::findOrFail($webQuote->fresh()->converted_to_invoice_id);

        $appQuote = $this->apiQuote();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$appQuote->id}/send")->assertOk()->assertJsonPath('data.quote_number', 'QT-0002');
        $this->apiAs($this->owner)->postJson("/api/quotations/{$appQuote->id}/accept")->assertOk();
        $res = $this->apiAs($this->owner)->postJson("/api/quotations/{$appQuote->id}/convert")->assertSuccessful();
        $res->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.invoice_number', null)
            ->assertJsonPath('data.invoice_date', '2026-10-12')
            ->assertJsonPath('data.due_date', '2026-11-11');
        $this->assertMoney(4427, $res->json('data.grand_total'));
        $this->assertMoney(4427, $res->json('data.balance'));
        $appInvoice = Invoice::findOrFail($res->json('data.id'));
        $this->assertSame($appInvoice->id, (int) $appQuote->fresh()->converted_to_invoice_id);
        $this->assertSame('converted', $appQuote->fresh()->status);

        foreach (['subtotal', 'total_cgst', 'total_sgst', 'total_igst', 'total_tax', 'round_off', 'grand_total', 'balance'] as $f) {
            $this->assertMoney((float) $webInvoice->$f, $appInvoice->$f, "{$f}: app convert must equal web convert");
        }
        $this->assertSame((bool) $webInvoice->is_interstate, (bool) $appInvoice->is_interstate);
        $this->assertSame((int) $webInvoice->place_of_supply_state_id, (int) $appInvoice->place_of_supply_state_id);
        $w = $webInvoice->items()->sole();
        $a = $appInvoice->items()->sole();
        foreach (['description', 'hsn_sac', 'unit'] as $f) {
            $this->assertSame($w->$f, $a->$f);
        }
        foreach (['quantity', 'rate', 'discount', 'amount', 'gst_rate', 'cgst_amount', 'sgst_amount', 'igst_amount', 'total'] as $f) {
            $this->assertMoney((float) $w->$f, $a->$f, "line {$f}");
        }
        $this->assertMoney(4426.78, $a->total);

        // Issued one on each surface: one gapless series, INV-0001 then INV-0002.
        $this->assertSame('INV-0001', $this->webIssue($this->owner, $webInvoice)->invoice_number);
        $this->apiAs($this->owner)->postJson("/api/invoices/{$appInvoice->id}/finalize")
            ->assertOk()->assertJsonPath('data.invoice_number', 'INV-0002');

        // Converting either quote again is refused on either surface.
        $this->apiAs($this->owner)->postJson("/api/quotations/{$appQuote->id}/convert")->assertStatus(422);
        $this->apiAs($this->owner)->postJson("/api/quotations/{$webQuote->id}/convert")->assertStatus(422);
        $this->actingAs($this->owner)->post(route('quotations.convert', $appQuote))->assertStatus(422);
        $this->assertContains($this->apiAs($this->rival)->postJson("/api/quotations/{$appQuote->id}/convert")->status(), [403, 404]);
        $this->assertSame(2, Invoice::where('company_id', $this->biz->id)->count());
    }

    public function test_a_declined_quotation_cannot_be_converted_in_the_app(): void
    {
        $q = $this->apiQuote();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/send")->assertOk();
        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/decline", ['decline_reason' => 'Went elsewhere'])->assertOk();

        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/convert")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This quotation is already converted or declined, so it cannot be converted.');
        $this->assertSame(0, Invoice::count());
        $this->assertSame('declined', $q->fresh()->status);
        $this->assertNull($q->fresh()->converted_to_invoice_id);
    }

    // ------------------------------ GET /api/quotations/{quotation}/share-link

    public function test_the_share_link_from_the_app_is_signed_and_opens_for_the_customer(): void
    {
        $q = $this->apiQuote();
        $this->apiAs($this->owner)->getJson("/api/quotations/{$q->id}/share-link")
            ->assertStatus(422)->assertJsonPath('message', 'Send the quotation before sharing it.');

        $this->apiAs($this->owner)->postJson("/api/quotations/{$q->id}/send")->assertOk();
        $this->assertContains($this->apiAs($this->rival)->getJson("/api/quotations/{$q->id}/share-link")->status(), [403, 404]);

        $res = $this->apiAs($this->owner)->getJson("/api/quotations/{$q->id}/share-link")->assertOk();
        $url = (string) $res->json('url');
        $this->assertStringContainsString('/q/' . $q->id . '?', $url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('signature=', $url);

        $wa = (string) $res->json('whatsapp_link');
        $this->assertStringStartsWith('https://wa.me/919876500001?text=', $wa);
        $text = rawurldecode((string) parse_url($wa, PHP_URL_QUERY));
        $this->assertStringContainsString('Sharing Quotation QT-0001 for ₹4,427.', $text);
        $this->assertStringContainsString('Valid until: 11 Nov 2026.', $text);
        $this->assertStringContainsString('/q/' . $q->id . '?', $text);

        // The customer opens it without an account.
        $this->app['auth']->forgetGuards();
        $page = $this->flatten($this->get($url)->assertOk()->getContent());
        $this->assertStringContainsString('Quotation no. QT-0001', $page);
        $this->assertStringContainsString('Quoted total ₹4,427', $page);
        // A tampered signature is refused.
        $tampered = preg_replace_callback('/signature=([0-9a-f])/', fn ($m) => 'signature=' . ($m[1] === 'a' ? 'b' : 'a'), $url);
        $this->get($tampered)->assertForbidden();
    }

    // ---------------------------------------------------------------- helpers

    private function payload(array $over = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'quote_date' => '2026-10-12',
            'valid_until' => '2026-11-11',
            'subject' => 'Supply of steel almirahs',
            'currency' => 'INR',
            'style' => 'classic',
            'items' => [$this->line(1250.50, 18, 3)],
        ], $over);
    }

    private function apiQuote(array $over = [], ?User $as = null, ?Customer $customer = null): Quotation
    {
        $as ??= $this->owner;
        $payload = $this->payload($over);
        if ($customer) {
            $payload['customer_id'] = $customer->id;
        }
        $res = $this->apiAs($as)->postJson('/api/quotations', $payload)->assertCreated();

        return Quotation::findOrFail($res->json('data.id'));
    }

    private function assertSameQuoteMoney(Quotation $web, Quotation $api): void
    {
        foreach (['subtotal', 'total_cgst', 'total_sgst', 'total_igst', 'total_tax', 'round_off', 'grand_total'] as $f) {
            $this->assertMoney((float) $web->$f, $api->$f, "{$f}: app must equal web");
        }
        $this->assertMoney(4427, $api->grand_total);
        $this->assertSame((bool) $web->is_interstate, (bool) $api->is_interstate);
        $w = $web->items()->sole();
        $a = $api->items()->sole();
        foreach (['quantity', 'rate', 'discount', 'amount', 'gst_rate', 'cgst_amount', 'sgst_amount', 'igst_amount'] as $f) {
            $this->assertMoney((float) $w->$f, $a->$f, "line {$f}: app must equal web");
        }
        $this->assertSame($w->description, $a->description);
        $this->assertSame($w->hsn_sac, $a->hsn_sac);
    }
}
