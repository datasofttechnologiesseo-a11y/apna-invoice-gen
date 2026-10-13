<?php

namespace Tests\Feature\Audit\Security;

use App\Models\CashMemo;
use App\Models\Company;
use App\Models\Expense;
use App\Models\PurchaseBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Fintech\Security\Concerns\BuildsBusinesses;
use Tests\TestCase;

/**
 * Account erasure keeps "only the GST books for the retention window"
 * (AccountErasureService), and deleting a business is refused while it has
 * "invoices, purchases, expenses or other entries in its books ... input
 * tax credit included" (CompanyController::destroy / BusinessBooks).
 *
 * erase() decides "statutory records exist" from SALES invoices alone. A
 * business whose books hold purchase invoices, cash memos and expenses on
 * which input tax credit was taken (CGST s.35/36, Rule 56: accounts of
 * inward supplies kept 72 months) is hard-deleted on the spot.
 */
class ErasureRetentionTest extends TestCase
{
    use BuildsBusinesses;
    use RefreshDatabase;

    public function test_closing_an_account_keeps_purchase_invoices_and_cash_memos_on_which_itc_was_claimed(): void
    {
        $this->freezeClock();
        $owner = User::factory()->create(['email' => 'buyer@example.test']);
        $company = Company::factory()->create(['user_id' => $owner->id, 'state_id' => $this->maharashtra()->id,
            'gstin' => null, 'onboarded_at' => now()]);
        $owner->forceFill(['active_company_id' => $company->id])->save();
        $vendor = $company->vendors()->create(['user_id' => $owner->id, 'name' => 'Supplier', 'state_id' => $this->maharashtra()->id]);

        // A purchase invoice of 59,000 (ITC 9,000) and a cash memo, both this month.
        $this->actingAs($owner->fresh())->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'PB-1', 'bill_date' => '2026-09-17', 'itc_eligible' => 1,
            'expense_category' => 'office_supplies',
            'items' => [['description' => 'Machine', 'hsn_sac' => '8471', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 50000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner->fresh())->post(route('finance.cash-memos.store'), [
            'memo_date' => '2026-09-15', 'seller_name' => 'Local seller', 'payment_mode' => 'cash',
            'gst_rate' => 18, 'is_interstate' => 0, 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Printer paper', 'quantity' => 2, 'rate' => 100]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, PurchaseBill::where('company_id', $company->id)->count(), 'fixture');
        $this->assertSame(1, CashMemo::where('company_id', $company->id)->count(), 'fixture');

        // The same books make the business itself undeletable...
        $this->actingAs($owner->fresh())->delete(route('companies.destroy', $company))->assertSessionHasErrors('company');
        $this->assertSame(1, PurchaseBill::where('company_id', $company->id)->count(), 'fixture: company delete refused');

        // ...but closing the account wipes them immediately.
        $this->actingAs($owner->fresh())->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

        $left = PurchaseBill::where('company_id', $company->id)->count() . ' purchase invoice(s), '
            . CashMemo::where('company_id', $company->id)->count() . ' cash memo(s), '
            . Expense::where('company_id', $company->id)->count() . ' expense row(s)';
        $this->assertSame(1, PurchaseBill::where('company_id', $company->id)->count(),
            "account erasure hard-deleted ITC records inside the 72-month window; left: {$left}");
        $this->assertSame(1, CashMemo::where('company_id', $company->id)->count(), "left: {$left}");
    }

    /**
     * Same rule in PurgeErasedAccounts: an erased account is purged once its
     * newest SALES invoice is past the window, whatever the date of the
     * purchase invoices kept with it.
     */
    public function test_the_purge_waits_for_the_newest_purchase_invoice_not_just_the_newest_sale(): void
    {
        $this->freezeClock(); // 30 Sep 2026
        $a = $this->buildBusiness('A');
        $owner = $a['owner']->fresh();
        // Every sale is moved back to FY 2018-19 (kept until 31 Dec 2025); the purchase stays in Sep 2026.
        \App\Models\Invoice::where('company_id', $a['company']->id)->update(['invoice_date' => '2018-06-15']);
        $bill = $a['purchaseBill'];
        $this->assertSame('2026-09-17', $bill->fresh()->bill_date->toDateString(), 'fixture');

        $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
        $this->assertNotNull(PurchaseBill::find($bill->id), 'fixture: kept at erasure (depersonalised)');

        $this->artisan('accounts:purge-erased')->assertExitCode(0);

        $this->assertNotNull(PurchaseBill::find($bill->id),
            'the purge deleted a purchase invoice of Sep 2026 (ITC taken) the same day, because the last SALE was from 2018');
    }
}
