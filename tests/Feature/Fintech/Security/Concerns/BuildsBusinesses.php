<?php

namespace Tests\Feature\Fintech\Security\Concerns;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\CashMemo;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Gstr2bEntry;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\PurchasePayment;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * One business with one of everything a real user has: invoices in each
 * state, a receipt, a credit note, quotations, a cash memo, a purchase
 * invoice with a vendor payment, an expense, items with stock, a bank
 * statement, GSTR-2B lines and team members.
 *
 * Documents that the app numbers or mirrors (invoices, receipts, credit
 * notes, cash memos, purchase invoices, bank lines) are created through the
 * app's own HTTP routes, so the rows are exactly what a user would have.
 * Every business-identifying string carries a SECRET-<tag> marker so a test
 * can search any response for another business's data.
 *
 * Dates assume the clock is frozen at 30 Sep 2026 (see freezeClock()).
 */
trait BuildsBusinesses
{
    protected function freezeClock(): void
    {
        // 30 Sep 2026, inside FY 2026-27; every fixture date is in September.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-30 11:00:00'));
    }

    protected function maharashtra(): State
    {
        return State::where('gst_code', '27')->first()
            ?? State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra', 'code' => 'MH']);
    }

    protected function karnataka(): State
    {
        return State::where('gst_code', '29')->first()
            ?? State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka', 'code' => 'KA']);
    }

    /** @return array<string, mixed> */
    protected function buildBusiness(string $tag, ?User $owner = null, array $companyAttributes = []): array
    {
        Mail::fake();
        $mh = $this->maharashtra();
        $lower = strtolower($tag);
        $owner ??= User::factory()->create(['name' => "Owner {$tag}", 'email' => "owner.{$lower}@example.test"]);

        $company = Company::factory()->create(array_merge([
            'user_id' => $owner->id, 'state_id' => $mh->id, 'name' => "SECRET-FIRM-{$tag}",
            'gstin' => null, 'invoice_prefix' => 'INV', 'invoice_counter' => 0, 'onboarded_at' => now(),
            'bank_account_number' => "SECRET-ACCT-{$tag}", 'bank_ifsc' => 'HDFC0001234',
            'upi_id' => "firm{$lower}@okaxis",
        ], $companyAttributes));
        $owner->forceFill(['active_company_id' => $company->id])->save();
        $owner = $owner->fresh();

        $customer = $owner->customers()->create([
            'company_id' => $company->id, 'name' => "SECRET-CUSTOMER-{$tag}", 'state_id' => $mh->id,
            'country' => 'India', 'email' => "buyer.{$lower}@example.test", 'phone' => '9876543210',
        ]);
        $customerUnused = $owner->customers()->create([
            'company_id' => $company->id, 'name' => "SECRET-PROSPECT-{$tag}", 'state_id' => $mh->id, 'country' => 'India',
        ]);

        $product = Product::create([
            'user_id' => $owner->id, 'company_id' => $company->id, 'name' => "SECRET-ITEM-{$tag}", 'kind' => 'goods',
            'hsn_sac' => '8471', 'unit' => 'NOS', 'rate' => 1000, 'gst_rate' => 18,
            'track_stock' => true, 'opening_stock' => 100, 'is_active' => true,
        ]);
        $productUnused = Product::create([
            'user_id' => $owner->id, 'company_id' => $company->id, 'name' => "SECRET-SPARE-{$tag}", 'kind' => 'goods',
            'hsn_sac' => '8471', 'unit' => 'NOS', 'rate' => 50, 'gst_rate' => 18, 'is_active' => true,
        ]);

        $makeInvoice = function (array $items, string $date) use ($owner, $company, $customer): Invoice {
            $this->actingAs($owner)->post(route('invoices.store'), [
                'customer_id' => $customer->id, 'invoice_date' => $date,
                'due_date' => \Illuminate\Support\Carbon::parse($date)->addDays(15)->toDateString(),
                'items' => $items,
            ])->assertSessionHasNoErrors();

            return Invoice::where('company_id', $company->id)->latest('id')->firstOrFail();
        };
        $issue = function (Invoice $invoice) use ($owner): Invoice {
            $this->actingAs($owner)->post(route('invoices.finalize', $invoice))->assertSessionHasNoErrors();

            return $invoice->fresh();
        };

        // 2 x 1,000 = 2,000; 18% = 360 (CGST 180 + SGST 180); total 2,360. Unpaid.
        $invoiceOpen = $issue($makeInvoice([['product_id' => $product->id, 'description' => "Widget {$tag}", 'hsn_sac' => '8471',
            'quantity' => 2, 'unit' => 'NOS', 'rate' => 1000, 'gst_rate' => 18]], '2026-09-10'));

        // 1 x 1,000 + 18% = 1,180, paid in full by NEFT.
        $invoicePaid = $issue($makeInvoice([['description' => "Service {$tag}", 'hsn_sac' => '998314',
            'quantity' => 1, 'rate' => 1000, 'gst_rate' => 18]], '2026-09-11'));
        $this->actingAs($owner)->post(route('invoices.payments', $invoicePaid), [
            'amount' => 1180, 'method' => 'neft', 'received_at' => '2026-09-12', 'reference_number' => "UTR{$tag}0001",
        ])->assertSessionHasNoErrors();
        $payment = Payment::where('invoice_id', $invoicePaid->id)->firstOrFail();

        // 1 x 500 + 18% = 590; a 118 credit note for a rate correction.
        $invoiceCredited = $issue($makeInvoice([['description' => "Repair {$tag}", 'hsn_sac' => '998714',
            'quantity' => 1, 'rate' => 500, 'gst_rate' => 18]], '2026-09-13'));
        $this->actingAs($owner)->post(route('credit-notes.store', $invoiceCredited), [
            'credit_note_date' => '2026-09-20', 'amount' => 118, 'reason' => 'rate_correction', 'notes' => "SECRET-CN-{$tag}",
        ])->assertSessionHasNoErrors();
        $creditNote = CreditNote::where('invoice_id', $invoiceCredited->id)->firstOrFail();

        // 1 x 300 + 18% = 354, never issued.
        $invoiceDraft = $makeInvoice([['description' => "Draft {$tag}", 'hsn_sac' => '998314',
            'quantity' => 1, 'rate' => 300, 'gst_rate' => 18]], '2026-09-14');

        // 1 x 200 + 18% = 236, issued then cancelled.
        $invoiceCancelled = $issue($makeInvoice([['description' => "Cancelled {$tag}", 'hsn_sac' => '998314',
            'quantity' => 1, 'rate' => 200, 'gst_rate' => 18]], '2026-09-15'));
        $this->actingAs($owner)->post(route('invoices.cancel', $invoiceCancelled), ['cancellation_reason' => 'Raised in error'])
            ->assertSessionHasNoErrors();

        $quote = function (string $state) use ($owner, $company, $customer, $tag): Quotation {
            $quotation = ($state === 'draft' ? Quotation::factory() : Quotation::factory()->{$state}())->create(array_merge([
                'user_id' => $owner->id, 'company_id' => $company->id, 'customer_id' => $customer->id,
                'subject' => "SECRET-QUOTE-{$tag}",
            ], $state === 'draft' ? ['status' => 'draft'] : []));
            $quotation->items()->create([
                'description' => "Quoted {$tag}", 'hsn_sac' => '8471', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 1000,
                'discount' => 0, 'gst_rate' => 18, 'amount' => 1000, 'cgst_amount' => 90, 'sgst_amount' => 90, 'igst_amount' => 0,
            ]);

            return $quotation;
        };
        $quoteDraft = $quote('draft');
        $quoteSent = $quote('sent');
        $quoteAccepted = $quote('accepted');

        $this->actingAs($owner)->post(route('finance.cash-memos.store'), [
            'memo_date' => '2026-09-15', 'seller_name' => "SECRET-SELLER-{$tag}", 'payment_mode' => 'cash',
            'gst_rate' => 18, 'is_interstate' => 0, 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Printer paper', 'quantity' => 2, 'rate' => 100]],
        ])->assertSessionHasNoErrors();
        $cashMemo = CashMemo::where('company_id', $company->id)->latest('id')->firstOrFail();

        $this->actingAs($owner)->post(route('finance.expenses.store'), [
            'entry_date' => '2026-09-16', 'category' => 'rent', 'description' => "SECRET-RENT-{$tag}",
            'amount' => 5000, 'gst_amount' => 900, 'itc_eligible' => '1', 'payment_method' => 'bank',
        ])->assertSessionHasNoErrors();
        $expense = Expense::where('company_id', $company->id)->whereNull('cash_memo_id')->whereNull('purchase_bill_id')
            ->latest('id')->firstOrFail();

        $vendor = $company->vendors()->create(['user_id' => $owner->id, 'name' => "SECRET-VENDOR-{$tag}", 'state_id' => $mh->id]);
        $vendorUnused = $company->vendors()->create(['user_id' => $owner->id, 'name' => "SECRET-SUPPLIER-{$tag}", 'state_id' => $mh->id]);
        // 1 x 500 + 18% = 590, paid on the spot.
        $this->actingAs($owner)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => "PB-{$tag}-1", 'bill_date' => '2026-09-17', 'itc_eligible' => 1,
            'expense_category' => 'office_supplies', 'paid_now' => 590, 'paid_on' => '2026-09-17', 'payment_method' => 'neft',
            'items' => [['description' => 'Cable', 'hsn_sac' => '8544', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 500, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors();
        $purchaseBill = PurchaseBill::where('company_id', $company->id)->latest('id')->firstOrFail();
        $purchasePayment = PurchasePayment::where('purchase_bill_id', $purchaseBill->id)->firstOrFail();

        $this->actingAs($owner)->post(route('products.stock.adjust', $product), [
            'direction' => 'in', 'quantity' => 5, 'moved_on' => '2026-09-18', 'reason' => 'Found in godown',
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('bank.upload'), [
            'account_name' => "SECRET-BANK-{$tag}", 'file' => $this->bankStatementFile($tag),
        ])->assertSessionHasNoErrors();
        $statement = BankStatement::where('company_id', $company->id)->latest('id')->firstOrFail();
        $bankLine = BankStatementLine::where('bank_statement_id', $statement->id)->where('status', 'unmatched')->firstOrFail();

        $gstr2b = Gstr2bEntry::create([
            'company_id' => $company->id, 'period' => '2026-09', 'kind' => 'invoice', 'supplier_gstin' => '27PQRST1234U1Z1',
            'supplier_name' => "SECRET-2B-SUPPLIER-{$tag}", 'document_number' => "2B-{$tag}-1", 'document_date' => '2026-09-05',
            'document_value' => 1180, 'taxable_value' => 1000, 'igst' => 0, 'cgst' => 90, 'sgst' => 90,
        ]);

        $memberUser = User::factory()->create(['name' => "Member {$tag}", 'email' => "member.{$lower}@example.test"]);
        $memberAccepted = CompanyMember::create([
            'company_id' => $company->id, 'user_id' => $memberUser->id, 'email' => $memberUser->email, 'role' => 'accountant',
            'token' => CompanyMember::newToken(), 'invited_by' => $owner->id, 'accepted_at' => now(),
        ]);
        $memberPending = CompanyMember::create([
            'company_id' => $company->id, 'email' => "invitee.{$lower}@example.test", 'role' => 'staff',
            'token' => CompanyMember::newToken(), 'invited_by' => $owner->id,
        ]);

        InvoiceReminder::create([
            'invoice_id' => $invoiceOpen->id, 'company_id' => $company->id, 'channel' => 'email',
            'recipient' => $customer->email, 'days_past_due' => 3,
        ]);

        return [
            'tag' => $tag, 'owner' => $owner->fresh(), 'company' => $company->fresh(), 'customer' => $customer,
            'customerUnused' => $customerUnused, 'product' => $product, 'productUnused' => $productUnused,
            'invoiceOpen' => $invoiceOpen->fresh(), 'invoicePaid' => $invoicePaid->fresh(), 'invoiceCredited' => $invoiceCredited->fresh(),
            'invoiceDraft' => $invoiceDraft->fresh(), 'invoiceCancelled' => $invoiceCancelled->fresh(),
            'payment' => $payment, 'creditNote' => $creditNote,
            'quoteDraft' => $quoteDraft, 'quoteSent' => $quoteSent, 'quoteAccepted' => $quoteAccepted,
            'cashMemo' => $cashMemo, 'expense' => $expense, 'vendor' => $vendor, 'vendorUnused' => $vendorUnused,
            'purchaseBill' => $purchaseBill, 'purchasePayment' => $purchasePayment,
            'statement' => $statement, 'bankLine' => $bankLine, 'gstr2b' => $gstr2b,
            'memberUser' => $memberUser, 'memberAccepted' => $memberAccepted, 'memberPending' => $memberPending,
        ];
    }

    /** An HDFC-style statement: a receipt, a vendor payment and a bank charge. */
    protected function bankStatementFile(string $tag): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bank') . '.xlsx';
        $w = new Writer();
        $w->openToFile($path);
        foreach ([
            ['HDFC BANK Ltd.'], ['Statement From : 01/09/2026 To : 30/09/2026'], [],
            ['Date', 'Narration', 'Chq./Ref.No.', 'Value Dt', 'Withdrawal Amt.', 'Deposit Amt.', 'Closing Balance'],
            ['12/09/26', "NEFT CR-SECRET-PAYER-{$tag}", "UTR{$tag}0001", '12/09/26', '', '1,180.00', '1,01,180.00'],
            ['17/09/26', "NEFT DR-SECRET-VENDOR-{$tag}", '0000000000000077', '17/09/26', '590.00', '', '1,00,590.00'],
            ['20/09/26', 'CHRG: SMS ALERT CHARGES', '', '20/09/26', '17.70', '', '1,00,572.30'],
        ] as $cells) {
            $w->addRow(Row::fromValues($cells));
        }
        $w->close();

        return new UploadedFile($path, "statement-{$tag}.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * A fingerprint of every application table, to prove a refused request
     * changed nothing. Framework bookkeeping (sessions, cache, queues) is left
     * out; everything else - including users and the audit log - is in.
     *
     * @return array<string, string>
     */
    protected function snapshot(): array
    {
        $skip = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens'];
        $out = [];
        // This database only: since Laravel 12, getTables() without a schema
        // lists every database on a MySQL server.
        $schema = DB::connection()->getDriverName() === 'sqlite' ? null : DB::connection()->getDatabaseName();
        foreach (Schema::getTables($schema) as $t) {
            $name = is_array($t) ? $t['name'] : $t->name;
            if (in_array($name, $skip, true) || str_starts_with($name, 'sqlite_')) {
                continue;
            }
            $rows = DB::table($name)->get()->map(fn ($r) => json_encode((array) $r))->sort()->values()->all();
            $out[$name] = md5(implode("\n", $rows));
        }

        return $out;
    }

    /** @return list<string> tables whose rows differ */
    protected function changedTables(array $before, array $after): array
    {
        return array_values(array_keys(array_diff_assoc($after, $before) + array_diff_assoc($before, $after)));
    }
}
