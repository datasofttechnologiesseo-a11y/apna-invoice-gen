<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => \App\Models\Company::factory(),
            'name' => $this->faker->company(),
            'gstin' => null,
            'address_line1' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state_id' => State::factory(),
            'postal_code' => $this->faker->postcode(),
            'country' => 'India',
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
        ];
    }
}
