<?php

namespace Tests\Feature;

use App\Http\Controllers\InvoiceShareController;
use App\Mail\InvoiceMail;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceShareTest extends TestCase
{
    use RefreshDatabase;

    private function finalizedInvoice(User $user): Invoice
    {
        $company = Company::factory()->recycle($user)->create();
        return Invoice::factory()->recycle($user)->finalized()->create([
            'company_id' => $company->id,
        ]);
    }

    public function test_owner_can_email_a_finalized_invoice(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $this->actingAs($user)->post(route('invoices.share.email', $invoice), [
            'to' => 'customer@example.com',
            'subject' => 'Your invoice',
            'body' => 'Please find attached.',
        ])->assertRedirect();

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            return $mail->hasTo('customer@example.com');
        });
    }

    public function test_email_requires_valid_recipient(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $this->actingAs($user)->post(route('invoices.share.email', $invoice), [
            'to' => 'not-an-email',
            'subject' => 'x',
            'body' => 'x',
        ])->assertSessionHasErrors('to');

        Mail::assertNothingSent();
    }

    public function test_cannot_email_a_draft_invoice(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $invoice = Invoice::factory()->recycle($user)->create(['status' => 'draft']);

        $this->actingAs($user)->post(route('invoices.share.email', $invoice), [
            'to' => 'customer@example.com',
            'subject' => 'x',
            'body' => 'x',
        ])->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_public_signed_url_opens_for_anyone(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $url = InvoiceShareController::makePublicUrl($invoice);

        // Unauthenticated guest follows the link.
        $response = $this->get($url);
        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_public_url_without_signature_is_forbidden(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        // Hitting the route without a valid signature must be rejected by the
        // `signed` middleware.
        $this->get(route('invoices.public', $invoice->id, false))
            ->assertStatus(403);
    }

    public function test_public_url_for_cancelled_invoice_returns_410(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);
        $url = InvoiceShareController::makePublicUrl($invoice);

        $invoice->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Issued in error',
        ])->save();

        // Same signed URL, but the invoice is now cancelled — must refuse.
        $this->get($url)->assertStatus(410);
    }

    public function test_user_cannot_get_public_link_for_another_users_invoice(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobInvoice = $this->finalizedInvoice($bob);

        $this->actingAs($alice)->get(route('invoices.share.link', $bobInvoice))
            ->assertStatus(403);
    }

    public function test_owner_gets_a_usable_public_link_via_json(): void
    {
        $user = User::factory()->create();
        $invoice = $this->finalizedInvoice($user);

        $response = $this->actingAs($user)->get(route('invoices.share.link', $invoice));

        $response->assertStatus(200);
        $response->assertJsonStructure(['url', 'expires_in_days']);
        $this->assertNotEmpty($response->json('url'));
    }
}
