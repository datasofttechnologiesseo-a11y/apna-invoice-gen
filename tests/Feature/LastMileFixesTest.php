<?php

namespace Tests\Feature;

use App\Mail\BackupMail;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\Referral;
use App\Models\State;
use App\Models\User;
use App\Services\Gstr3bBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The last items from the full QA round: privacy of the app's referral list,
 * referrals from app sign-ups, no plain-text password in the sign-up session,
 * stock in the app's item search, signed links checked before the record is
 * looked up, separate rate limits per action, and late supplier invoices.
 */
class LastMileFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Company $company;
    private State $mh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-12 11:00', 'Asia/Kolkata'));
        $this->mh = State::factory()->create(['gst_code' => '27', 'name' => 'Maharashtra']);
        $this->owner = User::factory()->create();
        $this->company = Company::factory()->create([
            'user_id' => $this->owner->id, 'state_id' => $this->mh->id, 'gstin' => '27ABCDE1234F1Z5', 'onboarded_at' => now(),
        ]);
        $this->owner->forceFill(['active_company_id' => $this->company->id])->save();
    }

    public function test_the_app_referral_list_shows_friends_names_not_their_email_addresses(): void
    {
        $friend = User::factory()->create(['name' => 'Kavita Rao', 'email' => 'kavita.private@example.com']);
        Referral::create([
            'referrer_user_id' => $this->owner->id, 'referee_user_id' => $friend->id,
            'referral_code' => 'X', 'reward_status' => 'pending', 'signed_up_at' => now(),
        ]);

        Sanctum::actingAs($this->owner);
        $body = $this->getJson('/api/referrals')->assertOk()->getContent();

        $this->assertStringContainsString('Kavita Rao', $body);
        $this->assertStringNotContainsString('kavita.private@example.com', $body);
    }

    public function test_a_sign_up_in_the_app_with_a_referral_code_counts_like_the_website(): void
    {
        $this->owner->forceFill(['referral_code' => 'AI-OWNR'])->save();

        $this->postJson('/api/auth/register', [
            'name' => 'Ritu Shah', 'phone' => '9876543210', 'email' => 'ritu@example.com',
            'password' => 'Str0ng!Passw0rd', 'password_confirmation' => 'Str0ng!Passw0rd', 'referral_code' => 'ai-ownr',
        ])->assertSuccessful();

        $ritu = User::where('email', 'ritu@example.com')->firstOrFail();
        $this->assertSame($this->owner->id, (int) $ritu->referred_by_user_id);
        $this->assertTrue(Referral::where('referrer_user_id', $this->owner->id)->where('referee_user_id', $ritu->id)->exists());
    }

    public function test_the_website_sign_up_never_keeps_the_password_in_plain_text(): void
    {
        $this->post('/register', [
            'name' => 'Arun Mehta', 'phone' => '9123456780', 'email' => 'arun@example.com',
            'password' => 'Str0ng!Passw0rd', 'password_confirmation' => 'Str0ng!Passw0rd', 'terms_accepted' => '1',
        ])->assertRedirect(route('register.verify'));

        $stored = session('pending_registration')['password'];
        $this->assertNotSame('Str0ng!Passw0rd', $stored);
        $this->assertTrue(Hash::check('Str0ng!Passw0rd', $stored));
    }

    public function test_the_app_item_search_shows_stock_on_hand(): void
    {
        Product::create([
            'user_id' => $this->owner->id, 'company_id' => $this->company->id, 'name' => 'Steel rack', 'kind' => 'goods',
            'hsn_sac' => '94032090', 'unit' => 'NOS', 'rate' => 4500, 'gst_rate' => 18, 'is_active' => true,
            'track_stock' => true, 'opening_stock' => 12,
        ]);
        Product::create([
            'user_id' => $this->owner->id, 'company_id' => $this->company->id, 'name' => 'Steel installation', 'kind' => 'service',
            'hsn_sac' => '998719', 'unit' => 'OTH', 'rate' => 500, 'gst_rate' => 18, 'is_active' => true,
        ]);

        Sanctum::actingAs($this->owner);
        $rows = collect($this->getJson('/api/products/search?q=Steel')->assertOk()->json())->keyBy('name');

        $this->assertEqualsWithDelta(12, $rows['Steel rack']['stock'], 0.001);
        $this->assertNull($rows['Steel installation']['stock'], 'an item that does not track stock has no figure');
    }

    public function test_a_public_link_is_refused_the_same_way_whether_or_not_the_invoice_exists(): void
    {
        $customer = \App\Models\Customer::factory()->recycle($this->owner)->recycle($this->company)->create(['state_id' => $this->mh->id]);
        $real = Invoice::factory()->recycle($this->owner)->recycle($this->company)->recycle($customer)->finalized()->create();

        $known = $this->get("/i/{$real->id}?expires=1999999999&signature=forged")->getStatusCode();
        $unknown = $this->get('/i/987654?expires=1999999999&signature=forged')->getStatusCode();

        $this->assertSame(403, $known);
        $this->assertSame($known, $unknown, 'an unknown invoice id answered differently, so ids could be probed');
    }

    public function test_one_actions_rate_limit_does_not_block_another(): void
    {
        Mail::fake();
        $this->owner->forceFill(['email_verified_at' => now()])->save();
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($this->owner)->get(route('backup.download'));
        }
        $this->actingAs($this->owner)->get(route('backup.download'))->assertStatus(429);

        // Emailing the backup has its own allowance.
        $this->actingAs($this->owner)->post(route('backup.email'))->assertRedirect();
        Mail::assertSent(BackupMail::class);
    }

    public function test_a_late_supplier_invoice_is_booked_in_the_open_month_without_changing_the_locked_one(): void
    {
        $this->company->forceFill(['books_locked_until' => '2026-09-30'])->save();
        $vendor = $this->company->vendors()->create([
            'user_id' => $this->owner->id, 'name' => 'Ajmera Steel', 'state_id' => $this->mh->id, 'gstin' => '27PQRST1234U1Z1',
        ]);

        // A September bill that reached the office on 12 October.
        $this->actingAs($this->owner)->post(route('purchases.store'), [
            'vendor_id' => $vendor->id, 'bill_number' => 'AS/77', 'bill_date' => '2026-09-25', 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Steel sheets', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 10000, 'gst_rate' => 18]],
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $bill = PurchaseBill::where('bill_number', 'AS/77')->firstOrFail();
        $this->assertSame('2026-09-25', $bill->bill_date->toDateString(), 'the supplier invoice keeps its own date');
        $this->assertSame('2026-10-12', $bill->booked_on->toDateString());
        $this->assertSame('2026-10-12', Expense::where('purchase_bill_id', $bill->id)->value('entry_date') ? Carbon::parse(Expense::where('purchase_bill_id', $bill->id)->value('entry_date'))->toDateString() : null);

        // 18% of 10,000 = 900 CGST + 900 SGST, claimed in October, not in the filed September.
        $builder = app(Gstr3bBuilder::class);
        $sep = $builder->build($this->company, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
        $oct = $builder->build($this->company, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));
        $this->assertEqualsWithDelta(0, $sep['itc']['total'], 0.001);
        $this->assertEqualsWithDelta(1800, $oct['itc']['total'], 0.001);

        // It stays editable in October, but cannot be moved to another locked date.
        $this->actingAs($this->owner)->put(route('purchases.update', $bill), [
            'vendor_id' => $vendor->id, 'bill_number' => 'AS/77', 'bill_date' => '2026-09-20', 'itc_eligible' => 1, 'expense_category' => 'office_supplies',
            'items' => [['description' => 'Steel sheets', 'hsn_sac' => '72085100', 'quantity' => 1, 'unit' => 'NOS', 'rate' => 10000, 'gst_rate' => 18]],
        ]);
        $this->assertSame('2026-09-25', $bill->fresh()->bill_date->toDateString());
    }
}
