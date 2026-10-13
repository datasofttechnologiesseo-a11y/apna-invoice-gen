<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Models\AuditLog;
use App\Models\DataBreach;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The super-admin area (every business's data, impersonation, password
 * resets, the breach register) must be closed to every normal account, and
 * work for the operator. Plus the remaining public and helper routes: invoice
 * templates, the public blog API, the Sanctum cookie and the marketing pages.
 *
 * SUPER_ADMIN_EMAILS is empty in tests, so the is_super_admin flag alone
 * makes an operator.
 */
class AdminAndMiscRoutesTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();
        $this->admin = User::factory()->create(['name' => 'Operator Admin', 'email' => 'ops@apnainvoice.example', 'is_super_admin' => true]);
        $biz = $this->makeBusiness([], ['name' => 'Normal Owner', 'email' => 'owner@sharmatraders.example']);
        $this->user = $this->ownerOf($biz);
        $this->webIssued($this->user, $this->customerOf($biz));
    }

    private function breach(array $over = []): DataBreach
    {
        return DataBreach::create(array_merge([
            'title' => 'Leaked CSV export', 'description' => 'A backup CSV was mailed to the wrong address.',
            'status' => 'open', 'severity' => 'high', 'discovered_at' => now()->subHours(5),
            'affected_users' => 3, 'logged_by' => $this->admin->id,
        ], $over));
    }

    /** Route parameters for an admin route, by the kind of record it names. */
    private function params(?string $kind): array
    {
        return match ($kind) {
            'user' => ['user' => $this->user->id],
            'breach' => ['breach' => $this->breach()->id],
            'post' => ['post' => Post::factory()->create()->id],
            default => [],
        };
    }

    public static function adminPages(): array
    {
        return [
            'dashboard' => ['admin.dashboard', null],
            'users' => ['admin.users', null],
            'user detail' => ['admin.users.show', 'user'],
            'all invoices' => ['admin.invoices', null],
            'all companies' => ['admin.companies', null],
            'all customers' => ['admin.customers', null],
            'breach register' => ['admin.breaches.index', null],
            'new breach' => ['admin.breaches.create', null],
            'breach record' => ['admin.breaches.show', 'breach'],
            'blog list' => ['admin.blog.index', null],
            'new post' => ['admin.blog.create', null],
            'edit post' => ['admin.blog.edit', 'post'],
        ];
    }

    public static function adminActions(): array
    {
        return [
            'log breach' => ['POST', 'admin.breaches.store', null],
            'update breach' => ['PATCH', 'admin.breaches.update', 'breach'],
            'stamp notification' => ['POST', 'admin.breaches.notify', 'breach'],
            'impersonate' => ['POST', 'admin.users.impersonate', 'user'],
            'reset password' => ['POST', 'admin.users.reset-password', 'user'],
            'create post' => ['POST', 'admin.blog.store', null],
            'update post' => ['PUT', 'admin.blog.update', 'post'],
            'delete post' => ['DELETE', 'admin.blog.destroy', 'post'],
            'toggle post' => ['POST', 'admin.blog.toggle', 'post'],
            'preview post' => ['POST', 'admin.blog.preview', null],
            'upload image' => ['POST', 'admin.blog.upload-image', null],
        ];
    }

    // ---- admin.* access ----------------------------------------------------

    #[DataProvider('adminPages')]
    public function test_a_normal_account_is_refused_every_admin_page_and_the_operator_gets_in(string $name, ?string $kind): void
    {
        $url = route($name, $this->params($kind));

        $this->actingAs($this->user)->get($url)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertRedirect(route('login'));

        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    #[DataProvider('adminActions')]
    public function test_a_normal_account_cannot_run_any_admin_action(string $verb, string $name, ?string $kind): void
    {
        $url = route($name, $this->params($kind));
        $before = [Post::count(), DataBreach::count(), (string) $this->user->fresh()->password];

        $this->actingAs($this->user)->call($verb, $url, [
            'title' => 'x', 'body' => 'x', 'status' => 'open', 'severity' => 'low', 'description' => 'x',
            'discovered_at' => now()->toDateString(), 'affected_users' => 1, 'target' => 'board',
            'password' => 'TakeOver12345', 'password_confirmation' => 'TakeOver12345',
        ])->assertForbidden();

        $this->assertSame($before, [Post::count(), DataBreach::count(), (string) $this->user->fresh()->password]);
        $this->assertAuthenticatedAs($this->user);
    }

    // ---- breach register ---------------------------------------------------

    public function test_the_operator_logs_a_breach_and_stamps_both_notifications(): void
    {
        Mail::fake();
        $this->actingAs($this->admin)->get(route('admin.breaches.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.breaches.create'))->assertOk();

        // Validation: everything required, statuses and severities from the list only.
        $this->actingAs($this->admin)->post(route('admin.breaches.store'), [])
            ->assertSessionHasErrors(['title', 'description', 'status', 'severity', 'discovered_at', 'affected_users']);
        $this->actingAs($this->admin)->post(route('admin.breaches.store'), [
            'title' => 'x', 'description' => 'x', 'status' => 'forgotten', 'severity' => 'apocalyptic',
            'discovered_at' => 'yesterday-ish', 'affected_users' => -1,
        ])->assertSessionHasErrors(['status', 'severity', 'discovered_at', 'affected_users']);
        $this->assertSame(0, DataBreach::count());

        $this->actingAs($this->admin)->post(route('admin.breaches.store'), [
            'title' => 'Invoice PDFs exposed via old share links',
            'description' => 'Share links issued before the expiry fix stayed valid.',
            'status' => 'open', 'severity' => 'high',
            'occurred_at' => '2026-10-10 09:00:00', 'discovered_at' => '2026-10-12 08:30:00',
            'affected_users' => 42, 'data_categories' => 'Customer names, GSTINs, invoice amounts',
        ])->assertSessionHasNoErrors();
        $breach = DataBreach::firstOrFail();
        $this->assertSame($this->admin->id, (int) $breach->logged_by);
        $this->assertSame(42, $breach->affected_users);
        $this->assertSame('open', $breach->status);

        $this->actingAs($this->admin)->get(route('admin.breaches.show', $breach))->assertOk()
            ->assertSee('Invoice PDFs exposed via old share links');
        $this->actingAs($this->admin)->get(route('admin.breaches.index'))->assertOk()
            ->assertSee('Invoice PDFs exposed via old share links');

        $this->actingAs($this->admin)->from(route('admin.breaches.show', $breach))->patch(route('admin.breaches.update', $breach), [
            'title' => 'Invoice PDFs exposed via old share links', 'description' => 'Share links issued before the expiry fix stayed valid.',
            'status' => 'contained', 'severity' => 'high', 'discovered_at' => '2026-10-12 08:30:00', 'affected_users' => 42,
            'remediation' => 'All old links revoked.',
        ])->assertRedirect(route('admin.breaches.show', $breach))->assertSessionHas('status', 'Breach record updated.');
        $this->assertSame('contained', $breach->fresh()->status);
        $this->assertSame('All old links revoked.', $breach->fresh()->remediation);
        $this->actingAs($this->admin)->patch(route('admin.breaches.update', $breach), ['status' => 'closed'])
            ->assertSessionHasErrors(['title', 'description']);
        $this->assertSame('contained', $breach->fresh()->status);

        // Notification milestones: the Board first, then the affected users;
        // with both stamped the breach moves to "reported".
        $this->actingAs($this->admin)->from(route('admin.breaches.show', $breach))
            ->post(route('admin.breaches.notify', $breach), ['target' => 'board'])
            ->assertSessionHas('status', 'Recorded: Data Protection Board notified.');
        $this->assertSame(now()->toDateTimeString(), $breach->fresh()->board_notified_at->toDateTimeString());
        $this->assertSame('contained', $breach->fresh()->status);

        $this->actingAs($this->admin)->from(route('admin.breaches.show', $breach))
            ->post(route('admin.breaches.notify', $breach), ['target' => 'users'])
            ->assertSessionHas('status', 'Recorded: affected users notified.');
        $breach->refresh();
        $this->assertNotNull($breach->users_notified_at);
        $this->assertSame('reported', $breach->status);
        $this->assertFalse($breach->notificationOverdue());

        $this->actingAs($this->admin)->post(route('admin.breaches.notify', $breach), ['target' => 'press'])
            ->assertSessionHasErrors('target');

        // The register records that people were told; it does not email them
        // itself (Admin\BreachController::notify only stamps the time).
        Mail::assertNothingSent();
    }

    // ---- password reset ----------------------------------------------------

    public function test_the_operator_can_reset_a_users_password_but_not_their_own_or_another_operators(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)->post(route('admin.users.reset-password', $this->user), [
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post(route('admin.users.reset-password', $this->user), [
            'password' => 'Fresh-Start-2026', 'password_confirmation' => 'Different-2026',
        ])->assertSessionHasErrors('password');
        $this->assertFalse(Hash::check('Fresh-Start-2026', $this->user->fresh()->password));

        $this->actingAs($this->admin)->post(route('admin.users.reset-password', $this->user), [
            'password' => 'Fresh-Start-2026', 'password_confirmation' => 'Fresh-Start-2026',
        ])->assertRedirect(route('admin.users.show', $this->user))
            ->assertSessionHas('status', 'Password reset for Normal Owner.');
        $this->assertTrue(Hash::check('Fresh-Start-2026', $this->user->fresh()->password));
        $entry = AuditLog::where('action', 'admin.user.password_reset')->firstOrFail();
        $this->assertStringContainsString('Operator Admin reset the password for Normal Owner', $entry->summary);
        // The new password is set directly; no reset email goes out.
        Mail::assertNothingSent();

        // The new password signs the user in.
        $this->app['auth']->forgetGuards();
        $this->post(route('login'), ['email' => 'owner@sharmatraders.example', 'password' => 'Fresh-Start-2026']);
        $this->assertAuthenticatedAs($this->user->fresh());

        // Not the operator's own password, not another operator's.
        $other = User::factory()->create(['is_super_admin' => true]);
        $otherHash = (string) $other->password;
        $this->actingAs($this->admin)->post(route('admin.users.reset-password', $this->admin), [
            'password' => 'Fresh-Start-2026', 'password_confirmation' => 'Fresh-Start-2026',
        ])->assertStatus(422);
        $this->actingAs($this->admin)->post(route('admin.users.reset-password', $other), [
            'password' => 'Fresh-Start-2026', 'password_confirmation' => 'Fresh-Start-2026',
        ])->assertForbidden();
        $this->assertSame($otherHash, (string) $other->fresh()->password);
    }

    // ---- blog editing ------------------------------------------------------

    public function test_the_operator_edits_previews_and_uploads_images_for_the_blog(): void
    {
        Storage::fake('public');
        $post = Post::factory()->create(['title' => 'How to raise a GST credit note']);

        $this->actingAs($this->admin)->get(route('admin.blog.edit', $post))->assertOk()
            ->assertSee('How to raise a GST credit note');

        $preview = $this->actingAs($this->admin)->post(route('admin.blog.preview'), [
            'body' => "## Section 34\n\nA credit note **reduces** the tax.\n\n<script>alert(1)</script>",
        ])->assertOk();
        $this->assertStringContainsString('<strong>reduces</strong>', $preview->getContent());
        $this->assertStringContainsString('<h2', $preview->getContent());
        $this->assertStringNotContainsString('<script>', $preview->getContent(), 'the preview never runs script typed into a post');
        $this->assertSame(1, Post::count(), 'a preview saves nothing');

        $up = $this->actingAs($this->admin)->post(route('admin.blog.upload-image'), [
            'image' => UploadedFile::fake()->image('chart.png', 2000, 1000),
            'alt' => 'GSTR-1 table 9B',
        ])->assertCreated();
        $path = (string) $up->json('path');
        $this->assertStringStartsWith('posts/inline/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('storage/' . $path, (string) $up->json('url'));
        $this->assertSame('GSTR-1 table 9B', $up->json('alt'));

        $this->actingAs($this->admin)->postJson(route('admin.blog.upload-image'), [
            'image' => UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('image');
        $this->actingAs($this->admin)->postJson(route('admin.blog.upload-image'), [])
            ->assertStatus(422)->assertJsonValidationErrors('image');
    }

    // ---- invoice templates -------------------------------------------------

    public function test_invoice_templates_list_and_preview(): void
    {
        $page = $this->actingAs($this->user)->get(route('invoices.templates'))->assertOk();
        $page->assertSee('Blank invoice')->assertSee('Consulting / Professional Services')->assertSee('Product Sale (Goods)')
            ->assertSee(route('invoices.templates.preview', 'consulting'), false);

        // The preview is a sample: 1 x 10,000 @ 18%, same state:
        // taxable 10,000; CGST 900 + SGST 900; grand 11,800. Nothing is saved.
        $invoicesBefore = \App\Models\Invoice::count();
        $seen = null;
        View::composer('invoices.pdf', function ($v) use (&$seen) {
            $seen = $v->getData()['invoice'];
        });
        $pdf = $this->actingAs($this->user)->get(route('invoices.templates.preview', 'consulting'));
        $this->assertIsPdf($pdf);
        $this->assertNotNull($seen);
        $this->assertMoney(10000, $seen->subtotal);
        $this->assertMoney(900, $seen->total_cgst);
        $this->assertMoney(900, $seen->total_sgst);
        $this->assertMoney(11800, $seen->grand_total);
        $this->assertSame('INV-SAMPLE', $seen->invoice_number);
        $this->assertSame($invoicesBefore, \App\Models\Invoice::count());

        $this->actingAs($this->user)->get(route('invoices.templates.preview', 'no-such-template'))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->get(route('invoices.templates'))->assertRedirect(route('login'));
    }

    // ---- public blog API ---------------------------------------------------

    public function test_the_public_blog_api_serves_published_posts_only(): void
    {
        $live = Post::factory()->published()->create(['title' => 'GST on rent explained', 'slug' => 'gst-on-rent-explained',
            'body' => "Rent is a **taxable** supply.", 'view_count' => 0]);
        Post::factory()->create(['title' => 'Unfinished draft', 'slug' => 'unfinished-draft']);
        Post::factory()->scheduled()->create(['title' => 'Next week post', 'slug' => 'next-week-post']);

        $list = $this->getJson('/api/blog')->assertOk();
        $this->assertSame(['gst-on-rent-explained'], collect($list->json('data'))->pluck('slug')->all());
        $list->assertJsonPath('meta.total', 1);

        $one = $this->getJson('/api/blog/gst-on-rent-explained')->assertOk();
        $one->assertJsonPath('title', 'GST on rent explained');
        $this->assertStringContainsString('<strong>taxable</strong>', (string) $one->json('body_html'));
        $this->assertSame(1, (int) $live->fresh()->view_count);

        $this->getJson('/api/blog/unfinished-draft')->assertNotFound();
        $this->getJson('/api/blog/next-week-post')->assertNotFound();
        $this->getJson('/api/blog/Not_A_Slug')->assertNotFound();
    }

    // ---- Sanctum -----------------------------------------------------------

    public function test_the_sanctum_csrf_cookie_route_answers_with_no_content(): void
    {
        $res = $this->get('/sanctum/csrf-cookie');
        $res->assertNoContent();
        $res->assertCookie('XSRF-TOKEN');
    }

    // ---- marketing pages ---------------------------------------------------

    public static function marketingPages(): array
    {
        return array_combine($p = [
            'about', 'faq', 'features', 'how-to-use', 'pricing', 'privacy', 'terms', 'refund', 'cookies',
            'security', 'press', 'partners', 'billing-software', 'gst-invoice-format', 'credit-note-format', 'cash-memo-format',
        ], array_map(fn ($n) => [$n], $p));
    }

    #[DataProvider('marketingPages')]
    public function test_each_marketing_page_opens_for_a_visitor_with_a_title(string $page): void
    {
        $res = $this->get(route('pages.' . $page))->assertOk();
        $html = (string) $res->getContent();
        $this->assertSame(1, preg_match_all('#<title>(.*?)</title>#is', $html, $m), 'exactly one <title>');
        $title = trim(html_entity_decode($m[1][0], ENT_QUOTES | ENT_HTML5));
        $this->assertNotSame('', $title);
        $this->assertGreaterThan(10, mb_strlen($title), "the {$page} title says what the page is: '{$title}'");
    }

    public function test_no_two_marketing_pages_share_a_title(): void
    {
        $titles = [];
        foreach (array_keys(self::marketingPages()) as $page) {
            preg_match('#<title>(.*?)</title>#is', (string) $this->get(route('pages.' . $page))->getContent(), $m);
            $titles[$page] = trim(html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5));
        }
        $dupes = array_filter(array_count_values($titles), fn ($n) => $n > 1);
        $this->assertSame([], $dupes, 'each page has its own title: ' . json_encode($titles));
    }
}
