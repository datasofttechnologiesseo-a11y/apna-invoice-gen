<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The invoice form's main button used to be "Save and download PDF", which
 * on a draft downloads a PDF numbered "DRAFT · …" that users then sent to
 * customers. The main button is now "Save and issue": it saves the draft and
 * opens the usual "Issue this invoice?" confirmation, so issuing runs every
 * check it always did. The PDF stays available as a preview.
 */
class InvoiceSaveAndIssueTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $state = State::factory()->create(['gst_code' => '27']);
        $this->user = User::factory()->create();
        $company = Company::factory()->recycle($this->user)->create(['state_id' => $state->id, 'invoice_prefix' => 'INV', 'invoice_counter' => 0]);
        $this->user->forceFill(['active_company_id' => $company->id])->save();
        $this->customer = Customer::factory()->recycle($this->user)->recycle($company)->create(['state_id' => $state->id]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Consulting', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 5000, 'gst_rate' => 18]],
        ], $extra);
    }

    public function test_the_form_leads_with_save_and_issue(): void
    {
        $html = $this->actingAs($this->user)->get(route('invoices.create'))->assertOk()->getContent();

        $this->assertStringContainsString("submitForm('issue')", $html);
        $this->assertStringContainsString('Save and issue', $html);
        $this->assertStringContainsString('Preview PDF', $html);
        $this->assertStringNotContainsString('Save and download PDF', $html);
    }

    public function test_save_and_issue_saves_the_draft_and_opens_the_issue_confirmation(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload(['post_save_action' => 'issue']))
            ->assertRedirect()
            ->assertSessionHas('status', 'Draft saved. Check it and issue.');

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame('draft', $invoice->status, 'saving never issues without the confirmation');
        $this->assertNull($invoice->invoice_number);

        // The page opened from "Save and issue" has the confirmation open; a
        // normal visit does not (one more open dialog than on a plain visit).
        $open = 'x-data="{ open: true }"';
        $plain = substr_count($this->actingAs($this->user)->get(route('invoices.show', $invoice))->getContent(), $open);
        $fromSave = $this->actingAs($this->user)->get(route('invoices.show', ['invoice' => $invoice, 'issue' => 1]))
            ->assertOk()->assertSee('Issue this invoice?')->getContent();
        $this->assertSame($plain + 1, substr_count($fromSave, $open));

        // Confirming issues it exactly as the Issue button always did.
        $this->actingAs($this->user)->post(route('invoices.finalize', $invoice))->assertSessionHasNoErrors();
        $this->assertNotSame('draft', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->invoice_number);
    }

    public function test_save_and_issue_on_an_existing_draft(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload());
        $invoice = Invoice::latest('id')->firstOrFail();

        $this->actingAs($this->user)->put(route('invoices.update', $invoice), $this->payload(['post_save_action' => 'issue']))
            ->assertRedirect(route('invoices.show', ['invoice' => $invoice, 'issue' => 1]));
    }

    public function test_preview_pdf_on_a_draft_says_it_is_a_preview(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->payload(['post_save_action' => 'pdf']))
            ->assertSessionHas('status', 'Draft saved. Downloading a preview PDF.');

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame('draft', $invoice->status);
    }
}
