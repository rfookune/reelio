<?php

namespace Database\Factories;

use App\Models\Movie;

/** @extends TitleFactory<Movie> */
class MovieFactory extends TitleFactory
{
    protected $model = Movie::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return array_replace(parent::definition(), ['release_date' => fake()->date()]);
    }

    public function capCleared(): static
    {
        return parent::capCleared()->state(fn (): array => ['release_date' => null]);
    }

    public function removed(): static
    {
        return parent::removed()->state(fn (): array => ['release_date' => null]);
    }
}
