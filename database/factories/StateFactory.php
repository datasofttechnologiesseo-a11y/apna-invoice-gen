<?php

namespace Database\Factories;

use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<State> */
class StateFactory extends Factory
{
    protected $model = State::class;

    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        return [
            'code' => 'S' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT),
            'name' => $this->faker->unique()->state(),
            'gst_code' => str_pad((string) (($seq % 99) + 1), 2, '0', STR_PAD_LEFT),
            'is_union_territory' => false,
        ];
    }
}
