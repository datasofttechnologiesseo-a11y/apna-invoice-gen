<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Quotation> */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'is_interstate' => false,
            'currency' => 'INR',
            'exchange_rate' => 1,
            'status' => 'draft',
            'subtotal' => 1000,
            'total_cgst' => 90,
            'total_sgst' => 90,
            'total_igst' => 0,
            'total_tax' => 180,
            'round_off' => 0,
            'grand_total' => 1180,
            'style' => 'classic',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => 'sent',
            'quote_number' => 'QT-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'sent_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => 'accepted',
            'quote_number' => 'QT-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'sent_at' => now()->subDay(),
            'accepted_at' => now(),
        ]);
    }
}
