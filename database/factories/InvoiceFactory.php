<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'is_interstate' => false,
            'reverse_charge' => false,
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
            'paid_amount' => 0,
            'balance' => 1180,
        ];
    }

    public function finalized(): static
    {
        return $this->state(fn () => [
            'status' => 'final',
            'invoice_number' => 'INV-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'finalized_at' => now(),
        ]);
    }
}
