<?php

namespace Tests\Feature;

use App\Mail\PaymentReminderMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reminder goes to the business's customer, so its wording has to match
 * the dates: a reminder sent by hand before the due date must not say "due
 * today".
 */
class PaymentReminderMailTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): Invoice
    {
        $state = State::factory()->create(['gst_code' => '27']);
        $user = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $user->id, 'state_id' => $state->id]);
        $customer = Customer::factory()->create(['user_id' => $user->id, 'company_id' => $company->id, 'state_id' => $state->id]);

        return Invoice::factory()->create([
            'user_id' => $user->id, 'company_id' => $company->id, 'customer_id' => $customer->id,
            'invoice_number' => 'INV-0042', 'invoice_date' => '2026-10-12', 'due_date' => '2026-11-10',
        ]);
    }

    public function test_a_reminder_before_the_due_date_gives_the_date_not_today(): void
    {
        $mail = new PaymentReminderMail($this->invoice(), -29);

        $this->assertSame('Payment reminder: invoice INV-0042 is due on 10 Nov 2026', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('is due on <strong>10 Nov 2026</strong>', $html);
        $this->assertStringNotContainsString('due today', $html);
    }

    public function test_due_today_and_overdue_wording(): void
    {
        $invoice = $this->invoice();

        $this->assertSame('Payment reminder: invoice INV-0042 is due today', (new PaymentReminderMail($invoice, 0))->envelope()->subject);
        $this->assertSame('Payment reminder: invoice INV-0042 is 1 day overdue', (new PaymentReminderMail($invoice, 1))->envelope()->subject);

        $late = new PaymentReminderMail($invoice, 12);
        $this->assertSame('Payment reminder: invoice INV-0042 is 12 days overdue', $late->envelope()->subject);
        $this->assertStringContainsString('was due on <strong>10 Nov 2026</strong> and is now <strong>12 days overdue</strong>', $late->render());
    }
}
