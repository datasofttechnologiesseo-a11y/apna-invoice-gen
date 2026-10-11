<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A ready-to-use login for checking the app locally - verified, onboarded,
 * with one company - so you can sign in with email + password and skip OTP,
 * Google and onboarding.
 *
 *   php artisan db:seed --class=LocalTestUserSeeder
 *
 * Refuses to run outside APP_ENV=local, so these credentials can never exist
 * on a real server. Safe to re-run: it updates the same user in place.
 */
class LocalTestUserSeeder extends Seeder
{
    public const EMAIL = 'test@apnainvoice.test';
    public const PASSWORD = 'LocalTest@123';

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('LocalTestUserSeeder only runs when APP_ENV=local.');

            return;
        }

        $user = User::updateOrCreate(['email' => self::EMAIL], [
            'name' => 'Local Tester',
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
        ]);

        $company = Company::firstOrCreate(['user_id' => $user->id], [
            'name' => 'Local Test Traders',
            'address_line1' => '12 MG Road',
            'city' => 'Mumbai',
            'state_id' => State::where('gst_code', '27')->value('id') ?? State::query()->value('id'),
            'postal_code' => '400001',
            'country' => 'India',
            'gstin' => '27ABCDE1234F1Z5',
            'default_currency' => 'INR',
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
            'invoice_number_padding' => 4,
        ]);
        $company->forceFill(['onboarded_at' => $company->onboarded_at ?? now()])->save();

        $user->forceFill(['active_company_id' => $company->id])->save();

        $this->command?->info('Local test login ready: ' . self::EMAIL . ' (password in ' . self::class . ')');
    }
}
