<?php

namespace Database\Factories;

use App\Models\TvShow;

/** @extends TitleFactory<TvShow> */
class TvShowFactory extends TitleFactory
{
    protected $model = TvShow::class;

    public function definition(): array
    {
        return array_replace(parent::definition(), ['kind' => 'tv']);
    }
}
