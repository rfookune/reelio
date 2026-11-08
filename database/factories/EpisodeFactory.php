<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Episode>
 */
class EpisodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'title_id' => fn (array $attributes): int => Season::query()->whereKey($attributes['season_id'])->firstOrFail()->title_id,
            'tmdb_id' => fake()->unique()->numberBetween(1, 2_000_000_000),
            'season_number' => fn (array $attributes): ?int => Season::query()->whereKey($attributes['season_id'])->firstOrFail()->season_number,
            'episode_number' => 1,
            'name' => fake()->sentence(3),
            'overview' => fake()->paragraph(),
            'air_date' => '2025-01-01',
            'runtime' => 45,
            'episode_type' => 'standard',
        ];
    }
}
