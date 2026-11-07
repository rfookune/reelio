<?php

use App\Jobs\RefreshGenres;
use App\Models\Genre;
use App\Models\Movie;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;

test('the genre refresh renames and adds genres from the source, keeping every Movie\'s genres', function () {
    $movie = Movie::factory()->create();
    $movie->genres()->attach(Genre::factory()->create(['id' => 878, 'name' => 'Sci-Fi']));
    $source = new FakeCatalogSource(genres: [['id' => 28, 'name' => 'Action'], ['id' => 878, 'name' => 'Science Fiction']]);
    app()->instance(CatalogSource::class, $source);

    RefreshGenres::dispatch();

    expect(Genre::query()->orderBy('id')->pluck('name', 'id')->all())->toBe([28 => 'Action', 878 => 'Science Fiction'])
        ->and($movie->genres()->pluck('id')->all())->toBe([878])
        ->and($source->genreCalls[0]->profile)->toBe(CatalogProfile::Background);
});

test('a failed genre refresh leaves the stored genres as they are', function () {
    Genre::factory()->create(['id' => 878, 'name' => 'Science Fiction']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    RefreshGenres::dispatch();

    expect(Genre::query()->pluck('name', 'id')->all())->toBe([878 => 'Science Fiction']);
});
