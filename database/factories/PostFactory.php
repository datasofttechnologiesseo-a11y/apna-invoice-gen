<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(6);
        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . $this->faker->unique()->numberBetween(1000, 99999),
            'excerpt' => $this->faker->sentence(20),
            'body' => "## " . $this->faker->sentence() . "\n\n" . $this->faker->paragraphs(4, true),
            'status' => 'draft',
            'published_at' => null,
            'reading_minutes' => 3,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now()->addDays(2),
        ]);
    }
}
