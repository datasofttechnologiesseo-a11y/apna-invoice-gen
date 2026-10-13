<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\State;
use App\Models\User;
use Database\Seeders\StateSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Shared set-up for the end-to-end workflow journeys and the route-coverage
 * probes. Everything a test asserts about money is worked out by hand in the
 * test itself; these helpers only build fixtures and drive the real routes.
 *
 * Conventions:
 *  - "Today" is Monday 12 Oct 2026, 11:00 IST (FY 2026-27, period 10-2026).
 *  - The real state master is seeded (StateSeeder), so GST codes are real.
 *  - Home business: Maharashtra (27), GSTIN 27ABCDE1234F1Z0 (valid checksum).
 *  - Valid test GSTINs (checksums computed by hand with the mod-36 rule):
 *      27ABCDE1234F1Z0  27AAACT2727Q1ZW  27PQRST5678K1Z2
 *      29ABCDE1234F1ZW  29VWXYZ9876L1ZI  07LMNOP4321Q1Z6  24ABCDE1234F1Z6
 *  - Always call actingAs()/apiAs() before every request: the team
 *    middleware swaps the guard's user to the business owner for a member's
 *    request, so a bare follow-up request may run as the wrong person.
 */
trait WorkflowFixtures
{
    protected function bootWorkflow(): void
    {
        $this->travelTo(Carbon::parse('2026-10-12 11:00:00', 'Asia/Kolkata'));
        $this->seed(StateSeeder::class);
    }

    protected function state(string $gstCode): State
    {
        return State::where('gst_code', $gstCode)->firstOrFail();
    }

    /** A signed-up, verified owner with one onboarded GST-registered business. */
    protected function makeBusiness(array $company = [], array $user = []): Company
    {
        $owner = User::factory()->create($user);
        $biz = Company::factory()->create(array_merge([
            'user_id' => $owner->id,
            'name' => 'Sharma Traders',
            'state_id' => $this->state('27')->id,
            'gstin' => '27ABCDE1234F1Z0',
            'address_line1' => '12 MG Road',
            'city' => 'Pune',
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
            'invoice_number_padding' => 4,
            'onboarded_at' => now(),
        ], $company));
        $owner->forceFill(['active_company_id' => $biz->id])->save();

        return $biz;
    }

    protected function ownerOf(Company $company): User
    {
        return User::findOrFail($company->user_id);
    }

    protected function customerOf(Company $company, string $gstCode = '27', array $extra = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'user_id' => $company->user_id,
            'company_id' => $company->id,
            'name' => 'Patil Stores',
            'state_id' => $this->state($gstCode)->id,
            'gstin' => null,
            'email' => 'accounts@patilstores.example',
            'phone' => '9876500001',
            'country' => 'India',
        ], $extra));
    }

    protected function productOf(Company $company, array $extra = []): Product
    {
        return Product::create(array_merge([
            'user_id' => $company->user_id,
            'company_id' => $company->id,
            'name' => 'Steel almirah',
            'kind' => 'goods',
            'hsn_sac' => '94031010',
            'unit' => 'NOS',
            'rate' => 1000,
            'gst_rate' => 18,
            'is_active' => true,
        ], $extra));
    }

    /** One invoice line as the web form posts it. Default: 1 x 1,000 @ 18%. */
    protected function line(float $rate = 1000, float $gst = 18, float $qty = 1, array $extra = []): array
    {
        return array_merge([
            'description' => 'Steel almirah',
            'hsn_sac' => '94031010',
            'quantity' => $qty,
            'unit' => 'NOS',
            'rate' => $rate,
            'gst_rate' => $gst,
        ], $extra);
    }

    /** Save a draft through the web form as $user. */
    protected function webDraft(User $user, Customer $customer, ?array $items = null, array $over = []): Invoice
    {
        $before = (int) (Invoice::max('id') ?? 0);
        $date = $over['invoice_date'] ?? now()->toDateString();
        $this->actingAs($user)->post(route('invoices.store'), array_merge([
            'customer_id' => $customer->id,
            'invoice_date' => $date,
            'due_date' => $over['due_date'] ?? Carbon::parse($date)->addDays(30)->toDateString(),
            'items' => $items ?? [$this->line()],
        ], $over))->assertSessionHasNoErrors();

        return Invoice::where('id', '>', $before)->latest('id')->firstOrFail();
    }

    protected function webIssue(User $user, Invoice $invoice): Invoice
    {
        $this->actingAs($user)->post(route('invoices.finalize', $invoice))->assertSessionHasNoErrors();
        $fresh = $invoice->fresh();
        $this->assertNotSame('draft', $fresh->status, 'the draft should have been issued: ' . (string) session('error'));

        return $fresh;
    }

    protected function webIssued(User $user, Customer $customer, ?array $items = null, array $over = []): Invoice
    {
        return $this->webIssue($user, $this->webDraft($user, $customer, $items, $over));
    }

    protected function webPay(User $user, Invoice $invoice, float $amount, array $extra = []): TestResponse
    {
        return $this->actingAs($user)->post(route('invoices.payments', $invoice), array_merge([
            'amount' => $amount,
            'method' => 'upi',
            'received_at' => now()->toDateString(),
        ], $extra));
    }

    protected function latestPayment(): Payment
    {
        return Payment::latest('id')->firstOrFail();
    }

    /** Sanctum as $user, fresh for every request (the team middleware swaps users). */
    protected function apiAs(User $user): static
    {
        Sanctum::actingAs($user->fresh());

        return $this;
    }

    /** Exact to the paisa (a sub-paisa delta only absorbs float noise). */
    protected function assertMoney(float $expected, mixed $actual, string $message = ''): void
    {
        $this->assertEqualsWithDelta($expected, (float) $actual, 0.001, $message);
    }

    /** Body of any download, streamed or not. */
    protected function body(TestResponse $response): string
    {
        $base = $response->baseResponse;
        if ($base instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return (string) $response->streamedContent();
        }
        if ($base instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return (string) file_get_contents($base->getFile()->getPathname());
        }

        return (string) $response->getContent();
    }

    /** CSV download -> rows (BOM stripped). */
    protected function csvRows(TestResponse $response): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $this->body($response));
        $rows = [];
        foreach (preg_split('/\r\n|\n|\r/', trim($text)) as $l) {
            $rows[] = str_getcsv($l);
        }

        return $rows;
    }

    /** HTML -> one line of visible text. */
    protected function flatten(string $html): string
    {
        $html = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = preg_replace('/<[^>]*>/', ' ', $html);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    protected function assertIsPdf(TestResponse $response): void
    {
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $this->body($response), 'expected a PDF file');
    }
}
