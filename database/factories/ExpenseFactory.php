<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'entry_date' => now()->subDays(random_int(0, 60))->toDateString(),
            'category' => $this->faker->randomElement(array_keys(config('expense_categories'))),
            'vendor_name' => $this->faker->company(),
            'description' => $this->faker->sentence(4),
            'amount' => $this->faker->randomFloat(2, 100, 50000),
            'gst_amount' => 0,
            'payment_method' => $this->faker->randomElement(['bank', 'upi', 'card', 'cash']),
        ];
    }
}
