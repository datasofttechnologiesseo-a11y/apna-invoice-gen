<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * One owner, two businesses: Sharma Traders (Maharashtra, 27, series INV-)
 * and Sharma Exports added later (Karnataka, 29, same PAN, series EXP-).
 * They are two GST registrations, so customers, invoices, receipts, numbering
 * and every figure must stay apart, and the web and the app must agree on
 * which one is open.
 *
 * Today is Mon 12 Oct 2026 (FY 2026-27).
 */
class TwoBusinessesJourneyTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private Company $traders;
    private User $ownerRow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();
        $this->traders = $this->makeBusiness([], ['name' => 'Arbaz Sharma', 'email' => 'arbaz@sharmatraders.in']);
        $this->ownerRow = $this->ownerOf($this->traders);
    }

    /** Always the current row: the active business lives on the user. */
    private function owner(): User
    {
        return $this->ownerRow->fresh();
    }

    private function exportsPayload(array $over = []): array
    {
        return array_merge([
            'name' => 'Sharma Exports',
            'gstin' => '29ABCDE1234F1ZW',
            'state_id' => $this->state('29')->id,
            'address_line1' => '44 Residency Road',
            'city' => 'Bengaluru',
            'postal_code' => '560025',
            'country' => 'India',
            'default_currency' => 'INR',
            'invoice_prefix' => 'EXP',
            'invoice_number_padding' => 3,
        ], $over);
    }

    private function activeId(): int
    {
        return (int) $this->owner()->active_company_id;
    }

    private function dashboard(): TestResponse
    {
        return $this->actingAs($this->owner())->get(route('dashboard'))->assertOk();
    }

    private function listedInvoiceNumbers(): array
    {
        $page = $this->actingAs($this->owner())->get(route('invoices.index'))->assertOk();

        return collect($page->viewData('invoices')->items())->pluck('invoice_number')->all();
    }

    private function listedCustomerNames(): array
    {
        $page = $this->actingAs($this->owner())->get(route('customers.index'))->assertOk();

        return collect($page->viewData('customers')->items())->pluck('name')->all();
    }

    // ------------------------------------------------------------- the journey

    public function test_an_owner_adds_a_second_business_and_its_customers_invoices_series_and_figures_stay_apart(): void
    {
        $patil = $this->customerOf($this->traders); // Patil Stores, Maharashtra

        // 1. Add a business: the form opens empty with the default series.
        $form = $this->actingAs($this->owner())->get(route('companies.create'))->assertOk();
        $this->assertFalse($form->viewData('company')->exists);
        $this->assertSame('INV', $form->viewData('company')->invoice_prefix);

        $this->actingAs($this->owner())->post(route('companies.store'), $this->exportsPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('status', "Company 'Sharma Exports' created and made active.");
        $exports = Company::where('name', 'Sharma Exports')->firstOrFail();
        $this->assertSame($this->owner()->id, (int) $exports->user_id);
        $this->assertSame('29ABCDE1234F1ZW', $exports->gstin);
        $this->assertSame($exports->id, $this->activeId(), 'the new business opens straight away');
        $index = $this->actingAs($this->owner())->get(route('companies.index'))->assertOk();
        $this->assertSame($exports->id, (int) $index->viewData('active')->id);
        $this->assertEqualsCanonicalizing(['Sharma Traders', 'Sharma Exports'], $index->viewData('companies')->pluck('name')->all());

        // 2. A customer added now belongs to Exports only.
        $this->actingAs($this->owner())->post(route('customers.store'), [
            'name' => 'Kulkarni Agencies', 'state_id' => $this->state('27')->id, 'country' => 'India', 'city' => 'Kolhapur',
        ])->assertSessionHas('status', "Customer 'Kulkarni Agencies' added to Sharma Exports.");
        $kulkarni = Customer::where('name', 'Kulkarni Agencies')->firstOrFail();
        $this->assertSame($exports->id, (int) $kulkarni->company_id);
        $this->assertSame(['Kulkarni Agencies'], $this->listedCustomerNames());

        // 3. Traders' customer cannot be billed from Exports - web or app.
        $before = Invoice::count();
        $this->actingAs($this->owner())->from(route('invoices.create'))->post(route('invoices.store'), [
            'customer_id' => $patil->id, 'invoice_date' => now()->toDateString(), 'items' => [$this->line()],
        ])->assertSessionHasErrors('customer_id');
        $this->apiAs($this->owner())->postJson('/api/invoices', [
            'customer_id' => $patil->id, 'invoice_date' => now()->toDateString(), 'items' => [$this->line()],
        ])->assertStatus(422)->assertJsonValidationErrors('customer_id');
        $this->assertSame($before, Invoice::count());

        // 4. Exports' first invoice starts its own series: EXP-001.
        //    Karnataka -> Maharashtra is inter-state: 3 x 500 = 1,500;
        //    IGST 18% = 270; CGST/SGST 0; grand 1,770.
        $exp1 = $this->webIssued($this->owner(), $kulkarni, [$this->line(500, 18, 3)]);
        $this->assertSame('EXP-001', $exp1->invoice_number);
        $this->assertSame($exports->id, (int) $exp1->company_id);
        $this->assertTrue((bool) $exp1->is_interstate);
        $this->assertMoney(270, $exp1->total_igst);
        $this->assertMoney(0, $exp1->total_cgst);
        $this->assertMoney(1770, $exp1->grand_total);
        // ₹770 received: Exports' own first receipt, balance 1,770 - 770 = 1,000.
        $this->webPay($this->owner(), $exp1, 770)->assertSessionHas('status', 'Payment recorded. Receipt RCPT-0001 issued.');

        // 5. Back to Traders.
        $this->actingAs($this->owner())->from(route('companies.index'))->post(route('companies.switch', $this->traders))
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('status', 'Switched to Sharma Traders.');
        $this->assertSame($this->traders->id, $this->activeId());
        $this->assertSame([], $this->listedInvoiceNumbers(), "Exports' invoice does not show in Traders");
        $this->assertSame(['Patil Stores'], $this->listedCustomerNames());
        // ...and Exports' customer cannot be billed from here.
        $this->actingAs($this->owner())->from(route('invoices.create'))->post(route('invoices.store'), [
            'customer_id' => $kulkarni->id, 'invoice_date' => now()->toDateString(), 'items' => [$this->line()],
        ])->assertSessionHasErrors('customer_id');

        // 6. Traders' own series also starts at 0001, unaffected by Exports.
        //    INV-0001: 2 x 1,250 = 2,500, intra-state CGST 225 + SGST 225 = 2,950.
        $inv1 = $this->webIssued($this->owner(), $patil, [$this->line(1250, 18, 2)]);
        $this->assertSame('INV-0001', $inv1->invoice_number);
        $this->assertMoney(225, $inv1->total_cgst);
        $this->assertMoney(225, $inv1->total_sgst);
        $this->assertMoney(2950, $inv1->grand_total);
        // ₹950 received: Traders' receipt series is its own, RCPT-0001 too.
        $this->webPay($this->owner(), $inv1, 950)->assertSessionHas('status', 'Payment recorded. Receipt RCPT-0001 issued.');
        // INV-0002: 1 x 1,000 @ 18% = 1,180.
        $inv2 = $this->webIssued($this->owner(), $patil);
        $this->assertSame('INV-0002', $inv2->invoice_number);
        $this->assertSame(['INV-0002', 'INV-0001'], $this->listedInvoiceNumbers());

        // 7. Exports again: its series continues from 001, not from Traders' 0002.
        //    EXP-002: 1 x 2,000 @ 5% IGST = 100; grand 2,100.
        $this->actingAs($this->owner())->from(route('companies.index'))->post(route('companies.switch', $exports))
            ->assertSessionHas('status', 'Switched to Sharma Exports.');
        $exp2 = $this->webIssued($this->owner(), $kulkarni, [$this->line(2000, 5, 1)]);
        $this->assertSame('EXP-002', $exp2->invoice_number);
        $this->assertMoney(100, $exp2->total_igst);
        $this->assertMoney(2100, $exp2->grand_total);
        $this->assertSame(['EXP-002', 'EXP-001'], $this->listedInvoiceNumbers());

        $this->assertSame(2, (int) $this->traders->fresh()->invoice_counter);
        $this->assertSame(2, (int) $exports->fresh()->invoice_counter);
        $this->assertSame(1, (int) $this->traders->fresh()->receipt_counter);
        $this->assertSame(1, (int) $exports->fresh()->receipt_counter);

        // 8. Each dashboard shows only its own business.
        //    Exports: billed 1,770 + 2,100 = 3,870; received 770;
        //    outstanding 1,000 + 2,100 = 3,100.
        $dash = $this->dashboard();
        $this->assertSame($exports->id, (int) $dash->viewData('company')->id);
        $stats = $dash->viewData('stats');
        $this->assertSame(2, (int) $stats['issued']);
        $this->assertMoney(3870, $stats['invoiced_this_month']);
        $this->assertMoney(3100, $stats['outstanding']);
        $this->assertMoney(770, $stats['received_total']);
        $this->assertSame(1, (int) $stats['receipts_issued']);
        $this->assertSame(['EXP-002', 'EXP-001'], $dash->viewData('recent')->pluck('invoice_number')->all());

        //    Traders: billed 2,950 + 1,180 = 4,130; received 950;
        //    outstanding (2,950 - 950) + 1,180 = 3,180.
        $this->actingAs($this->owner())->from(route('companies.index'))->post(route('companies.switch', $this->traders));
        $dash = $this->dashboard();
        $this->assertSame($this->traders->id, (int) $dash->viewData('company')->id);
        $stats = $dash->viewData('stats');
        $this->assertSame(2, (int) $stats['issued']);
        $this->assertMoney(4130, $stats['invoiced_this_month']);
        $this->assertMoney(3180, $stats['outstanding']);
        $this->assertMoney(950, $stats['received_total']);
        $this->assertSame(['INV-0002', 'INV-0001'], $dash->viewData('recent')->pluck('invoice_number')->all());

        // Nothing crossed over in the tables either.
        $this->assertSame(2, Invoice::where('company_id', $this->traders->id)->count());
        $this->assertSame(2, Invoice::where('company_id', $exports->id)->count());
        $this->assertSame(0, Invoice::where('company_id', $exports->id)->where('customer_id', $patil->id)->count());
        $this->assertSame(1, Payment::where('company_id', $exports->id)->count());
    }

    // ------------------------------------------------------------- web and app agree

    public function test_the_app_and_the_web_always_agree_on_which_business_is_open(): void
    {
        // Add Exports from the phone.
        $created = $this->apiAs($this->owner())->postJson('/api/companies', $this->exportsPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sharma Exports')
            ->assertJsonPath('data.gstin', '29ABCDE1234F1ZW')
            ->assertJsonPath('data.state.gst_code', '29')
            ->assertJsonPath('data.next_invoice_number', 'EXP-001');
        $exportsId = (int) $created->json('data.id');
        $this->assertSame($exportsId, $this->activeId(), 'adding a business on the app opens it');

        // The app and the web both say Exports.
        $this->apiAs($this->owner())->getJson('/api/companies/active')->assertOk()->assertJsonPath('data.id', $exportsId);
        $dash = $this->dashboard();
        $this->assertSame($exportsId, (int) $dash->viewData('company')->id);
        $dash->assertSee('Sharma Exports');

        // Switch on the web -> the app follows.
        $this->actingAs($this->owner())->from(route('companies.index'))->post(route('companies.switch', $this->traders))->assertRedirect();
        $this->apiAs($this->owner())->getJson('/api/companies/active')->assertOk()
            ->assertJsonPath('data.id', $this->traders->id)
            ->assertJsonPath('data.name', 'Sharma Traders');

        // Switch on the app -> the web follows.
        $this->apiAs($this->owner())->postJson("/api/companies/{$exportsId}/switch")->assertOk()->assertJsonPath('data.id', $exportsId);
        $this->assertSame($exportsId, (int) $this->dashboard()->viewData('company')->id);

        // Bill from the app in Exports: numbered in Exports' series.
        //    1 x 1,000 @ 18%, Karnataka -> Karnataka intra-state: CGST 90 + SGST 90 = 1,180.
        $kaCustomer = $this->customerOf(Company::findOrFail($exportsId), '29', ['name' => 'Hegde Textiles']);
        $draft = $this->apiAs($this->owner())->postJson('/api/invoices', [
            'customer_id' => $kaCustomer->id, 'invoice_date' => now()->toDateString(), 'items' => [$this->line()],
        ])->assertCreated();
        $this->apiAs($this->owner())->postJson('/api/invoices/' . $draft->json('data.id') . '/finalize')
            ->assertOk()->assertJsonPath('data.invoice_number', 'EXP-001');
        $issued = Invoice::findOrFail($draft->json('data.id'));
        $this->assertSame($exportsId, (int) $issued->company_id);
        $this->assertMoney(90, $issued->total_cgst);
        $this->assertMoney(1180, $issued->grand_total);

        // Each business, shown by id, reports its own next number.
        $this->apiAs($this->owner())->getJson("/api/companies/{$exportsId}")->assertOk()
            ->assertJsonPath('data.next_invoice_number', 'EXP-002');
        $this->apiAs($this->owner())->getJson("/api/companies/{$this->traders->id}")->assertOk()
            ->assertJsonPath('data.name', 'Sharma Traders')
            ->assertJsonPath('data.next_invoice_number', 'INV-0001');

        // The list has both, with the active one and the counts per business.
        $list = $this->apiAs($this->owner())->getJson('/api/companies')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.active_company_id', $exportsId);
        $counts = collect($list->json('data'))->mapWithKeys(fn ($c) => [$c['name'] => (int) $c['invoices_count']])->all();
        $this->assertSame(['Sharma Exports' => 1, 'Sharma Traders' => 0], collect($counts)->sortKeys()->all());

        // Invoice lists follow the open business on both doors.
        $this->apiAs($this->owner())->getJson('/api/invoices')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(['EXP-001'], $this->listedInvoiceNumbers());
        $this->apiAs($this->owner())->postJson("/api/companies/{$this->traders->id}/switch")->assertOk();
        $this->apiAs($this->owner())->getJson('/api/invoices')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame([], $this->listedInvoiceNumbers());

        // Renaming on the app shows on the web.
        $this->apiAs($this->owner())->putJson("/api/companies/{$exportsId}", $this->exportsPayload(['name' => 'Sharma Exports LLP']))
            ->assertOk()->assertJsonPath('data.name', 'Sharma Exports LLP');
        $this->actingAs($this->owner())->get(route('companies.index'))->assertOk()->assertSee('Sharma Exports LLP');
    }

    // ------------------------------------------------------------- refusals

    public function test_another_owners_business_cannot_be_opened_shown_or_changed(): void
    {
        $gupta = $this->makeBusiness(
            ['name' => 'Gupta Steel', 'gstin' => '07LMNOP4321Q1Z6', 'state_id' => $this->state('07')->id],
            ['email' => 'gupta@guptasteel.in'],
        );

        // Web.
        $this->actingAs($this->owner())->from(route('companies.index'))->post(route('companies.switch', $gupta))->assertForbidden();
        $this->actingAs($this->owner())->get(route('companies.edit', $gupta))->assertForbidden();
        $this->actingAs($this->owner())->patch(route('companies.update', $gupta), $this->exportsPayload(['name' => 'Taken Over', 'gstin' => null]))->assertForbidden();
        $this->actingAs($this->owner())->delete(route('companies.destroy', $gupta))->assertForbidden();

        // App.
        $this->apiAs($this->owner())->getJson("/api/companies/{$gupta->id}")->assertForbidden();
        $this->apiAs($this->owner())->putJson("/api/companies/{$gupta->id}", $this->exportsPayload(['name' => 'Taken Over', 'gstin' => null]))->assertForbidden();
        $this->apiAs($this->owner())->postJson("/api/companies/{$gupta->id}/switch")->assertForbidden();
        $this->apiAs($this->owner())->getJson('/api/companies/999999')->assertNotFound();

        $this->assertSame($this->traders->id, $this->activeId());
        $this->assertSame('Gupta Steel', $gupta->fresh()->name);
        $this->assertNotNull(Company::find($gupta->id));
        $this->assertSame(1, Company::where('user_id', $this->owner()->id)->count());
        $this->apiAs($this->owner())->getJson('/api/companies')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonMissing(['name' => 'Gupta Steel']);
    }

    public function test_adding_a_business_with_bad_details_is_refused_on_the_web_and_the_app(): void
    {
        $refusedOnWeb = [
            'name' => ['name' => ''],
            'state_id' => ['state_id' => null],
            'country' => ['country' => ''],
            'default_currency' => ['default_currency' => 'RUPEE'],
            'invoice_prefix' => ['invoice_prefix' => 'SHARMAEXPORTS'], // 13 > 10
            'invoice_number_padding' => ['invoice_number_padding' => 9],
            // Maharashtra GSTIN on a Karnataka business.
            'gstin' => ['gstin' => '27ABCDE1234F1Z0'],
            // "SHARMAEXP/2026-27/001" = 9+1+7+1+3 = 21 characters > 16 (Rule 46(b)).
            'invoice_number_format' => ['invoice_number_format' => 'SHARMAEXP/{FY}/{N}'],
            'bank_ifsc' => ['bank_ifsc' => 'SBIN1234'],
            'upi_id' => ['upi_id' => 'sharma exports'],
        ];
        foreach ($refusedOnWeb as $field => $over) {
            $this->actingAs($this->owner())->from(route('companies.create'))
                ->post(route('companies.store'), $this->exportsPayload($over))
                ->assertRedirect(route('companies.create'))
                ->assertSessionHasErrors($field);
        }

        $refusedOnApp = ['name' => ['name' => ''], 'state_id' => ['state_id' => 999999], 'gstin' => ['gstin' => '27ABCDE1234F1Z0'],
            'invoice_prefix' => ['invoice_prefix' => 'SHARMAEXPORTS'], 'default_currency' => ['default_currency' => 'RS']];
        foreach ($refusedOnApp as $field => $over) {
            $this->apiAs($this->owner())->postJson('/api/companies', $this->exportsPayload($over))
                ->assertStatus(422)->assertJsonValidationErrors($field);
        }

        $this->assertSame(1, Company::where('user_id', $this->owner()->id)->count(), 'nothing was created');
        $this->assertSame($this->traders->id, $this->activeId(), 'the open business did not change');
    }

    /**
     * The web form refuses a series longer than 16 characters (CGST Rule
     * 46(b)); "add business" on the app must too, or the new business's
     * first invoice would be numbered SHARMAEXP/2026-27/001 (21 characters),
     * which GSTR-1 rejects.
     */
    public function test_the_app_refuses_a_new_business_whose_series_breaks_the_16_character_rule(): void
    {
        $this->apiAs($this->owner())->postJson('/api/companies', $this->exportsPayload(['invoice_number_format' => 'SHARMAEXP/{FY}/{N}']))
            ->assertStatus(422)->assertJsonValidationErrors('invoice_number_format');
        $this->assertNull(Company::where('name', 'Sharma Exports')->first());
    }

    // ------------------------------------------------------------- deleting a business

    public function test_a_business_with_invoices_is_kept_and_deleting_the_open_empty_one_moves_you_back(): void
    {
        $inv = $this->webIssued($this->owner(), $this->customerOf($this->traders)); // INV-0001
        $this->actingAs($this->owner())->post(route('companies.store'), $this->exportsPayload())->assertSessionHasNoErrors();
        $exports = Company::where('name', 'Sharma Exports')->firstOrFail();
        $this->actingAs($this->owner())->post(route('customers.store'), [
            'name' => 'Kulkarni Agencies', 'state_id' => $this->state('27')->id, 'country' => 'India',
        ])->assertSessionHasNoErrors();
        $kulkarni = Customer::where('name', 'Kulkarni Agencies')->firstOrFail();

        // Traders has an issued invoice: refused, everything kept.
        $this->actingAs($this->owner())->from(route('companies.index'))->delete(route('companies.destroy', $this->traders))
            ->assertRedirect(route('companies.index'))
            ->assertSessionHasErrors(['company' => 'This company has invoices and cannot be deleted.']);
        $this->assertNotNull(Company::find($this->traders->id));
        $this->assertNotNull(Invoice::find($inv->id));
        $this->assertSame($exports->id, $this->activeId());

        // Exports (open, no invoices) goes, with its customer, and Traders opens.
        $this->actingAs($this->owner())->from(route('companies.index'))->delete(route('companies.destroy', $exports))
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('status', 'Company deleted.');
        $this->assertNull(Company::find($exports->id));
        $this->assertNull(Customer::find($kulkarni->id));
        $this->assertSame($this->traders->id, $this->activeId());
        $this->assertSame($this->traders->id, (int) $this->dashboard()->viewData('company')->id);
        $this->assertSame(['INV-0001'], $this->listedInvoiceNumbers());
    }

    public function test_deleting_your_only_empty_business_leaves_a_working_account(): void
    {
        $solo = $this->makeBusiness(['name' => 'Solo Studio', 'gstin' => null], ['name' => 'Meera Joshi']);
        $meera = $this->ownerOf($solo);

        $this->actingAs($meera)->from(route('companies.index'))->delete(route('companies.destroy', $solo))
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('status', 'Company deleted.');
        $this->assertNull(Company::find($solo->id));
        $this->assertNull($meera->fresh()->active_company_id);

        // The next page gives her a fresh, empty business rather than an error.
        $this->actingAs($meera->fresh())->get(route('companies.index'))->assertOk();
        $fresh = Company::where('user_id', $meera->id)->get();
        $this->assertCount(1, $fresh);
        $this->assertSame("Meera Joshi's Company", $fresh->first()->name);
        $this->assertSame((int) $fresh->first()->id, (int) $meera->fresh()->active_company_id);
    }

    /**
     * A business that has only a quotation (no invoices) shows a Delete
     * button. Deleting it removes its customers first, but quotations hold
     * customer_id / company_id with ON DELETE RESTRICT, so the database
     * refuses and the owner gets a server error.
     * Expected: a clear refusal (or a clean delete), never a 500.
     */
    public function test_deleting_a_business_that_only_has_a_quotation_does_not_end_in_a_server_error(): void
    {
        $this->actingAs($this->owner())->post(route('companies.store'), $this->exportsPayload())->assertSessionHasNoErrors();
        $exports = Company::where('name', 'Sharma Exports')->firstOrFail();
        $hegde = $this->customerOf($exports, '29', ['name' => 'Hegde Textiles']);
        $this->actingAs($this->owner())->post(route('quotations.store'), [
            'customer_id' => $hegde->id, 'quote_date' => now()->toDateString(), 'valid_until' => now()->addDays(15)->toDateString(),
            'items' => [$this->line()],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Quotation::where('company_id', $exports->id)->count());

        $res = $this->actingAs($this->owner())->from(route('companies.index'))->delete(route('companies.destroy', $exports));
        $this->assertNotSame(500, $res->getStatusCode(), 'deleting a business with a quotation crashed with a server error');
        $res->assertRedirect(route('companies.index'));
    }

    /**
     * Deleting a business is refused while it has invoices (GST records are
     * kept for 72 months, s.36 CGST Act), but its expenses, purchase invoices
     * and cash memos are removed with it by ON DELETE CASCADE, while the
     * confirmation only warns about customers. Here the business has a
     * ₹30,000 rent bill with ₹5,400 GST (18%) taken as input tax credit.
     * Expected: those books survive (the delete is refused like invoices).
     */
    public function test_deleting_a_business_does_not_silently_erase_its_expenses_and_input_tax_credit(): void
    {
        $this->actingAs($this->owner())->post(route('companies.store'), $this->exportsPayload())->assertSessionHasNoErrors();
        $exports = Company::where('name', 'Sharma Exports')->firstOrFail();
        // 30,000 x 18% = 5,400 GST, eligible for ITC.
        $this->actingAs($this->owner())->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(), 'category' => 'rent', 'vendor_name' => 'Kulkarni Estates',
            'description' => 'Godown rent October', 'amount' => 30000, 'gst_amount' => 5400, 'itc_eligible' => 1,
            'payment_method' => 'bank',
        ])->assertSessionHasNoErrors();
        $expense = Expense::where('company_id', $exports->id)->firstOrFail();
        $this->assertMoney(5400, $expense->gst_amount);

        $this->actingAs($this->owner())->from(route('companies.index'))->delete(route('companies.destroy', $exports));

        $this->assertNotNull(Expense::find($expense->id), 'the rent bill and its ₹5,400 input tax credit were erased with the business');
    }
}
