<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sales-area QA fixes: a cancelled invoice never looks payable, WhatsApp
 * links carry the country code, the thermal slip prints HSN and signed
 * round-off, and the lists say what GSTR-1 will call the supply.
 */
class SalesDocumentFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->user->id, 'state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z5', 'upi_id' => 'shop@upi',
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'state_id' => $mh->id, 'phone' => '98765 00001',
        ]);
    }

    private function issued(array $attrs = []): Invoice
    {
        $invoice = Invoice::factory()->recycle($this->user)->finalized()->create(array_merge([
            'company_id' => $this->company->id, 'customer_id' => $this->customer->id,
            'subtotal' => 12500, 'total_cgst' => 1125, 'total_sgst' => 1125, 'total_tax' => 2250,
            'grand_total' => 14750, 'balance' => 14750,
        ], $attrs));
        InvoiceItem::factory()->recycle($invoice)->create([
            'description' => 'Steel almirah', 'hsn_sac' => '94032010', 'quantity' => 1, 'unit' => 'NOS',
            'rate' => 12500, 'amount' => 12500, 'gst_rate' => 18, 'cgst_amount' => 1125, 'sgst_amount' => 1125, 'igst_amount' => 0, 'total' => 14750,
        ]);

        return $invoice->fresh(['items', 'customer.state', 'company.state']);
    }

    private function cancel(Invoice $invoice): Invoice
    {
        $invoice->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Wrong customer'])->save();

        return $invoice->fresh(['items', 'customer.state', 'company.state']);
    }

    private function pdfText(Invoice $invoice): string
    {
        $html = view('invoices.pdf', [
            'invoice' => $invoice, 'amountInWords' => 'words', 'style' => 'classic',
            'print' => true, 'copyLabels' => $invoice->copyLabels(1),
        ])->render();

        return preg_replace('/\s+/', ' ', strip_tags($html));
    }

    public function test_a_cancelled_invoice_pdf_is_stamped_and_asks_for_no_payment(): void
    {
        $live = $this->issued();
        $this->assertStringContainsString('Scan & pay', $this->pdfText($live), 'a payable invoice offers the QR');

        $text = $this->pdfText($this->cancel($live));
        $this->assertStringContainsString('CANCELLED', $text);
        $this->assertStringNotContainsString('Scan & pay', $text);
        $this->assertStringNotContainsString('Payment details', $text);
    }

    public function test_a_cancelled_invoice_page_and_print_show_no_qr(): void
    {
        $invoice = $this->cancel($this->issued());

        foreach ([route('invoices.show', $invoice), route('invoices.print', $invoice)] as $url) {
            $this->actingAs($this->user)->get($url)->assertOk()
                ->assertSee('Cancelled')
                ->assertDontSee('Scan to pay')
                ->assertDontSee('Record payment');
        }
    }

    public function test_whatsapp_numbers_get_the_country_code(): void
    {
        $this->assertSame('919876500001', Invoice::whatsAppNumber('98765 00001'));
        $this->assertSame('919876500001', Invoice::whatsAppNumber('09876500001'));
        $this->assertSame('919876500001', Invoice::whatsAppNumber('+91 98765 00001'));
        $this->assertSame('919876500001', Invoice::whatsAppNumber('0091 98765 00001'));
        $this->assertSame('14155550100', Invoice::whatsAppNumber('+1 415 555 0100'));
        $this->assertNull(Invoice::whatsAppNumber('12345'));
        $this->assertNull(Invoice::whatsAppNumber(null));

        $this->assertStringStartsWith('https://wa.me/919876500001?text=', $this->issued()->whatsAppShareLink());

        $quote = Quotation::create([
            'user_id' => $this->user->id, 'company_id' => $this->company->id, 'customer_id' => $this->customer->id,
            'quote_date' => now()->toDateString(), 'status' => 'sent', 'quote_number' => 'QT-0001', 'grand_total' => 100,
        ]);
        $this->assertStringStartsWith('https://wa.me/919876500001?text=', $quote->whatsAppShareLink());
    }

    public function test_thermal_slip_prints_hsn_signed_round_off_and_upi_only_when_owed(): void
    {
        $owed = $this->issued(['round_off' => -0.14]);
        $html = $this->actingAs($this->user)->get(route('invoices.print', $owed) . '?format=thermal')->assertOk()->getContent();
        $this->assertStringContainsString('HSN 94032010', $html);
        $this->assertStringContainsString('-₹0.14', $html);
        $this->assertStringNotContainsString('₹-0.14', $html);
        $this->assertStringContainsString('Pay via UPI', $html);

        $paid = $this->issued(['status' => 'paid', 'paid_amount' => 14750, 'balance' => 0]);
        $this->actingAs($this->user)->get(route('invoices.print', $paid) . '?format=thermal')->assertOk()
            ->assertDontSee('Pay via UPI');

        $cancelled = $this->cancel($this->issued());
        $this->actingAs($this->user)->get(route('invoices.print', $cancelled) . '?format=thermal')->assertOk()
            ->assertSee('CANCELLED')
            ->assertDontSee('Pay via UPI');
    }

    public function test_the_invoice_list_tags_exports_and_uses_status_labels(): void
    {
        $this->issued(['supply_type' => 'export_lut', 'is_interstate' => true]);
        $this->issued();

        $this->actingAs($this->user)->get(route('invoices.index'))->assertOk()
            ->assertSee('Export')
            ->assertSee('Issued')
            ->assertDontSee('>Final<', false);
    }

    public function test_an_export_under_lut_is_a_tax_invoice_in_the_gstr1_csv(): void
    {
        $inv = $this->issued(['supply_type' => 'export_lut', 'is_interstate' => true,
            'total_cgst' => 0, 'total_sgst' => 0, 'total_tax' => 0, 'grand_total' => 12500, 'balance' => 12500]);

        $csv = $this->actingAs($this->user)->get(route('invoices.gstr1', [
            'from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString(),
        ]))->assertOk()->streamedContent();

        $row = collect(explode("\n", $csv))->first(fn ($l) => str_starts_with($l, $inv->invoice_number . ','));
        $this->assertNotNull($row);
        $this->assertStringContainsString('Tax Invoice', $row);
        $this->assertStringNotContainsString('Bill of Supply', $row);
    }

    public function test_the_quotation_form_hsn_header_is_not_the_validity_chip(): void
    {
        $html = $this->actingAs($this->user)->get(route('quotations.create'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/HSN\/SAC<\/span>\s*<a[^>]*>.*?60d/s', $html);
        $this->assertStringNotContainsString('No GST is charged', $html);
    }
}
