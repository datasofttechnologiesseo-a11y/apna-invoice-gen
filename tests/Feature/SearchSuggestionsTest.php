<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The typeahead behind the invoice and customer search boxes.
 *
 * A tester reported that the search bar "is not working like a Google search
 * bar does" - you typed, guessed, pressed Enter and waited for a page load to
 * find out whether you had guessed right. The suggestions make it a lookup.
 *
 * The property that actually matters here is not that the dropdown returns
 * rows. It is that the dropdown and the list agree: both go through
 * Invoice::scopeSearch and Customer::scopeSearch, so a suggestion can never be
 * a bill that pressing Enter then fails to find. A dropdown that offers rows
 * the results page then denies is worse than no dropdown at all, and
 * test_every_suggestion_survives_pressing_enter is what stops the two drifting.
 */
class SearchSuggestionsTest extends TestCase
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

        return [$user, $company, $customer];
    }

    /** Flatten the response into ['Group label' => ['title', ...]] for readable assertions. */
    private function titles(array $payload): array
    {
        $out = [];
        foreach ($payload['groups'] ?? [] as $group) {
            $out[$group['label']] = array_column($group['items'], 'title');
        }

        return $out;
    }

    public function test_three_letters_of_a_customer_name_bring_back_both_the_bills_and_the_customer(): void
    {
        [$user, $company, $customer] = $this->fixture();

        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
            ->create(['invoice_number' => 'INV/26-27/0044']);

        $titles = $this->titles(
            $this->actingAs($user)->getJson(route('search.suggest', ['q' => 'kir']))
                ->assertOk()
                ->json()
        );

        $this->assertContains('INV/26-27/0044', $titles['Invoices'] ?? []);
        $this->assertContains('Kirana Traders', $titles['Customers'] ?? []);
    }

    public function test_an_amount_off_a_bank_statement_suggests_the_invoice(): void
    {
        [$user, $company, $customer] = $this->fixture();

        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
            ->create(['invoice_number' => 'INV/26-27/0045', 'grand_total' => 11800]);

        // The way the figure is written on a statement, not the way it is stored.
        $titles = $this->titles(
            $this->actingAs($user)->getJson(route('search.suggest', ['q' => '11,800']))->assertOk()->json()
        );

        $this->assertContains('INV/26-27/0045', $titles['Invoices'] ?? []);
    }

    public function test_every_suggestion_survives_pressing_enter(): void
    {
        [$user, $company, $customer] = $this->fixture();

        foreach (['INV/26-27/0051', 'INV/26-27/0052', 'INV/26-27/0053'] as $number) {
            Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()
                ->create(['invoice_number' => $number]);
        }

        foreach (['kirana', '98765-43210', '27AAPFU0939F1ZV', 'INV/26-27/005'] as $term) {
            $suggested = $this->titles(
                $this->actingAs($user)->getJson(route('search.suggest', ['q' => $term]))->assertOk()->json()
            )['Invoices'] ?? [];

            $this->assertNotEmpty($suggested, "no invoice suggested for '{$term}'");

            // The same term through the list filter must show every bill the
            // dropdown just offered. This is the whole contract.
            $page = $this->actingAs($user)->get(route('invoices.index', ['search' => $term]))->assertOk();
            foreach ($suggested as $number) {
                $page->assertSee($number);
            }
        }
    }

    public function test_suggestions_never_cross_a_company_boundary(): void
    {
        [$user] = $this->fixture();

        // A second business with a confusingly similar customer name.
        $state = State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
        $stranger = User::factory()->create();
        $otherCompany = Company::factory()->recycle($stranger)->create(['state_id' => $state->id, 'onboarded_at' => now()]);
        $otherCustomer = Customer::factory()->recycle($stranger)->recycle($otherCompany)
            ->create(['name' => 'Kirana Wholesale', 'state_id' => $state->id]);
        Invoice::factory()->recycle($stranger)->recycle($otherCompany)->recycle($otherCustomer)->finalized()
            ->create(['invoice_number' => 'NOT/YOURS/0001']);

        $payload = $this->actingAs($user)->getJson(route('search.suggest', ['q' => 'kirana']))->assertOk();

        $payload->assertDontSee('NOT/YOURS/0001');
        $payload->assertDontSee('Kirana Wholesale');
    }

    public function test_an_empty_term_suggests_nothing(): void
    {
        [$user] = $this->fixture();

        $this->actingAs($user)->getJson(route('search.suggest', ['q' => '   ']))
            ->assertOk()
            ->assertJson(['groups' => []]);
    }

    public function test_the_pill_says_overdue_once_the_due_date_has_passed(): void
    {
        [$user, $company, $customer] = $this->fixture();

        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()->create([
            'invoice_number' => 'INV/26-27/0060',
            'due_date' => now()->subDays(10)->toDateString(),
            'balance' => 1180,
        ]);

        $item = $this->actingAs($user)->getJson(route('search.suggest', ['q' => 'INV/26-27/0060']))
            ->assertOk()->json('groups.0.items.0');

        $this->assertSame('Overdue', $item['badge']);
        $this->assertSame('danger', $item['tone']);
    }

    public function test_a_bill_due_today_is_not_yet_overdue(): void
    {
        // The invoice list, the dashboard ring and the notification bell all
        // define overdue as due_date < today. A pill that says Overdue on a
        // bill the rest of the app calls current is the same class of bug as a
        // suggestion the list cannot find: the dropdown contradicting the page.
        [$user, $company, $customer] = $this->fixture();

        Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)->finalized()->create([
            'invoice_number' => 'INV/26-27/0070',
            'due_date' => now()->toDateString(),
            'balance' => 1180,
            'paid_amount' => 0,
        ]);

        $item = $this->actingAs($user)->getJson(route('search.suggest', ['q' => 'INV/26-27/0070']))
            ->assertOk()->json('groups.0.items.0');

        $this->assertSame('Unpaid', $item['badge']);
        $this->assertSame('due', $item['tone']);
    }

    public function test_the_customer_scope_points_at_the_ledger_instead_of_the_invoice_list(): void
    {
        [$user, , $customer] = $this->fixture();

        $onInvoices = $this->actingAs($user)
            ->getJson(route('search.suggest', ['q' => 'kirana', 'scope' => 'invoices']))
            ->assertOk()->json('groups');
        $onCustomers = $this->actingAs($user)
            ->getJson(route('search.suggest', ['q' => 'kirana', 'scope' => 'customers']))
            ->assertOk()->json('groups');

        // Same person, different intent: from the invoice list you want their
        // bills, from the customer list you want their account.
        $fromInvoices = collect($onInvoices)->firstWhere('label', 'Customers')['items'][0]['url'];
        $fromCustomers = collect($onCustomers)->firstWhere('label', 'Customers')['items'][0]['url'];

        $this->assertSame(route('invoices.index', ['search' => 'Kirana Traders']), $fromInvoices);
        $this->assertSame(route('customers.ledger', $customer), $fromCustomers);
    }

    public function test_the_endpoint_is_behind_authentication(): void
    {
        $this->getJson(route('search.suggest', ['q' => 'kirana']))->assertUnauthorized();
    }
}
