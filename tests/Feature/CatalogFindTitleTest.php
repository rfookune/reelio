<?php

use App\Models\Movie;
use App\Models\TvShow;
use App\Services\Catalog\Catalog;
use Illuminate\Database\Eloquent\ModelNotFoundException;

test('resolves a stored Movie as its subtype with its detail casts', function () {
    $movie = Movie::factory()->create(['release_date' => '2020-01-02', 'runtime' => 120]);

    $title = app(Catalog::class)->findTitle((string) $movie->id);

    expect($title)->toBeInstanceOf(Movie::class);
    expect($title->is($movie))->toBeTrue();
    expect($title->release_date->toDateString())->toBe('2020-01-02');
    expect($title->runtime)->toBe(120);
    expect($title->exists)->toBeTrue();
});

test('resolves a stored TV Show as its subtype', function () {
    $tvShow = TvShow::factory()->create();

    $title = app(Catalog::class)->findTitle($tvShow->id);

    expect($title)->toBeInstanceOf(TvShow::class);
    expect($title->is($tvShow))->toBeTrue();
    expect($title->exists)->toBeTrue();
});

test('rejects invalid or oversized local ids as missing titles', function (int|string $id) {
    expect(fn () => app(Catalog::class)->findTitle($id))->toThrow(ModelNotFoundException::class);
})->with([0, -1, '', 'nope', '1.5', '999999999999999999999999999']);

test('reports a missing title with the usual not-found exception', function () {
    expect(fn () => app(Catalog::class)->findTitle(999999))->toThrow(ModelNotFoundException::class);
});

test('finds both Movies and TV Shows by default', function () {
    $movie = Movie::factory()->create();
    $tvShow = TvShow::factory()->create();

    expect(app(Catalog::class)->findTitle($movie->id))->toBeInstanceOf(Movie::class);
    expect(app(Catalog::class)->findTitle($tvShow->id))->toBeInstanceOf(TvShow::class);
});
