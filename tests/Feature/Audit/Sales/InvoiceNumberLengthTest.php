<?php

namespace Tests\Feature\Audit\Sales;

use Illuminate\Support\Carbon;

/**
 * Rule 46(b): the invoice serial number is at most 16 characters. Custom
 * numbers and the Company settings page enforce that, but two auto-numbering
 * paths do not:
 *  - the setup wizard turns any prefix (up to 10 characters) into
 *    "{prefix}/{FY}/{N}" without the length check, so prefix "ACME" gives
 *    "ACME/2026-27/0001" (17);
 *  - the settings check only looks at number 1, so the default series
 *    "INV/{FY}/{N}" becomes "INV/2026-27/10000" (17) on the 10,000th bill of
 *    the year.
 * Nothing at issue time checks the length.
 */
class InvoiceNumberLengthTest extends SalesAuditCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 11:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function oneLine(): array
    {
        return [['description' => 'Item', 'hsn_sac' => '8471', 'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18]];
    }

    public function test_setup_wizard_prefix_never_yields_a_number_over_16_characters(): void
    {
        $this->company->forceFill(['invoice_number_format' => null])->save();

        $this->actingAs($this->user)->post(route('onboarding.business.save'), [
            'name' => 'Acme Traders', 'state_id' => $this->mh->id, 'country' => 'India',
            'default_currency' => 'INR', 'invoice_prefix' => 'ACME',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->company->refresh()->forceFill(['gstin' => '27ABCDE1234F1Z5'])->save();

        $inv = $this->issue($this->draft($this->oneLine()));

        $this->assertNotNull($inv->invoice_number);
        $this->assertLessThanOrEqual(16, strlen($inv->invoice_number),
            "Rule 46(b) allows 16 characters; issued \"{$inv->invoice_number}\" (" . strlen($inv->invoice_number) . ')');
    }

    public function test_ten_thousandth_invoice_of_the_year_stays_within_16_characters(): void
    {
        $this->company->forceFill([
            'invoice_number_format' => 'INV/{FY}/{N}', 'invoice_number_padding' => 4,
            'invoice_counter' => 9999, 'invoice_counter_fy' => 2026,
        ])->save();

        $inv = $this->issue($this->draft($this->oneLine()));

        $this->assertNotNull($inv->invoice_number);
        $this->assertLessThanOrEqual(16, strlen($inv->invoice_number),
            "Rule 46(b) allows 16 characters; issued \"{$inv->invoice_number}\" (" . strlen($inv->invoice_number) . ')');
    }
}
