<?php

namespace Database\Factories;

use App\Models\StreamingService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StreamingService>
 */
class StreamingServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 1_000_000),
            'name' => fake()->company(),
            'logo_path' => '/'.fake()->lexify('??????????').'.png',
            'fetched_at' => now(),
        ];
    }
}
