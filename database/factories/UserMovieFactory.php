<?php

namespace Database\Factories;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserMovie>
 */
class UserMovieFactory extends Factory
{
    /**
     * Define the model's default state: on the user's Watchlist, not Watched.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title_id' => Movie::factory(),
            'watchlisted_at' => now(),
            'watched_at' => null,
        ];
    }

    /** Watched and not on the Watchlist. */
    public function watched(): static
    {
        return $this->state(fn (): array => ['watchlisted_at' => null, 'watched_at' => now()]);
    }
}
