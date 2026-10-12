<?php

namespace Tests\Feature;

use App\Mail\TeamInviteMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Company settings: saving stays on the form, images can be removed, the
 * books lock saves on its own, and changes reach the activity log. Team:
 * a pending invitation can be sent again, but not in a burst.
 */
class CompanySettingsAndTeamTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $state = State::firstOrCreate(['gst_code' => '27'], State::factory()->raw(['gst_code' => '27']));
        $this->user = User::factory()->create();
        $this->company = Company::factory()->recycle($this->user)->create([
            'name' => 'Sharma Traders', 'state_id' => $state->id, 'onboarded_at' => now(),
            'bank_account_number' => '50100012345678', 'invoice_prefix' => 'INV', 'invoice_number_padding' => 4,
        ]);
        $this->user->forceFill(['active_company_id' => $this->company->id])->save();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => $this->company->name,
            'state_id' => $this->company->state_id,
            'country' => 'India',
            'default_currency' => 'INR',
            'invoice_prefix' => 'INV',
            'invoice_number_padding' => 4,
            'bank_account_number' => $this->company->bank_account_number,
        ], $overrides);
    }

    public function test_saving_returns_to_the_settings_form_with_the_message(): void
    {
        $this->actingAs($this->user)
            ->patch(route('companies.update', $this->company), $this->payload(['name' => 'Sharma Traders LLP']))
            ->assertRedirect(route('companies.edit', $this->company))
            ->assertSessionHas('status');

        $this->actingAs($this->user)->get(route('companies.edit', $this->company))
            ->assertOk()->assertSee('Company settings');
    }

    public function test_company_and_bank_changes_are_written_to_the_activity_log(): void
    {
        $this->actingAs($this->user)->patch(route('companies.update', $this->company), $this->payload([
            'name' => 'Sharma Traders LLP', 'bank_account_number' => '50100099998888',
        ]))->assertRedirect();

        $company = AuditLog::where('action', 'company.updated')->first();
        $this->assertNotNull($company);
        $this->assertStringContainsString('business name', $company->summary);

        $bank = AuditLog::where('action', 'company.bank_updated')->first();
        $this->assertNotNull($bank);
        $this->assertStringContainsString('account number', $bank->summary);
        // Account numbers are never written out in full.
        $this->assertSame(['ending 5678', 'ending 8888'], $bank->changes['bank_account_number']);

        $this->actingAs($this->user)->get(route('profile.activity'))->assertOk()
            ->assertSee('Bank details changed')
            ->assertDontSee('COMPANY · BANK_UPDATED');
    }

    public function test_saving_without_changes_logs_nothing(): void
    {
        $this->actingAs($this->user)->patch(route('companies.update', $this->company), $this->payload())->assertRedirect();

        $this->assertSame(0, AuditLog::whereIn('action', ['company.updated', 'company.bank_updated'])->count());
    }

    public function test_the_logo_can_be_removed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/old.png', 'x');
        $this->company->forceFill(['logo_path' => 'logos/old.png'])->save();

        $this->actingAs($this->user)->patch(route('companies.update', $this->company), $this->payload(['remove_logo' => 1]))
            ->assertRedirect();

        $this->assertNull($this->company->fresh()->logo_path);
        Storage::disk('public')->assertMissing('logos/old.png');
    }

    public function test_a_heic_photo_is_refused_with_one_message(): void
    {
        $file = UploadedFile::fake()->create('IMG_0001.heic', 200, 'image/heic');

        $this->actingAs($this->user)->from(route('companies.edit', $this->company))
            ->patch(route('companies.update', $this->company), $this->payload(['logo' => $file]))
            ->assertSessionHasErrors('logo');

        $html = $this->actingAs($this->user)->get(route('companies.edit', $this->company))->getContent();
        $this->assertStringContainsString('accept=".png,.jpg,.jpeg,.webp"', $html);
    }

    public function test_the_books_lock_saves_on_its_own(): void
    {
        $this->actingAs($this->user)->patch(route('companies.update', $this->company), [
            'section' => 'books_lock', 'books_locked_until' => '2026-03-31',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $company = $this->company->fresh();
        $this->assertSame('2026-03-31', $company->books_locked_until->toDateString());
        $this->assertSame('Sharma Traders', $company->name, 'nothing else on the company changes');
        $this->assertNotNull(AuditLog::where('action', 'company.books_lock_changed')->first());

        // The main form no longer carries the lock, so saving it keeps the date.
        $this->actingAs($this->user)->patch(route('companies.update', $this->company), $this->payload(['name' => 'Renamed']))->assertRedirect();
        $this->assertSame('2026-03-31', $this->company->fresh()->books_locked_until->toDateString());
    }

    public function test_a_pending_invitation_can_be_sent_again_but_not_in_a_burst(): void
    {
        Mail::fake();
        $this->actingAs($this->user)->post(route('team.invite'), ['email' => 'ca@firm.in', 'role' => 'accountant'])
            ->assertSessionHasNoErrors();
        $member = CompanyMember::where('email', 'ca@firm.in')->firstOrFail();
        RateLimiter::clear('team-invite-resend:' . $member->id);

        $this->actingAs($this->user)->post(route('team.invite'), ['resend' => $member->id])
            ->assertSessionHas('status');
        Mail::assertSent(TeamInviteMail::class, 2);

        // A second tap straight away is refused, and nothing is sent.
        $this->actingAs($this->user)->post(route('team.invite'), ['resend' => $member->id])
            ->assertSessionHas('warning');
        Mail::assertSent(TeamInviteMail::class, 2);

        $this->actingAs($this->user)->get(route('team.index'))->assertOk()->assertSee('Resend invite');
    }
}
