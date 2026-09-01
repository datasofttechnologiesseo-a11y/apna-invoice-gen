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
 * Regression cover for the dashboard "needs your attention" list.
 *
 * This shipped broken: the controller emitted tone 'red' while the view's map
 * had been renamed to 'danger', so the dashboard threw
 * "Undefined array key" for any user who had one.
 *
 * It escaped every existing test - including the one that walks every GET
 * route asserting no 500 - because the list is only BUILT when the account has
 * an overdue invoice, a stale draft, or an unsent one. Fixtures with a clean
 * account never entered the loop, so route-level smoke tests reported the
 * dashboard healthy while it was broken for exactly the users who most needed
 * it: the ones with money outstanding.
 *
 * These tests create each condition on purpose so every tone is rendered.
 */
class DashboardActionItemsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $state = State::factory()->create(['gst_code' => '27']);
        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create([
            'state_id' => $state->id, 'onboarded_at' => now(),
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
        $this->customer = Customer::factory()->recycle($this->user)->recycle($this->company)->create();
    }

    private function invoice(array $attributes): Invoice
    {
        return Invoice::factory()
            ->recycle($this->user)->recycle($this->company)->recycle($this->customer)
            ->create($attributes);
    }

    public function test_an_overdue_invoice_renders_its_action_item(): void
    {
        $this->invoice([
            'status' => 'final',
            'finalized_at' => now()->subDays(40),
            'invoice_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
            'balance' => 5000,
        ]);

        $res = $this->actingAs($this->user)->get('/dashboard');

        $res->assertOk();
        $res->assertSee('overdue', false);
    }

    public function test_a_stale_draft_renders_its_action_item(): void
    {
        $invoice = $this->invoice(['status' => 'draft', 'balance' => 1000]);
        // The query keys off created_at, which the factory stamps as "now".
        $invoice->forceFill(['created_at' => now()->subDays(20)])->save();

        $this->actingAs($this->user)->get('/dashboard')->assertOk();
    }

    public function test_an_unsent_issued_invoice_renders_its_action_item(): void
    {
        $this->invoice([
            'status' => 'final',
            'finalized_at' => now()->subDays(3),
            'invoice_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(),
            'balance' => 2500,
        ]);

        $this->actingAs($this->user)->get('/dashboard')->assertOk();
    }

    public function test_all_three_action_items_render_together(): void
    {
        // The case that actually broke production: every tone on screen at once.
        $this->invoice([
            'status' => 'final', 'finalized_at' => now()->subDays(40),
            'invoice_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(), 'balance' => 5000,
        ]);

        $draft = $this->invoice(['status' => 'draft', 'balance' => 1000]);
        $draft->forceFill(['created_at' => now()->subDays(20)])->save();

        $this->invoice([
            'status' => 'final', 'finalized_at' => now()->subDays(3),
            'invoice_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(), 'balance' => 2500,
        ]);

        $this->actingAs($this->user)->get('/dashboard')->assertOk();
    }

    public function test_every_tone_the_controller_emits_exists_in_the_views_map(): void
    {
        // The structural guard. Rendering proves today's three tones resolve;
        // this catches a fourth being added later with a name the view does not
        // know, which is the exact shape of the bug that shipped.
        $controller = file_get_contents(app_path('Http/Controllers/DashboardController.php'));
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));

        preg_match_all("/'tone'\s*=>\s*'([a-z_]+)'/", $controller, $emitted);
        preg_match_all("/^\s*'([a-z_]+)'\s*=>\s*\['ring'/m", $view, $known);

        $this->assertNotEmpty($emitted[1], 'expected the controller to emit tones');
        $this->assertNotEmpty($known[1], 'expected the view to define a tone map');

        $missing = array_diff(array_unique($emitted[1]), $known[1]);
        $this->assertSame([], array_values($missing),
            'DashboardController emits tone(s) the dashboard view cannot resolve: '
            . implode(', ', $missing));
    }
}
