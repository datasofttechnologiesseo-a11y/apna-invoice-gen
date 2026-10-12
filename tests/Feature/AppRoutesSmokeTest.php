<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\Quotation;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Opens every signed-in GET page and download in the app with a realistic
 * set of records behind it, and fails on any server error. Feature tests
 * cover behaviour; this catches the page or export nobody remembered to
 * test (the GSTR-3B CSV crashed for exactly that reason).
 */
class AppRoutesSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Routes that are not signed-in pages, or need a signature / token. */
    private const SKIP = '#^(api/|_ignition|sanctum|up$|storage/|logout|auth/|login|register|forgot-password|reset-password|'
        . 'verify-email|email/verify|confirm-password|i/|q/|unsubscribe|r/|newsletter|password|admin|onboarding|'
        . 'blog|sitemap|robots|manuals|feed)#';

    public function test_every_signed_in_page_and_export_loads_without_a_server_error(): void
    {
        $mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $ka = State::factory()->create(['gst_code' => '29', 'name' => 'Karnataka']);
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create(['state_id' => $mh->id, 'gstin' => '27ABCDE1234F1Z0', 'onboarded_at' => now()]);
        $user->forceFill(['active_company_id' => $company->id])->save();
        $this->actingAs($user);

        $customer = Customer::factory()->recycle($user)->recycle($company)->create(['state_id' => $ka->id, 'gstin' => '29ABCDE1234F1ZW']);
        $product = Product::create(['user_id' => $user->id, 'company_id' => $company->id, 'name' => 'Widget', 'kind' => 'goods', 'hsn_sac' => '84733010', 'unit' => 'NOS', 'rate' => 1000, 'gst_rate' => 18, 'is_active' => true]);

        $this->post(route('invoices.store'), [
            'customer_id' => $customer->id, 'invoice_date' => now()->toDateString(), 'invoice_discount' => 100,
            'items' => [['product_id' => $product->id, 'description' => 'Widget', 'hsn_sac' => '84733010', 'quantity' => 2, 'unit' => 'NOS', 'rate' => 1000, 'gst_rate' => 18]],
        ])->assertRedirect();
        $invoice = Invoice::latest('id')->first();
        $this->post(route('invoices.finalize', $invoice))->assertRedirect();
        $this->post(route('invoices.payments', $invoice), ['amount' => 500, 'method' => 'upi', 'received_at' => now()->toDateString()])->assertRedirect();
        $this->post(route('credit-notes.store', $invoice), ['credit_note_date' => now()->toDateString(), 'amount' => 100, 'reason' => array_key_first(config('credit_note_reasons'))])->assertRedirect();

        $this->post(route('quotations.store'), [
            'customer_id' => $customer->id, 'quote_date' => now()->toDateString(), 'valid_until' => now()->addDays(15)->toDateString(),
            'items' => [['description' => 'Service', 'hsn_sac' => '998314', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 5000, 'gst_rate' => 18]],
        ])->assertRedirect();

        $vendor = Vendor::create(['user_id' => $user->id, 'company_id' => $company->id, 'name' => 'Supplier Co', 'state_id' => $mh->id]);
        $this->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'S/1', 'bill_date' => now()->toDateString(), 'itc_eligible' => 1, 'paid_now' => 100,
            'items' => [['description' => 'Parts', 'hsn_sac' => '84733010', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 2000, 'gst_rate' => 18]],
        ])->assertRedirect();
        $this->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(), 'category' => 'rent', 'description' => 'Office rent', 'amount' => 10000, 'gst_amount' => 1800, 'payment_method' => 'bank', 'itc_eligible' => 1,
        ]);
        $this->post(route('finance.cash-memos.store'), [
            'memo_date' => now()->toDateString(), 'seller_name' => 'Local shop', 'payment_mode' => 'cash', 'expense_category' => 'office_supplies', 'itc_eligible' => 1,
            'items' => [['description' => 'Stationery', 'quantity' => 1, 'rate' => 300]],
        ]);

        $ids = [
            'invoice' => $invoice->id,
            'quotation' => Quotation::value('id'),
            'customer' => $customer->id,
            'product' => $product->id,
            'vendor' => $vendor->id,
            'purchaseBill' => PurchaseBill::value('id'),
            'expense' => Expense::whereNull('cash_memo_id')->whereNull('purchase_bill_id')->value('id'),
            'cashMemo' => \App\Models\CashMemo::value('id'),
            'creditNote' => CreditNote::value('id'),
            'payment' => Payment::value('id'),
            'company' => $company->id,
            'report' => 'sales-register',
            'format' => 'xlsx',
            'type' => 'customers',
            'template' => array_key_first(config('invoice_templates')),
        ];

        $failures = [];
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods(), true) || preg_match(self::SKIP, $uri)) {
                continue;
            }
            $missing = false;
            $path = preg_replace_callback('/\{(\w+)(\?)?\}/', function ($m) use ($ids, &$missing) {
                if (array_key_exists($m[1], $ids) && $ids[$m[1]] !== null) {
                    return (string) $ids[$m[1]];
                }
                if (! empty($m[2])) {
                    return '';
                }
                $missing = true;

                return 'x';
            }, $uri);
            if ($missing) {
                continue;
            }
            $response = $this->get('/' . ltrim(rtrim($path, '/'), '/'));
            $status = $response->getStatusCode();
            $checked++;
            if ($status >= 500) {
                $failures[] = "{$status} /{$uri}";
                continue;
            }
            // CSV / Excel downloads are streamed: a 200 is sent before the file
            // is written, so a crash while writing only shows up when the
            // stream is read. Read it.
            if ($response->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
                try {
                    $response->streamedContent();
                } catch (\Throwable $e) {
                    $failures[] = "stream /{$uri}: " . $e->getMessage();
                }
            } elseif (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                // A Blade directive glued to the word before it ("bond@if") is
                // not compiled: it prints as text and unbalances the template.
                $html = (string) $response->getContent();
                if (preg_match('/@(if|else|elseif|endif|foreach|endforeach|unless|endunless|isset|endisset|php|endphp)[ (]?/', strip_tags($html), $m)) {
                    $failures[] = "raw Blade '{$m[0]}' on /{$uri}";
                }
            }
        }

        $this->assertGreaterThan(80, $checked, 'expected to exercise most of the app');
        $this->assertSame([], $failures, "pages that crash:\n" . implode("\n", $failures));
    }
}
