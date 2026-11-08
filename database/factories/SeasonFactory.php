<?php

namespace Database\Factories;

use App\Models\Season;
use App\Models\TvShow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title_id' => TvShow::factory(),
            'tmdb_id' => fake()->unique()->numberBetween(1, 2_000_000_000),
            'season_number' => 1,
            'name' => 'Season 1',
            'overview' => fake()->paragraph(),
            'air_date' => '2025-01-01',
            'episode_count' => 10,
        ];
    }
}
