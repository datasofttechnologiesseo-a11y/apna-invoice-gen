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

        // gst_code is two digits and unique, so the whole space is 01-99 and a
        // plain counter eventually wraps back onto a code a test picked by
        // hand - several pin '27' for Maharashtra. Which iteration that lands
        // on depends on how many states every earlier test made, so adding a
        // test file anywhere in the suite could turn this red somewhere else.
        // Skipping taken codes makes the factory independent of that.
        $gstCode = null;
        for ($attempt = 0; $attempt < 99; $attempt++) {
            $seq++;
            $candidate = str_pad((string) (($seq % 99) + 1), 2, '0', STR_PAD_LEFT);
            if (! State::where('gst_code', $candidate)->exists()) {
                $gstCode = $candidate;
                break;
            }
        }

        return [
            'code' => 'S' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT),
            'name' => $this->faker->unique()->state(),
            'gst_code' => $gstCode ?? str_pad((string) (($seq % 99) + 1), 2, '0', STR_PAD_LEFT),
            'is_union_territory' => false,
        ];
    }
}
