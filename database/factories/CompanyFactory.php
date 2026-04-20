<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->company(),
            'address_line1' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state_id' => State::factory(),
            'postal_code' => $this->faker->postcode(),
            'country' => 'India',
            'default_currency' => 'INR',
            'invoice_prefix' => 'INV',
            'invoice_counter' => 0,
            'invoice_number_padding' => 4,
        ];
    }
}
