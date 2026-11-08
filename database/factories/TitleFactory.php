<?php

namespace Database\Factories;

use App\Models\Title;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @template TTitle of Title = Title
 *
 * @extends Factory<TTitle>
 */
class TitleFactory extends Factory
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
            'kind' => 'movie',
            'tmdb_id' => fake()->unique()->numberBetween(1, 1000000000),
            'slug' => Str::slug($title),
            'title' => $title,
            'overview' => fake()->paragraph(),
            'synced_at' => now(),
            'last_viewed_at' => now(),
        ];
    }

    public function movie(): static
    {
        return $this->state(fn (): array => ['kind' => 'movie']);
    }

    public function tvShow(): static
    {
        return $this->state(fn (): array => ['kind' => 'tv']);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Title $title): void {
            $details = $title->kind === 'movie' ? $title->movieDetails() : $title->tvShowDetails();
            $details->firstOrCreate();
        });
    }

    /**
     * A Title cleared at TMDB's 6-month cap: every TMDB-derived field is cleared, but it isn't removed, so its next
     * successful refresh restores it.
     */
    public function capCleared(): static
    {
        return $this->state(fn (): array => [
            'slug' => null,
            'title' => null,
            'overview' => null,
            'synced_at' => null,
        ]);
    }

    /** A Removed Title: the Catalog source dropped it, so every TMDB-derived field is cleared. */
    public function removed(): static
    {
        return $this->state(fn (): array => [
            'slug' => null,
            'title' => null,
            'overview' => null,
            'synced_at' => null,
            'removed_at' => now(),
        ]);
    }
}
