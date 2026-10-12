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
 * Every font in an invoice PDF must be DejaVu, which has the ₹ sign. Semi-bold
 * (font-weight 600) has no DejaVu face, so dompdf fell back to Times-Bold and
 * an item named "Gift voucher ₹500" printed with a "?" for the rupee sign.
 */
class PdfRupeeGlyphTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_pdf_uses_only_fonts_that_have_the_rupee_sign(): void
    {
        $state = State::factory()->create(['gst_code' => '27']);
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id, 'state_id' => $state->id, 'onboarded_at' => now()]);
        $user->forceFill(['active_company_id' => $company->id])->save();
        $customer = Customer::factory()->create(['user_id' => $user->id, 'company_id' => $company->id, 'state_id' => $state->id]);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id, 'invoice_date' => now()->toDateString(),
            'items' => [['description' => 'Gift voucher ₹500', 'hsn_sac' => '998599', 'quantity' => 1, 'rate' => 500, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
        $invoice = Invoice::latest('id')->firstOrFail();

        $pdf = $this->actingAs($user)->get(route('invoices.pdf', $invoice))->assertOk()->getContent();
        preg_match_all('#/BaseFont\s*/(?:[A-Z]{6}\+)?([A-Za-z-]+)#', $pdf, $m);
        $fonts = array_unique($m[1]);

        $this->assertNotEmpty($fonts);
        foreach ($fonts as $font) {
            $this->assertStringStartsWith('DejaVu', $font, "{$font} has no ₹ sign");
        }
    }
}
