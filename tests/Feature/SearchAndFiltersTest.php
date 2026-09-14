<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The two search bars, and the invoice date range behind the dashboard card.
 *
 * The bug worth pinning here is not "search returns nothing" - it is that the
 * two screens searched different columns, so the same term found a customer
 * on one page and nothing on the other. Both now go through
 * Customer::scopeSearch, and these tests fail if they drift apart again.
 */
class SearchAndFiltersTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Company,2:Customer} */
    private function fixture(): array
    {
        $state = State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create(['state_id' => $state->id, 'onboarded_at' => now()]);
        $user->switchCompany($company);

        $customer = Customer::factory()->recycle($user)->recycle($company)->create([
            'name' => 'Kirana Traders',
            'email' => 'accounts@kirana.example',
            'phone' => '+91 98765-43210',
            'gstin' => '27AAPFU0939F1ZV',
            'state_id' => $state->id,
        ]);

        // A second customer who must never come back on these searches.
        Customer::factory()->recycle($user)->recycle($company)->create([
            'name' => 'Unrelated Stores', 'email' => 'no@example.com',
            'phone' => '9000000000', 'gstin' => null, 'state_id' => $state->id,
        ]);

        return [$user, $company, $customer];
    }

    public static function customerTerms(): array
    {
        return [
            'name' => ['Kirana'],
            'email' => ['accounts@kirana'],
            'gstin' => ['27AAPFU0939F1ZV'],
            'gstin lower case' => ['27aapfu0939f1zv'],
            'phone as stored' => ['98765-43210'],
            'phone typed plainly' => ['9876543210'],
        ];
    }

    #[DataProvider('customerTerms')]
    public function test_the_customer_list_finds_a_customer_by_name_email_gstin_or_mobile(string $term): void
    {
        [$user] = $this->fixture();

        $this->actingAs($user)->get(route('customers.index', ['search' => $term]))
            ->assertOk()
            ->assertSee('Kirana Traders')
            ->assertDontSee('Unrelated Stores');
    }

    #[DataProvider('customerTerms')]
    public function test_the_invoice_list_finds_the_same_customer_by_the_same_terms(string $term): void
    {
        // Parity is the point: before this, the invoice list matched name and
        // phone while the customer list matched name and email, so an email
        // or a GSTIN found the customer on one page and nothing on the other.
        [$user, $company, $customer] = $this->fixture();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
            ->create(['invoice_number' => 'INV/26-27/0007']);

        $this->actingAs($user)->get(route('invoices.index', ['search' => $term]))
            ->assertOk()
            ->assertSee('INV/26-27/0007');
    }

    public function test_an_invoice_can_be_found_by_its_amount(): void
    {
        // A bank statement gives you a figure, not a bill number.
        // finalized_at is backdated on purpose: the notification bell lists
        // invoices issued in the last 24 h on every page, so a same-day
        // fixture shows up in the chrome and not just the list under test.
        [$user, $company, $customer] = $this->fixture();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
            ->create(['invoice_number' => 'INV/26-27/0009', 'grand_total' => 11800, 'finalized_at' => now()->subDays(3)]);
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
            ->create(['invoice_number' => 'INV/26-27/0010', 'grand_total' => 5000, 'finalized_at' => now()->subDays(3)]);

        foreach (['11800', '11,800', '₹11800'] as $term) {
            $this->actingAs($user)->get(route('invoices.index', ['search' => $term]))
                ->assertOk()
                ->assertSee('INV/26-27/0009')
                ->assertDontSee('INV/26-27/0010');
        }
    }

    public function test_an_amount_with_paise_still_matches(): void
    {
        // grand_total is DECIMAL(*,2). Searching it means comparing a typed
        // number against that column, and a whole-rupee fixture would not
        // notice if the comparison lost the paise.
        [$user, $company, $customer] = $this->fixture();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
            ->create(['invoice_number' => 'INV/26-27/0011', 'grand_total' => 1180.35, 'finalized_at' => now()->subDays(3)]);

        $this->actingAs($user)->get(route('invoices.index', ['search' => '1,180.35']))
            ->assertOk()
            ->assertSee('INV/26-27/0011');
    }

    public function test_the_invoice_list_can_be_bounded_by_invoice_date(): void
    {
        [$user, $company, $customer] = $this->fixture();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()->create([
            'invoice_number' => 'THIS/MONTH',
            'invoice_date' => now()->startOfMonth()->addDay()->toDateString(),
            'finalized_at' => now()->startOfMonth()->addDay(),
        ]);
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()->create([
            'invoice_number' => 'LAST/MONTH',
            'invoice_date' => now()->subMonth()->startOfMonth()->addDay()->toDateString(),
            'finalized_at' => now()->subMonth()->startOfMonth()->addDay(),
        ]);

        $this->actingAs($user)->get(route('invoices.index', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]))->assertOk()->assertSee('THIS/MONTH')->assertDontSee('LAST/MONTH');
    }

    public function test_an_unparseable_date_in_the_url_is_ignored_rather_than_fatal(): void
    {
        // These arrive from bookmarks and hand-edited URLs. A bad one should
        // widen the list, not 500 the page a business runs its day from.
        [$user, $company, $customer] = $this->fixture();
        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
            ->create(['invoice_number' => 'STILL/HERE']);

        $this->actingAs($user)->get(route('invoices.index', ['from' => 'not-a-date', 'to' => '??']))
            ->assertOk()
            ->assertSee('STILL/HERE');
    }
}
