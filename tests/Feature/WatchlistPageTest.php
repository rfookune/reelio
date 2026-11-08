<?php

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('guests sign in before returning to their Watchlist', function () {
    $this->get('/watchlist?sort=title')->assertRedirect(route('login'));

    $this->assertGuest();
    expect(session('url.intended'))->toBe(url('/watchlist?sort=title'));
});

test('each sort uses the full key, ignores title case and accents, and puts undated Movies last', function (string $sort, array $expected) {
    $user = User::factory()->create();
    foreach ([
        ['zebra', '2026-03-02', '2026-01-03'],
        ['Éclair', null, '2026-01-01'],
        ['eclair', '2026-03-01', '2026-01-01'],
        ['Apple', '2025-01-01', '2026-01-02'],
    ] as $index => [$title, $releaseDate, $dateAdded]) {
        UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $index + 1, 'title' => $title, 'release_date' => $releaseDate]))->create(['watchlisted_at' => $dateAdded]);
    }

    $this->actingAs($user)->get(route('watchlist', ['sort' => $sort]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sort', $sort)
        ->where('movies.data', fn ($cards) => collect($cards)->pluck('tmdbId')->all() === $expected));
})->with([
    'recently added' => ['recently-added', [1, 4, 3, 2]],
    'oldest added' => ['oldest-added', [2, 3, 4, 1]],
    'title' => ['title', [4, 2, 3, 1]],
    'newest release' => ['newest-release', [1, 3, 4, 2]],
    'oldest release' => ['oldest-release', [4, 3, 1, 2]],
]);

test('unknown or non-text sorts are rejected with 422', function (mixed $sort) {
    $this->actingAs(User::factory()->create())->get(route('watchlist', ['sort' => $sort]))->assertUnprocessable();
})->with(['unknown' => ['rating'], 'array' => [['title']]]);

test('cursor pages never skip Movies after earlier cards and the boundary card are removed', function (string $sort) {
    $user = User::factory()->create();
    foreach (Movie::factory()->count(51)->create(['title' => 'Éclair', 'release_date' => null]) as $movie) {
        UserMovie::factory()->for($user)->for($movie)->create(['watchlisted_at' => '2026-01-01']);
    }
    $first = $this->actingAs($user)->get(route('watchlist', ['sort' => $sort]))->assertOk();
    $cards = collect($first->viewData('page')['props']['movies']['data']);
    $nextUrl = $first->viewData('page')['props']['movies']['next_page_url'];
    expect($cards)->toHaveCount(48);
    expect($nextUrl)->toContain('cursor=');

    foreach ([$cards->first(), $cards->last()] as $card) {
        $this->delete(route('movies.watchlist.destroy', ['tmdbId' => $card['tmdbId']]), ['source' => 'watchlist'])->assertRedirect();
    }

    $next = $this->get($nextUrl)->assertOk();
    $remaining = collect($next->viewData('page')['props']['movies']['data']);
    expect($remaining)->toHaveCount(3);
    expect($cards->pluck('tmdbId')->intersect($remaining->pluck('tmdbId')))->toBeEmpty();
    expect($next->viewData('page')['props']['movies']['next_cursor'])->toBeNull();
    expect($first->viewData('page')['scrollProps']['movies']['pageName'])->toBe('cursor');
    expect($first->viewData('page')['mergeProps'])->toContain('movies.data');
    if ($sort === 'recently-added') {
        expect($nextUrl)->not->toContain('sort=');
    }
})->with(['recently-added', 'oldest-added', 'title', 'newest-release', 'oldest-release']);

test('an invalid restored Movie reference is rejected with 422', function (mixed $restore) {
    $this->actingAs(User::factory()->create())->get(route('watchlist', ['restore' => $restore]))->assertUnprocessable();
})->with(['non-number' => ['movie'], 'zero' => [0], 'array' => [[1]]]);

test('cards use one batched ratings lookup and prefer IMDb while keeping poster-less and unrated Movies', function () {
    config(['ratings.sources.imdb.enabled' => true]);
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['imdb_id' => 'tt0000001', 'poster_path' => null]);
    UserMovie::factory()->for($user)->for($movie)->create();
    UserMovie::factory()->for($user)->create();
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000001', 8.2, 1000);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, 'movie:'.$movie->tmdb_id, 7.5, 100);
    $ratingQueries = [];
    DB::listen(function ($query) use (&$ratingQueries): void {
        if (str_contains($query->sql, 'from "ratings"')) {
            $ratingQueries[] = $query->sql;
        }
    });

    $this->actingAs($user)->get('/watchlist')->assertInertia(fn (Assert $page) => $page
        ->where('movies.data.0.ratings', [])
        ->where('movies.data.1.posterPath', null)
        ->where('movies.data.1.ratings.0.source', 'imdb'));

    expect($ratingQueries)->toHaveCount(2);
});

test('an empty Watchlist suggests cached Trending without making Catalog requests', function (bool $hasTrending) {
    $source = fakeDiscoverSource(['trending' => [movieListings(901, 906)]]);
    if ($hasTrending) {
        app(DiscoverBuilder::class)->build(CatalogProfile::Background);
    }
    $calls = $source->sectionCalls;

    $this->actingAs(User::factory()->create())->get('/watchlist')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('userMovies', [])
        ->where('movies.data', [])
        ->has('trending', $hasTrending ? 6 : 0));

    expect($source->sectionCalls)->toBe($calls);
})->with([true, false]);

test('the Watchlist shows only the signed-in users saved Movies, newest first, without fetching the Catalog', function () {
    $user = User::factory()->unverified()->create();
    $old = UserMovie::factory()->for($user)->create(['watchlisted_at' => '2026-01-01']);
    $recent = UserMovie::factory()->for($user)->create(['watchlisted_at' => '2026-02-01', 'watched_at' => '2025-12-01']);
    UserMovie::factory()->create();
    UserMovie::factory()->for($user)->watched()->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['title' => null]))->create();

    $this->actingAs($user)->get('/watchlist')->assertOk()->assertHeader('X-Robots-Tag', 'noindex')
        ->assertInertia(fn (Assert $page) => $page->component('Watchlist')
            ->where('sort', 'recently-added')
            ->where('userMovies', fn ($facts) => collect($facts)->where('watchlisted', true)->count() === 3)
            ->where('movies.data', fn ($cards) => collect($cards)->pluck('tmdbId')->all() === [$recent->movie->tmdb_id, $old->movie->tmdb_id])
            ->where('movies.data.0.href', $recent->movie->canonicalUrl('watchlist'))
            ->where('userMovies.'.$recent->movie->tmdb_id.'.watchedDate', '2025-12-01T00:00:00.000000Z'));
});

test('a restored Movie can be opened at its cursor in every sort without revealing another users Movies', function (string $sort) {
    $user = User::factory()->create();
    $facts = UserMovie::factory()->for($user)->create();
    $other = UserMovie::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('watchlist', ['sort' => $sort, 'restore' => $facts->movie->tmdb_id]))->assertRedirect();
    $this->get($response->headers->get('Location'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sort', $sort)
        ->where('movies.data.0.tmdbId', $facts->movie->tmdb_id));
    $this->get(route('watchlist', ['restore' => $other->movie->tmdb_id]))->assertRedirect(route('watchlist'));
})->with(['recently-added', 'oldest-added', 'title', 'newest-release', 'oldest-release']);
