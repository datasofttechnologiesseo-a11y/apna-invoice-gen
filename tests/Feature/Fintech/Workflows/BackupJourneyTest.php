<?php

namespace Tests\Feature\Fintech\Workflows;

use App\Mail\BackupMail;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * A backup is the owner's whole book of accounts in one file. It must hold
 * every business the owner runs, nothing of anybody else's, reach only the
 * owner's own inbox, and never be handed to a team member.
 *
 * Owner: Sharma Traders (Maharashtra) plus a second business, Sharma Exports.
 * Another user runs Rival Traders; none of it may appear in Sharma's backup.
 */
class BackupJourneyTest extends TestCase
{
    use RefreshDatabase, WorkflowFixtures;

    private Company $biz;
    private Company $second;
    private User $owner;
    private Company $rival;
    private User $rivalOwner;

    /** Temp files to clean up. */
    private array $tmp = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootWorkflow();

        $this->biz = $this->makeBusiness([], ['name' => 'Arbaz Sharma', 'email' => 'arbaz@sharmatraders.example']);
        $this->owner = $this->ownerOf($this->biz);
        $this->second = Company::factory()->create([
            'user_id' => $this->owner->id, 'name' => 'Sharma Exports', 'state_id' => $this->state('27')->id,
        ]);

        // Sharma Traders' books: INV-0001 1 x 1,000 @ 18% = 1,180; 500 received
        // (RCPT-0001); credit note 118 (CRN-0001); one expense.
        $patil = $this->customerOf($this->biz);
        $inv = $this->webIssued($this->owner, $patil);
        $this->webPay($this->owner, $inv, 500, ['reference_number' => 'UTR-SHARMA-1'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('credit-notes.store', $inv), [
            'credit_note_date' => now()->toDateString(), 'amount' => 118, 'reason' => 'post_sale_discount',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(), 'category' => 'rent', 'description' => 'October shop rent',
            'amount' => 10000, 'gst_amount' => 1800, 'itc_eligible' => 1,
        ])->assertSessionHasNoErrors();

        // Someone else's books.
        $this->rival = $this->makeBusiness([
            'name' => 'Rival Traders', 'gstin' => '29ABCDE1234F1ZW', 'state_id' => $this->state('29')->id,
        ], ['email' => 'owner@rivaltraders.example']);
        $this->rivalOwner = $this->ownerOf($this->rival);
        $rivalCustomer = $this->customerOf($this->rival, '29', ['name' => 'Rival Customer', 'email' => 'buyer@rival.example']);
        $this->webIssued($this->rivalOwner, $rivalCustomer, [$this->line(7777, 18, 1)]);
        $this->actingAs($this->rivalOwner)->post(route('finance.expenses.store'), [
            'entry_date' => now()->toDateString(), 'category' => 'rent', 'description' => 'Rival secret rent', 'amount' => 55555,
        ])->assertSessionHasNoErrors();
    }

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    // ---- helpers ----------------------------------------------------------

    /** Keep a copy of every zip the real BackupService builds (the controllers delete theirs). */
    private function keepZips(): object
    {
        $spy = new class extends BackupService {
            public array $copies = [];

            public function buildZipForUser(User $user): string
            {
                $path = parent::buildZipForUser($user);
                $copy = $path . '.kept.zip';
                copy($path, $copy);
                $this->copies[] = $copy;

                return $path;
            }
        };
        $this->app->instance(BackupService::class, $spy);

        return $spy;
    }

    /** @return array<string, string> entry name => contents */
    private function unzip(string $path): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'the backup is a readable ZIP');
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $files[$name] = (string) $zip->getFromIndex($i);
        }
        $zip->close();

        return $files;
    }

    private function downloadedZip(TestResponse $res): array
    {
        $res->assertOk();
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $res->baseResponse);
        $this->assertStringContainsString('apna-invoice-backup-2026-10-12.zip', (string) $res->headers->get('content-disposition'));
        $this->assertSame('application/zip', $res->headers->get('content-type'));
        $path = $res->baseResponse->getFile()->getPathname();
        $this->tmp[] = $path;

        return $this->unzip($path);
    }

    private function assertIsSharmasBackup(array $files): void
    {
        $folder = Str::slug('Sharma Traders') . '-' . $this->biz->id;
        $secondFolder = Str::slug('Sharma Exports') . '-' . $this->second->id;

        $this->assertArrayHasKey('README.txt', $files);
        $this->assertStringContainsString('Account: Arbaz Sharma <arbaz@sharmatraders.example>', $files['README.txt']);
        foreach (['company.csv', 'customers.csv', 'invoices.csv', 'invoice_items.csv', 'payments.csv',
            'credit_notes.csv', 'expenses.csv', 'stock_movements.csv'] as $csv) {
            $this->assertArrayHasKey("{$folder}/{$csv}", $files, "{$csv} is in the backup");
        }
        $this->assertArrayHasKey("{$secondFolder}/company.csv", $files, 'every business the owner runs is backed up');

        $invoices = $files["{$folder}/invoices.csv"];
        $this->assertStringContainsString('INV-0001', $invoices);
        $this->assertStringContainsString('Patil Stores', $invoices);
        $this->assertStringContainsString('1180.00', $invoices, 'grand total 1,180');
        // 1,180 - 500 paid - 118 credited = 562 still owed.
        $this->assertStringContainsString('562.00', $invoices);
        $this->assertStringContainsString('UTR-SHARMA-1', $files["{$folder}/payments.csv"]);
        $this->assertStringContainsString('CRN-0001', $files["{$folder}/credit_notes.csv"]);
        $this->assertStringContainsString('October shop rent', $files["{$folder}/expenses.csv"]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $invoices, 'UTF-8 BOM so Excel shows the rupee sign');

        // Nothing of the other user's business, anywhere in the file.
        foreach ($files as $name => $contents) {
            $this->assertStringNotContainsString('Rival', $name . $contents, "{$name} must not hold another user's data");
            $this->assertStringNotContainsString('7777.00', $contents);   // the rival's line, not a random postcode
            $this->assertStringNotContainsString('55555.00', $contents);
        }
        $this->assertEmpty(array_filter(array_keys($files), fn ($n) => str_ends_with(strtok($n, '/'), '-' . $this->rival->id)),
            'no folder for the other business');
    }

    private function memberOf(Company $company, string $role): User
    {
        $member = User::factory()->create(['name' => 'Member ' . $role]);
        CompanyMember::create([
            'company_id' => $company->id, 'user_id' => $member->id, 'email' => $member->email,
            'role' => $role, 'token' => CompanyMember::newToken(), 'invited_by' => $company->user_id,
            'accepted_at' => now(),
        ]);
        $member->forceFill(['active_company_id' => $company->id])->save();

        return $member->fresh();
    }

    // ---- download ---------------------------------------------------------

    public function test_the_downloaded_zip_holds_this_owners_books_and_nobody_elses(): void
    {
        // Weekly backup is on for every new account (users.auto_backup_enabled defaults to true).
        $this->actingAs($this->owner)->get(route('backup.index'))->assertOk()
            ->assertSee('Enabled')->assertSee('arbaz@sharmatraders.example');

        $this->assertIsSharmasBackup($this->downloadedZip($this->actingAs($this->owner)->get(route('backup.download'))));
        $this->assertIsSharmasBackup($this->downloadedZip($this->apiAs($this->owner)->get('/api/backup/download')));

        // The other owner's backup is theirs alone.
        $rivalFiles = $this->downloadedZip($this->actingAs($this->rivalOwner)->get(route('backup.download')));
        $joined = implode("\n", array_keys($rivalFiles)) . implode("\n", $rivalFiles);
        $this->assertStringContainsString('Rival Customer', $joined);
        $this->assertStringNotContainsString('Patil Stores', $joined);
        $this->assertStringNotContainsString('Sharma', $joined);

        // Only a signed-in owner gets a file.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/backup/download')->assertUnauthorized();
    }

    // ---- email ------------------------------------------------------------

    public function test_emailing_the_backup_sends_the_zip_to_the_owner_only(): void
    {
        Mail::fake();
        $spy = $this->keepZips();

        $this->actingAs($this->owner)->from(route('backup.index'))->post(route('backup.email'))
            ->assertRedirect(route('backup.index'))
            ->assertSessionHas('status', 'Backup emailed to arbaz@sharmatraders.example.');

        Mail::assertSent(BackupMail::class, 1);
        Mail::assertSent(BackupMail::class, function (BackupMail $mail) {
            $attachments = $mail->attachments();

            return $mail->hasTo('arbaz@sharmatraders.example')
                && ! $mail->hasTo('owner@rivaltraders.example')
                && $mail->user->is($this->owner)
                && count($attachments) === 1
                && $attachments[0]->as === 'apna-invoice-backup-2026-10-12.zip'
                && $attachments[0]->mime === 'application/zip';
        });
        $this->assertSame('2026-10-12 11:00', $this->owner->fresh()->last_backup_sent_at->timezone('Asia/Kolkata')->format('Y-m-d H:i'));

        // The attached file is the owner's own books, and the temp file is gone.
        $this->assertCount(1, $spy->copies);
        $this->tmp[] = $spy->copies[0];
        $this->assertIsSharmasBackup($this->unzip($spy->copies[0]));
        Mail::assertSent(BackupMail::class, fn (BackupMail $m) => ! file_exists($m->zipPath));

        // Same from the phone.
        $this->apiAs($this->owner)->postJson('/api/backup/email')->assertOk()
            ->assertJsonPath('message', 'Backup emailed to arbaz@sharmatraders.example.');
        Mail::assertSent(BackupMail::class, 2);
        $this->tmp[] = $spy->copies[1];
        $this->assertIsSharmasBackup($this->unzip($spy->copies[1]));
        $this->apiAs($this->owner)->getJson('/api/backup')->assertOk()
            ->assertJsonPath('last_backup_sent_at', now()->toIso8601String());
    }

    // ---- weekly toggle ----------------------------------------------------

    public function test_weekly_backup_can_be_turned_on_and_off_on_both_doors(): void
    {
        // New accounts start with the weekly backup on.
        $this->assertTrue((bool) $this->owner->fresh()->auto_backup_enabled);

        $this->actingAs($this->owner)->from(route('backup.index'))->post(route('backup.toggle'), ['auto_backup_enabled' => '0'])
            ->assertRedirect(route('backup.index'))
            ->assertSessionHas('status', 'Auto-backup turned off.');
        $this->assertFalse((bool) $this->owner->fresh()->auto_backup_enabled);
        $this->actingAs($this->owner)->get(route('backup.index'))->assertOk()->assertSee('Disabled');

        $this->actingAs($this->owner)->from(route('backup.index'))->post(route('backup.toggle'), ['auto_backup_enabled' => '1'])
            ->assertSessionHas('status', 'Auto-backup turned on. A ZIP backup will be emailed to you every week.');
        $this->assertTrue((bool) $this->owner->fresh()->auto_backup_enabled);
        $this->actingAs($this->owner)->get(route('backup.index'))->assertOk()->assertSee('Enabled');

        // Missing or nonsense values change nothing.
        $this->actingAs($this->owner)->from(route('backup.index'))->post(route('backup.toggle'), [])
            ->assertSessionHasErrors('auto_backup_enabled');
        $this->actingAs($this->owner)->from(route('backup.index'))->post(route('backup.toggle'), ['auto_backup_enabled' => 'sometimes'])
            ->assertSessionHasErrors('auto_backup_enabled');
        $this->assertTrue((bool) $this->owner->fresh()->auto_backup_enabled);

        // The phone sees and flips the same setting.
        $this->apiAs($this->owner)->getJson('/api/backup')->assertOk()
            ->assertJsonPath('auto_backup_enabled', true)
            ->assertJsonPath('last_backup_sent_at', null);
        $this->apiAs($this->owner)->postJson('/api/backup/toggle', ['auto_backup_enabled' => false])->assertOk()
            ->assertJsonPath('auto_backup_enabled', false)
            ->assertJsonPath('message', 'Auto-backup turned off.');
        $this->assertFalse((bool) $this->owner->fresh()->auto_backup_enabled);
        $this->actingAs($this->owner->fresh())->get(route('backup.index'))->assertOk()->assertSee('Disabled');
        $this->apiAs($this->owner)->postJson('/api/backup/toggle', [])->assertStatus(422)->assertJsonValidationErrors('auto_backup_enabled');
        $this->assertFalse((bool) $this->owner->fresh()->auto_backup_enabled);

        // One owner's switch is never anyone else's.
        $this->assertTrue((bool) $this->rivalOwner->fresh()->auto_backup_enabled);
    }

    // ---- the weekly command -----------------------------------------------

    public function test_the_weekly_command_mails_only_owners_who_opted_in_with_a_verified_email(): void
    {
        Mail::fake();
        $spy = $this->keepZips();
        // The owner keeps the default (on, verified email). The rival owner
        // turned it off. A third account is on but never verified its email.
        $this->assertTrue((bool) $this->owner->fresh()->auto_backup_enabled);
        $this->rivalOwner->forceFill(['auto_backup_enabled' => false])->save();
        $unverified = User::factory()->unverified()->create(['email' => 'new@unverified.example']);
        $unverified->forceFill(['auto_backup_enabled' => true])->save();           // opted in, email never verified

        $this->artisan('backups:send-weekly', ['--dry' => true])->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertNull($this->owner->fresh()->last_backup_sent_at, 'a dry run sends and stamps nothing');

        $this->artisan('backups:send-weekly')->assertSuccessful()->expectsOutputToContain('Sent: 1, Failed: 0');
        Mail::assertSent(BackupMail::class, 1);
        Mail::assertSent(BackupMail::class, fn (BackupMail $m) => $m->hasTo('arbaz@sharmatraders.example'));
        Mail::assertNotSent(BackupMail::class, fn (BackupMail $m) => $m->hasTo('owner@rivaltraders.example') || $m->hasTo('new@unverified.example'));

        $this->assertNotNull($this->owner->fresh()->last_backup_sent_at);
        $this->assertSame(now()->toDateString(), $this->owner->fresh()->last_backup_sent_at->toDateString());
        $this->assertNull($this->rivalOwner->fresh()->last_backup_sent_at);
        $this->assertNull($unverified->fresh()->last_backup_sent_at);
        $this->tmp[] = $spy->copies[0];
        $this->assertIsSharmasBackup($this->unzip($spy->copies[0]));

        // --user limits the run; a user who is not opted in is still skipped.
        $this->artisan('backups:send-weekly', ['--user' => [$this->rivalOwner->id]])->assertSuccessful()
            ->expectsOutputToContain('Sent: 0, Failed: 0');
        Mail::assertSent(BackupMail::class, 1);

        // Once a week: a second run the same day (a retried cron, two servers)
        // mails nothing and leaves the stamp alone. Naming the user with
        // --user still sends on demand.
        $this->travel(2)->hours();
        $this->artisan('backups:send-weekly')->assertSuccessful()->expectsOutputToContain('Sent: 0, Failed: 0');
        Mail::assertSent(BackupMail::class, 1);
        $this->assertSame('11:00', $this->owner->fresh()->last_backup_sent_at->timezone('Asia/Kolkata')->format('H:i'));
        $this->artisan('backups:send-weekly', ['--user' => [$this->owner->id]])->assertSuccessful();
        Mail::assertSent(BackupMail::class, 2);
        foreach ($spy->copies as $c) {
            $this->tmp[] = $c;
        }
    }

    // ---- team members -----------------------------------------------------

    public function test_team_members_never_get_the_owners_backup(): void
    {
        Mail::fake();
        // Download and email share one per-user rate limit; it would answer
        // 429 before the role check gets its say. The role check is under test.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->owner->forceFill(['auto_backup_enabled' => false])->save();

        foreach (['admin' => 'dashboard', 'accountant' => 'dashboard', 'staff' => 'invoices.index'] as $role => $landing) {
            $member = $this->memberOf($this->biz, $role);

            // Web: the backup page and the download bounce back with a message...
            $this->actingAs($member)->get(route('backup.index'))->assertRedirect(route($landing));
            $download = $this->actingAs($member)->get(route('backup.download'));
            $download->assertRedirect(route($landing));
            $this->assertNotInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $download->baseResponse);
            // ...and the email and switch are refused outright.
            $this->actingAs($member)->post(route('backup.email'))->assertForbidden();
            $this->actingAs($member)->post(route('backup.toggle'), ['auto_backup_enabled' => '1'])->assertForbidden();

            // API: every backup route is refused.
            $this->apiAs($member)->getJson('/api/backup')->assertForbidden();
            $this->apiAs($member)->getJson('/api/backup/download')->assertForbidden();
            $this->apiAs($member)->postJson('/api/backup/email')->assertForbidden();
            $this->apiAs($member)->postJson('/api/backup/toggle', ['auto_backup_enabled' => true])->assertForbidden();
        }

        Mail::assertNothingSent();
        $this->assertFalse((bool) $this->owner->fresh()->auto_backup_enabled, "a member cannot switch the owner's weekly backup");
        $this->assertNull($this->owner->fresh()->last_backup_sent_at);
    }
}
