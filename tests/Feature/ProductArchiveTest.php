<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Archiving a product, and - the part that was missing - getting back out.
 *
 * A tester reported the archive logic as "not understandable", and the flow
 * earned it. Deleting a product that had been invoiced silently archived it
 * instead. The list then offered a checkbox labelled "Show archived" which did
 * not show archived products alongside the others, it replaced the list with
 * only those. And there was no un-archive anywhere: the only way back was to
 * open Edit on the row and happen to notice an "Active" tick box in the form.
 *
 * So the fix is three things, and this pins all three: named states instead of
 * a checkbox, a Restore action on the row, and copy that says what archiving
 * actually does at each place someone meets it.
 */
class ProductArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $state = State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
        $user = User::factory()->create();
        $company = Company::factory()->recycle($user)->create(['state_id' => $state->id, 'onboarded_at' => now()]);
        $user->switchCompany($company);

        return $user;
    }

    private function productFor(User $user, string $name): Product
    {
        return Product::create([
            'user_id' => $user->id,
            'company_id' => $user->companies()->first()->id,
            'name' => $name, 'kind' => 'service', 'hsn_sac' => '998314',
            'unit' => 'HRS', 'rate' => 1500, 'gst_rate' => 18,
        ]);
    }

    /** Put the product on a real invoice line, which is what makes it un-deletable. */
    private function bill(User $user, Product $product): void
    {
        $company = $user->companies()->first();
        $customer = Customer::factory()->recycle($user)->recycle($company)
            ->create(['state_id' => $company->state_id]);
        $invoice = Invoice::factory()->recycle($user)->recycle($company)->recycle($customer)
            ->finalized()->create();

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'description' => $product->name,
        ]);
    }

    public function test_deleting_an_invoiced_product_archives_it_and_says_where_it_went(): void
    {
        $user = $this->owner();
        $product = $this->productFor($user, 'Consulting hour');
        $this->bill($user, $product);

        $this->actingAs($user)->delete(route('products.destroy', $product))->assertRedirect();

        $this->assertFalse($product->fresh()->is_active, 'the product should be archived, not deleted');
        $this->assertDatabaseHas('products', ['id' => $product->id]);

        // The message has to answer the two questions someone asks at this
        // moment: did I just lose my invoice history, and how do I undo this?
        $flash = session('status');
        $this->assertStringContainsString('archived, not deleted', $flash);
        $this->assertStringContainsString('Restore', $flash);
    }

    public function test_deleting_a_product_that_was_never_invoiced_really_deletes_it(): void
    {
        $user = $this->owner();
        $product = $this->productFor($user, 'Never sold');

        $this->actingAs($user)->delete(route('products.destroy', $product))->assertRedirect();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_the_three_tabs_show_three_different_sets(): void
    {
        $user = $this->owner();
        $live = $this->productFor($user, 'Still selling');
        $retired = $this->productFor($user, 'Retired service');
        $retired->update(['is_active' => false]);

        $this->actingAs($user)->get(route('products.index'))
            ->assertOk()->assertSee($live->name)->assertDontSee($retired->name);

        $this->actingAs($user)->get(route('products.index', ['status' => 'archived']))
            ->assertOk()->assertSee($retired->name)->assertDontSee($live->name);

        $this->actingAs($user)->get(route('products.index', ['status' => 'all']))
            ->assertOk()->assertSee($retired->name)->assertSee($live->name);
    }

    public function test_the_archived_tab_explains_itself_and_offers_the_way_back(): void
    {
        $user = $this->owner();
        $retired = $this->productFor($user, 'Retired service');
        $retired->update(['is_active' => false]);

        $this->actingAs($user)->get(route('products.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('Archived is not deleted')
            ->assertSee('Restore')
            ->assertSee(route('products.restore', $retired), false);
    }

    public function test_restore_puts_the_product_back_in_the_catalogue_and_the_autocomplete(): void
    {
        $user = $this->owner();
        $product = $this->productFor($user, 'Consulting hour');
        $product->update(['is_active' => false]);

        // Archived products are kept out of the invoice form's autocomplete -
        // that is the one thing archiving is for.
        $this->actingAs($user)->getJson(route('products.search', ['q' => 'Consulting']))
            ->assertOk()->assertJsonCount(0);

        $this->actingAs($user)->post(route('products.restore', $product))->assertRedirect();

        $this->assertTrue($product->fresh()->is_active);
        $this->actingAs($user)->getJson(route('products.search', ['q' => 'Consulting']))
            ->assertOk()->assertJsonCount(1);
    }

    public function test_the_old_show_archived_links_still_work(): void
    {
        $user = $this->owner();
        $retired = $this->productFor($user, 'Retired service');
        $retired->update(['is_active' => false]);

        // only_inactive=1 is sitting in bookmarks and browser history. Renaming
        // the filter should not break the links people already have.
        $this->actingAs($user)->get(route('products.index', ['only_inactive' => 1]))
            ->assertOk()->assertSee($retired->name);
    }

    public function test_restore_is_scoped_to_the_owner(): void
    {
        $owner = $this->owner();
        $product = $this->productFor($owner, 'Consulting hour');
        $product->update(['is_active' => false]);

        $stranger = $this->owner();

        $this->actingAs($stranger)->post(route('products.restore', $product))->assertForbidden();
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_an_empty_archive_says_so_rather_than_looking_like_a_broken_filter(): void
    {
        $user = $this->owner();
        $this->productFor($user, 'Still selling');

        $this->actingAs($user)->get(route('products.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('Nothing is archived');
    }
}
