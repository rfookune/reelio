<?php

use App\Jobs\RefreshMovie;
use App\Models\Movie;
use App\Services\Catalog\Enums\CatalogProfile;

test('a Movie\'s scheduled refresh updates it from one background-profile call', function () {
    $this->travelTo('2026-11-06 05:00:00');
    $movie = Movie::factory()->create(['title' => 'Old Title', 'synced_at' => '2026-11-01 05:00:00']);
    $source = fakeCatalogKnowing(sourceDetailsFor($movie, title: 'New Title'));

    RefreshMovie::dispatch($movie->id);

    expect($movie->refresh())->title->toBe('New Title')->synced_at->toDateTimeString()->toBe('2026-11-06 05:00:00')
        ->and($source->movieCalls)->toHaveCount(1)
        ->and($source->movieCalls[0]['context']->profile)->toBe(CatalogProfile::Background);
});

test('a Movie deleted since it was planned is skipped without asking the source', function () {
    $source = fakeCatalogKnowing();

    RefreshMovie::dispatch(999);

    expect($source->movieCalls)->toBe([]);
});
