<?php

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Movie>
 */
class MovieFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'tmdb_id' => fake()->unique()->numberBetween(1, 1000000000),
            'slug' => Str::slug($title),
            'title' => $title,
            'overview' => fake()->paragraph(),
            'release_date' => fake()->date(),
            'synced_at' => now(),
            'last_viewed_at' => now(),
        ];
    }
}
