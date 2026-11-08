<?php

use App\Models\TvShow;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\DB;

test('the TV Show page displays IMDb then TMDB ratings from its catalog identity and obeys the source switch', function () {
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(
        87108, title: 'Chernobyl', imdbId: 'tt7366338', voteAverage: 8.5, voteCount: 100,
    )]));
    DB::table('ratings')->insert(['source' => 'imdb', 'source_id' => 'tt7366338', 'value' => 9.3, 'vote_count' => 900000, 'as_of' => now()]);
    config(['ratings.sources.imdb.enabled' => true]);
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();

    $this->get($tvShow->canonicalUrl())->assertInertia(fn ($page) => $page->where('tvShow.ratings', [
        ['source' => 'imdb', 'name' => 'IMDb', 'value' => 9.3, 'voteCount' => 900000],
        ['source' => 'tmdb', 'name' => 'TMDB', 'value' => 8.5, 'voteCount' => 100],
    ]));

    config(['ratings.sources.imdb.enabled' => false]);
    $this->get($tvShow->canonicalUrl())->assertInertia(fn ($page) => $page
        ->has('tvShow.ratings', 1)->where('tvShow.ratings.0.source', 'tmdb'));
});

test('the TV Show page sends no ratings when neither source has a score', function () {
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(87108, title: 'Chernobyl')]));
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);

    $this->get(TvShow::query()->sole()->canonicalUrl())->assertInertia(fn ($page) => $page->where('tvShow.ratings', []));
});
