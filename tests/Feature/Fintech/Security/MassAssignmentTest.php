<?php

namespace Tests\Feature\Fintech\Security;

use App\Models\CashMemo;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\PurchasePayment;
use App\Models\Quotation;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\TestCase;

/**
 * Every create/update route, web and API, is sent the fields a user must
 * never set: company_id / user_id pointing at another business, status,
 * paid_amount, balance, grand_total, credited_amount, document numbers,
 * is_admin / is_super_admin, lifetime_free_at, guide_completed_at and
 * friends. The models' $fillable lists are wide (Invoice and Company accept
 * totals and counters), so the only thing standing between a crafted POST
 * and the books is each controller passing validated fields only.
 *
 * Expected values are worked by hand: a line of 1 x 100 at 18% is
 * 100 + 9 CGST + 9 SGST = 118.
 */
class MassAssignmentTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;

    private array $a;
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
        $this->a = $this->buildBusiness('A');
        $this->b = $this->buildBusiness('B');
    }

    private function owner(): User
    {
        return $this->a['owner']->fresh();
    }

    /** The forbidden fields, aimed at business B where an id is involved. */
    private function extras(array $except = []): array
    {
        $b = $this->b;

        return array_diff_key([
            'company_id' => $b['company']->id, 'user_id' => $b['owner']->id, 'status' => 'paid', 'paid_amount' => 99999,
            'balance' => 0, 'invoice_number' => 'HACK-1', 'grand_total' => 1, 'subtotal' => 1, 'total_tax' => 0, 'total_cgst' => 0,
            'total_sgst' => 0, 'credited_amount' => 99999, 'is_admin' => 1, 'is_super_admin' => 1,
            'lifetime_free_at' => '2026-01-01 00:00:00', 'guide_completed_at' => '2026-01-01 00:00:00',
            'finalized_at' => '2026-01-01 00:00:00', 'receipt_number' => 'HACK-R', 'credit_note_number' => 'HACK-C',
            'quote_number_override' => 'HACK-Q', 'converted_to_invoice_id' => $b['invoiceOpen']->id, 'accepted_at' => '2026-01-01 00:00:00',
            'expense_id' => $b['expense']->id, 'cash_memo_id' => $b['cashMemo']->id, 'purchase_bill_id' => $b['purchaseBill']->id,
            'invoice_id' => $b['invoiceOpen']->id, 'vendor_id_override' => $b['vendor']->id, 'token' => 'attacker-known-token',
            'invoice_counter' => 500, 'receipt_counter' => 500, 'credit_note_counter' => 500, 'active_company_id' => $b['company']->id,
            'email_verified_at' => '2026-01-01 00:00:00', 'phone_verified_at' => '2026-01-01 00:00:00', 'referral_code' => 'HACKER',
            'referred_by_user_id' => $b['owner']->id, 'taxable_value' => 1, 'amount_in_words' => 'One rupee', 'erased_at' => null,
            'onboarded_at' => '2020-01-01 00:00:00', 'logo_path' => '../../.env', 'signature_path' => '../../.env',
        ], array_flip($except));
    }

    private function line(): array
    {
        return ['description' => 'Line', 'hsn_sac' => '8471', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 100, 'gst_rate' => 18];
    }

    private function assertOwnedByA($model, string $what): void
    {
        $this->assertSame($this->a['company']->id, (int) $model->company_id, "{$what}: company_id taken from the request");
        if (array_key_exists('user_id', $model->getAttributes())) {
            $this->assertSame($this->a['owner']->id, (int) $model->user_id, "{$what}: user_id taken from the request");
        }
    }

    // ------------------------------------------------------------------ web

    public function test_web_customer_and_item_forms_ignore_ownership_fields(): void
    {
        $base = ['name' => 'MA Customer', 'state_id' => $this->a['company']->state_id, 'country' => 'India'];
        $this->actingAs($this->owner())->post(route('customers.store'), $base + $this->extras())->assertSessionHasNoErrors();
        $this->assertOwnedByA(Customer::where('name', 'MA Customer')->sole(), 'customers.store');

        $this->actingAs($this->owner())->postJson(route('customers.quick-create'), ['name' => 'MA Quick'] + $base + $this->extras())->assertCreated();
        $this->assertOwnedByA(Customer::where('name', 'MA Quick')->sole(), 'customers.quick-create');

        $c = $this->a['customer'];
        $this->actingAs($this->owner())->put(route('customers.update', $c), ['name' => 'MA Renamed'] + $base + $this->extras())->assertSessionHasNoErrors();
        $this->assertOwnedByA($c->fresh(), 'customers.update');

        $item = ['name' => 'MA Item', 'kind' => 'goods', 'hsn_sac' => '8471', 'unit' => 'NOS', 'rate' => 10, 'gst_rate' => 18];
        $this->actingAs($this->owner())->post(route('products.store'), $item + $this->extras())->assertSessionHasNoErrors();
        $this->assertOwnedByA(Product::where('name', 'MA Item')->sole(), 'products.store');
        $this->actingAs($this->owner())->postJson(route('products.quick-create'), ['name' => 'MA Quick Item'] + $item + $this->extras())->assertSuccessful();
        $this->assertOwnedByA(Product::where('name', 'MA Quick Item')->sole(), 'products.quick-create');
        $this->actingAs($this->owner())->put(route('products.update', $this->a['productUnused']), $item + $this->extras())->assertSessionHasNoErrors();
        $this->assertOwnedByA($this->a['productUnused']->fresh(), 'products.update');

        $this->actingAs($this->owner())->post(route('products.stock.adjust', $this->a['product']), [
            'direction' => 'in', 'quantity' => 1, 'moved_on' => '2026-09-29', 'reason' => 'MA', 'product_id' => $this->b['product']->id,
        ] + $this->extras())->assertSessionHasNoErrors();
        $move = StockMovement::where('note', 'MA')->orWhere('kind', 'adjustment')->latest('id')->firstOrFail();
        $this->assertSame($this->a['product']->id, (int) $move->product_id);
        $this->assertSame($this->a['company']->id, (int) $move->company_id);
    }

    public function test_web_invoice_create_and_edit_compute_totals_and_status_themselves(): void
    {
        // invoice_number (custom numbering) and paid_amount (advance) are real
        // fields of this form, so they are left out of the extras here.
        $extras = $this->extras(['invoice_number', 'paid_amount']);
        $this->actingAs($this->owner())->post(route('invoices.store'), [
            'customer_id' => $this->a['customer']->id, 'invoice_date' => '2026-09-29', 'items' => [$this->line()],
        ] + $extras)->assertSessionHasNoErrors();
        $inv = Invoice::latest('id')->firstOrFail();
        $this->assertOwnedByA($inv, 'invoices.store');
        $this->assertSame('draft', $inv->status);
        $this->assertNull($inv->finalized_at);
        $this->assertNull($inv->invoice_number);
        $this->assertEqualsWithDelta(118.00, (float) $inv->grand_total, 0.001);
        $this->assertEqualsWithDelta(118.00, (float) $inv->balance, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $inv->credited_amount, 0.001);
        $this->assertEqualsWithDelta(18.00, (float) $inv->items()->sum('cgst_amount') * 2, 0.001);

        $this->actingAs($this->owner())->put(route('invoices.update', $inv), [
            'customer_id' => $this->a['customer']->id, 'invoice_date' => '2026-09-29', 'items' => [$this->line()],
        ] + $extras)->assertSessionHasNoErrors();
        $inv->refresh();
        $this->assertOwnedByA($inv, 'invoices.update');
        $this->assertSame('draft', $inv->status);
        $this->assertEqualsWithDelta(118.00, (float) $inv->grand_total, 0.001);

        // An advance larger than the invoice is refused, not booked.
        $this->actingAs($this->owner())->put(route('invoices.update', $inv), [
            'customer_id' => $this->a['customer']->id, 'invoice_date' => '2026-09-29', 'items' => [$this->line()], 'paid_amount' => 500,
        ])->assertSessionHasErrors();
        $this->assertEqualsWithDelta(0.00, (float) $inv->fresh()->paid_amount, 0.001);
    }

    public function test_an_issued_invoice_soft_edit_cannot_change_money_customer_or_number(): void
    {
        $inv = $this->a['invoiceOpen']; // INV-0001, 2,360
        $this->actingAs($this->owner())->put(route('invoices.update', $inv), [
            'notes' => 'Thanks', 'customer_id' => $this->a['customerUnused']->id, 'items' => [$this->line()],
        ] + $this->extras())->assertSessionHasNoErrors();
        $inv->refresh();
        $this->assertSame('INV-0001', $inv->invoice_number);
        $this->assertSame('final', $inv->status);
        $this->assertSame($this->a['customer']->id, (int) $inv->customer_id);
        $this->assertEqualsWithDelta(2360.00, (float) $inv->grand_total, 0.001);
        $this->assertEqualsWithDelta(2360.00, (float) $inv->balance, 0.001);
        $this->assertSame('Thanks', $inv->notes);
    }

    public function test_web_receipts_and_credit_notes_take_their_number_and_amounts_from_the_books(): void
    {
        $inv = $this->a['invoiceOpen'];
        $this->actingAs($this->owner())->post(route('invoices.payments', $inv), [
            'amount' => 100, 'method' => 'cash', 'received_at' => '2026-09-29',
        ] + $this->extras())->assertSessionHasNoErrors();
        $p = Payment::where('invoice_id', $inv->id)->sole();
        $this->assertOwnedByA($p, 'invoices.payments');
        $this->assertSame('RCPT-0002', $p->receipt_number);
        $this->assertEqualsWithDelta(100.00, (float) $p->amount, 0.001);
        // 2,360 - 100 = 2,260.
        $this->assertEqualsWithDelta(2260.00, (float) $inv->fresh()->balance, 0.001);
        $this->assertSame('partially_paid', $inv->fresh()->status);

        $this->actingAs($this->owner())->post(route('credit-notes.store', $inv), [
            'credit_note_date' => '2026-09-29', 'amount' => 236, 'reason' => 'post_sale_discount',
        ] + $this->extras())->assertSessionHasNoErrors();
        $cn = CreditNote::where('invoice_id', $inv->id)->sole();
        $this->assertOwnedByA($cn, 'credit-notes.store');
        $this->assertSame('CRN-0002', $cn->credit_note_number);
        // 236 on a 2,000 + 180 + 180 invoice: taxable 200, CGST 18, SGST 18.
        $this->assertEqualsWithDelta(200.00, (float) $cn->taxable_value, 0.001);
        $this->assertEqualsWithDelta(18.00, (float) $cn->total_cgst, 0.001);
        $this->assertEqualsWithDelta(236.00, (float) $inv->fresh()->credited_amount, 0.001);
    }

    public function test_web_quotation_create_and_edit_ignore_status_and_conversion_fields(): void
    {
        $this->actingAs($this->owner())->post(route('quotations.store'), [
            'customer_id' => $this->a['customer']->id, 'quote_date' => '2026-09-29', 'items' => [$this->line()],
        ] + $this->extras())->assertSessionHasNoErrors();
        $q = Quotation::latest('id')->firstOrFail();
        $this->assertOwnedByA($q, 'quotations.store');
        $this->assertSame('draft', $q->status);
        $this->assertNull($q->converted_to_invoice_id);
        $this->assertNull($q->accepted_at);
        $this->assertEqualsWithDelta(118.00, (float) $q->grand_total, 0.001);

        $this->actingAs($this->owner())->put(route('quotations.update', $q), [
            'customer_id' => $this->a['customer']->id, 'quote_date' => '2026-09-29', 'items' => [$this->line()],
        ] + $this->extras())->assertSessionHasNoErrors();
        $q->refresh();
        $this->assertSame('draft', $q->status);
        $this->assertNull($q->converted_to_invoice_id);
        $this->assertOwnedByA($q, 'quotations.update');
    }

    public function test_web_expense_cash_memo_purchase_and_vendor_forms_ignore_ownership_and_links(): void
    {
        $this->actingAs($this->owner())->post(route('finance.expenses.store'), [
            'entry_date' => '2026-09-29', 'category' => 'rent', 'description' => 'MA rent', 'amount' => 100, 'payment_method' => 'bank',
        ] + $this->extras())->assertSessionHasNoErrors();
        $e = Expense::where('description', 'MA rent')->sole();
        $this->assertOwnedByA($e, 'finance.expenses.store');
        $this->assertNull($e->cash_memo_id);
        $this->assertNull($e->purchase_bill_id);
        $this->actingAs($this->owner())->patch(route('finance.expenses.update', $e), [
            'entry_date' => '2026-09-29', 'category' => 'rent', 'description' => 'MA rent', 'amount' => 100,
        ] + $this->extras())->assertSessionHasNoErrors();
        $this->assertOwnedByA($e->fresh(), 'finance.expenses.update');
        $this->assertNull($e->fresh()->purchase_bill_id);

        $this->actingAs($this->owner())->post(route('finance.cash-memos.store'), [
            'memo_date' => '2026-09-29', 'seller_name' => 'MA seller', 'payment_mode' => 'cash', 'gst_rate' => 18, 'itc_eligible' => 1,
            'items' => [['description' => 'Tape', 'quantity' => 2, 'rate' => 100]],
        ] + $this->extras())->assertSessionHasNoErrors();
        $m = CashMemo::where('seller_name', 'MA seller')->sole();
        $this->assertOwnedByA($m, 'finance.cash-memos.store');
        // 2 x 100 = 200 + 18% = 236.
        $this->assertEqualsWithDelta(236.00, (float) $m->grand_total, 0.001);
        $this->assertNotSame($this->b['expense']->id, (int) $m->expense_id);
        $this->assertSame($this->a['company']->id, (int) Expense::findOrFail($m->expense_id)->company_id);

        $this->actingAs($this->owner())->post(route('vendors.store'), ['name' => 'MA vendor'] + $this->extras())->assertSessionHasNoErrors();
        $v = Vendor::where('name', 'MA vendor')->sole();
        $this->assertOwnedByA($v, 'vendors.store');
        $this->actingAs($this->owner())->put(route('vendors.update', $v), ['name' => 'MA vendor'] + $this->extras())->assertSessionHasNoErrors();
        $this->assertOwnedByA($v->fresh(), 'vendors.update');

        $this->actingAs($this->owner())->post(route('purchases.store'), [
            'vendor_id' => $v->id, 'bill_number' => 'MA-1', 'bill_date' => '2026-09-29', 'items' => [$this->line()],
        ] + $this->extras())->assertSessionHasNoErrors();
        $bill = PurchaseBill::where('bill_number', 'MA-1')->sole();
        $this->assertOwnedByA($bill, 'purchases.store');
        $this->assertSame('unpaid', $bill->status);
        $this->assertEqualsWithDelta(0.00, (float) $bill->paid_amount, 0.001);
        $this->assertEqualsWithDelta(118.00, (float) $bill->grand_total, 0.001);
        $this->assertEqualsWithDelta(118.00, (float) $bill->balance, 0.001);
        $this->assertNotSame($this->b['expense']->id, (int) $bill->expense_id);

        $this->actingAs($this->owner())->post(route('purchases.payments.store', $bill), [
            'amount' => 18, 'method' => 'cash', 'paid_on' => '2026-09-29',
        ] + $this->extras())->assertSessionHasNoErrors();
        $pp = PurchasePayment::where('purchase_bill_id', $bill->id)->sole();
        $this->assertOwnedByA($pp, 'purchases.payments.store');
        $this->assertEqualsWithDelta(100.00, (float) $bill->fresh()->balance, 0.001);
    }

    public function test_web_company_settings_and_onboarding_cannot_set_owner_or_counters(): void
    {
        $company = $this->a['company'];
        $settings = ['name' => 'SECRET-FIRM-A', 'state_id' => $company->state_id, 'country' => 'India', 'default_currency' => 'INR',
            'invoice_prefix' => 'INV', 'invoice_number_padding' => 4, 'bank_ifsc' => $company->bank_ifsc, 'upi_id' => $company->upi_id];
        $this->actingAs($this->owner())->patch(route('companies.update', $company), $settings + $this->extras())->assertSessionHasNoErrors();
        $company->refresh();
        $this->assertSame($this->a['owner']->id, (int) $company->user_id);
        $this->assertSame(4, (int) $company->invoice_counter, 'INV-0001..0004 issued in the fixture');
        $this->assertSame(1, (int) $company->receipt_counter);
        $this->assertSame(1, (int) $company->credit_note_counter);
        $this->assertNotSame('../../.env', $company->logo_path);

        $this->actingAs($this->owner())->post(route('onboarding.business.save'), $settings + $this->extras())->assertSessionHasNoErrors();
        $company->refresh();
        $this->assertSame($this->a['owner']->id, (int) $company->user_id);
        $this->assertSame(4, (int) $company->invoice_counter);

        $this->actingAs($this->owner())->post(route('onboarding.customer.save'), [
            'name' => 'MA onboard customer', 'state_id' => $company->state_id, 'country' => 'India', 'more' => 1,
        ] + $this->extras());
        $this->assertOwnedByA(Customer::where('name', 'MA onboard customer')->sole(), 'onboarding.customer.save');

        $this->actingAs($this->owner())->post(route('companies.store'), ['name' => 'MA Second Firm'] + $settings + $this->extras())
            ->assertSessionHasNoErrors();
        $new = Company::where('name', 'MA Second Firm')->sole();
        $this->assertSame($this->a['owner']->id, (int) $new->user_id);
        $this->assertSame(0, (int) $new->invoice_counter);
        $this->assertSame(0, (int) $new->receipt_counter);
    }

    public function test_the_profile_form_cannot_grant_admin_lifetime_free_or_skip_verification(): void
    {
        $user = $this->owner();
        $user->forceFill(['email_verified_at' => '2026-09-01 10:00:00', 'phone_verified_at' => null])->save();
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Owner A', 'email' => $user->email] + $this->extras())
            ->assertSessionHasNoErrors();
        $u = $user->fresh();
        $this->assertFalse((bool) $u->is_super_admin);
        $this->assertNull($u->lifetime_free_at);
        $this->assertNull($u->guide_completed_at);
        $this->assertNull($u->phone_verified_at);
        $this->assertNull($u->referred_by_user_id);
        $this->assertNotSame('HACKER', $u->referral_code);
        $this->assertSame($this->a['company']->id, (int) $u->active_company_id);
        $this->assertSame('2026-09-01 10:00:00', $u->email_verified_at->format('Y-m-d H:i:s'));
    }

    public function test_team_invite_and_role_change_cannot_pick_the_business_token_or_acceptance(): void
    {
        $this->actingAs($this->owner())->post(route('team.invite'), ['email' => 'ma.member@team.test', 'role' => 'staff'] + $this->extras())
            ->assertSessionHasNoErrors();
        $m = CompanyMember::where('email', 'ma.member@team.test')->sole();
        $this->assertSame($this->a['company']->id, (int) $m->company_id);
        $this->assertNull($m->user_id);
        $this->assertNull($m->accepted_at);
        $this->assertNotSame('attacker-known-token', $m->token);
        $this->assertGreaterThanOrEqual(40, strlen($m->token));

        $this->actingAs($this->owner())->patch(route('team.update', $m), ['role' => 'accountant'] + $this->extras())->assertSessionHasNoErrors();
        $m->refresh();
        $this->assertSame('accountant', $m->role);
        $this->assertSame($this->a['company']->id, (int) $m->company_id);
        $this->assertNull($m->accepted_at);
        $this->assertNull($m->user_id);
    }

    public function test_registration_cannot_grant_admin_lifetime_free_or_verification(): void
    {
        auth()->logout();
        \Illuminate\Support\Facades\Mail::fake();
        $this->post('/register', [
            'name' => 'New Person', 'email' => 'new.person@example.test', 'phone' => '9876501234',
            'password' => 'S3cure-pass-word', 'password_confirmation' => 'S3cure-pass-word', 'terms_accepted' => '1',
        ] + $this->extras())->assertRedirect(route('register.verify'));
        $this->assertNull(User::where('email', 'new.person@example.test')->first(), 'no account before the code is verified');

        Sanctum::actingAs(new User()); // reset any guard state
        auth()->forgetGuards();
        $this->postJson('/api/auth/register', [
            'name' => 'Api Person', 'email' => 'api.person@example.test', 'phone' => '9876501235',
            'password' => 'S3cure-pass-word', 'password_confirmation' => 'S3cure-pass-word',
        ] + $this->extras())->assertCreated();
        $u = User::where('email', 'api.person@example.test')->sole();
        $this->assertFalse((bool) $u->is_super_admin);
        $this->assertNull($u->lifetime_free_at);
        $this->assertNull($u->guide_completed_at);
        $this->assertNull($u->referred_by_user_id);
        $this->assertNotSame($this->b['company']->id, (int) $u->active_company_id);
    }

    // ------------------------------------------------------------------ API

    public function test_api_create_and_update_routes_ignore_ownership_totals_and_status(): void
    {
        $api = function (string $verb, string $uri, array $data) {
            Sanctum::actingAs($this->owner());

            return $this->json($verb, $uri, $data);
        };
        $state = $this->a['company']->state_id;

        $api('POST', '/api/customers', ['name' => 'API C', 'state_id' => $state] + $this->extras())->assertCreated();
        $this->assertOwnedByA(Customer::where('name', 'API C')->sole(), 'api customers.store');
        $api('PUT', "/api/customers/{$this->a['customerUnused']->id}", ['name' => 'API C2', 'state_id' => $state] + $this->extras())->assertOk();
        $this->assertOwnedByA($this->a['customerUnused']->fresh(), 'api customers.update');

        $item = ['name' => 'API I', 'kind' => 'goods', 'hsn_sac' => '8471', 'unit' => 'NOS', 'rate' => 10, 'gst_rate' => 18];
        $api('POST', '/api/products', $item + $this->extras())->assertCreated();
        $this->assertOwnedByA(Product::where('name', 'API I')->sole(), 'api products.store');
        $api('PUT', "/api/products/{$this->a['productUnused']->id}", $item + $this->extras())->assertOk();
        $this->assertOwnedByA($this->a['productUnused']->fresh(), 'api products.update');

        $api('POST', '/api/invoices', ['customer_id' => $this->a['customer']->id, 'invoice_date' => '2026-09-29', 'items' => [$this->line()]]
            + $this->extras(['invoice_number', 'paid_amount']))->assertCreated();
        $inv = Invoice::latest('id')->firstOrFail();
        $this->assertOwnedByA($inv, 'api invoices.store');
        $this->assertSame('draft', $inv->status);
        $this->assertNull($inv->finalized_at);
        $this->assertEqualsWithDelta(118.00, (float) $inv->grand_total, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $inv->credited_amount, 0.001);
        $api('PUT', "/api/invoices/{$inv->id}", ['customer_id' => $this->a['customer']->id, 'invoice_date' => '2026-09-29', 'items' => [$this->line()]]
            + $this->extras(['invoice_number', 'paid_amount']))->assertOk();
        $this->assertSame('draft', $inv->fresh()->status);
        $this->assertEqualsWithDelta(118.00, (float) $inv->fresh()->grand_total, 0.001);

        $open = $this->a['invoiceOpen'];
        $api('POST', "/api/invoices/{$open->id}/payments", ['amount' => 60, 'method' => 'cash', 'received_at' => '2026-09-29'] + $this->extras())
            ->assertSuccessful();
        $p = Payment::where('invoice_id', $open->id)->sole();
        $this->assertOwnedByA($p, 'api payments');
        $this->assertSame('RCPT-0002', $p->receipt_number);
        $api('POST', "/api/invoices/{$open->id}/credit-notes", ['credit_note_date' => '2026-09-29', 'amount' => 118, 'reason' => 'rate_correction']
            + $this->extras())->assertSuccessful();
        $cn = CreditNote::where('invoice_id', $open->id)->sole();
        $this->assertOwnedByA($cn, 'api credit-notes');
        $this->assertSame('CRN-0002', $cn->credit_note_number);
        // 2,360 - 60 - 118 = 2,182.
        $this->assertEqualsWithDelta(2182.00, (float) $open->fresh()->balance, 0.001);

        $api('POST', '/api/expenses', ['entry_date' => '2026-09-29', 'category' => 'rent', 'description' => 'API rent', 'amount' => 10] + $this->extras())
            ->assertCreated();
        $e = Expense::where('description', 'API rent')->sole();
        $this->assertOwnedByA($e, 'api expenses.store');
        $this->assertNull($e->purchase_bill_id);
        $api('PUT', "/api/expenses/{$e->id}", ['entry_date' => '2026-09-29', 'category' => 'rent', 'description' => 'API rent', 'amount' => 10] + $this->extras())
            ->assertOk();
        $this->assertOwnedByA($e->fresh(), 'api expenses.update');

        $api('POST', '/api/cash-memos', ['memo_date' => '2026-09-29', 'seller_name' => 'API seller', 'payment_mode' => 'cash',
            'items' => [['description' => 'Tape', 'quantity' => 1, 'rate' => 50]]] + $this->extras())->assertCreated();
        $m = CashMemo::where('seller_name', 'API seller')->sole();
        $this->assertOwnedByA($m, 'api cash-memos.store');
        $this->assertEqualsWithDelta(50.00, (float) $m->grand_total, 0.001);

        $api('POST', '/api/quotations', ['customer_id' => $this->a['customer']->id, 'quote_date' => '2026-09-29', 'items' => [$this->line()]] + $this->extras())
            ->assertCreated();
        $q = Quotation::latest('id')->firstOrFail();
        $this->assertOwnedByA($q, 'api quotations.store');
        $this->assertSame('draft', $q->status);
        $this->assertNull($q->converted_to_invoice_id);

        $settings = ['name' => 'API firm', 'state_id' => $state, 'country' => 'India', 'default_currency' => 'INR',
            'invoice_prefix' => 'INV', 'invoice_number_padding' => 4];
        $api('POST', '/api/companies', $settings + $this->extras())->assertCreated();
        $new = Company::where('name', 'API firm')->sole();
        $this->assertSame($this->a['owner']->id, (int) $new->user_id);
        $this->assertSame(0, (int) $new->invoice_counter);
        $api('PUT', "/api/companies/{$this->a['company']->id}", ['name' => 'SECRET-FIRM-A', 'bank_ifsc' => 'HDFC0001234', 'upi_id' => 'firma@okaxis'] + $settings + $this->extras())
            ->assertOk();
        $this->assertSame($this->a['owner']->id, (int) $this->a['company']->fresh()->user_id);
        $this->assertSame(4, (int) $this->a['company']->fresh()->invoice_counter);

        $api('PUT', '/api/profile', ['name' => 'Owner A', 'email' => $this->a['owner']->email] + $this->extras())->assertOk();
        $u = $this->owner();
        $this->assertFalse((bool) $u->is_super_admin);
        $this->assertNull($u->lifetime_free_at);
        $this->assertNull($u->guide_completed_at);
    }
}
