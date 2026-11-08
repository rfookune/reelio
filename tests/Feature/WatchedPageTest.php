<?php

use App\Enums\Surface;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Inertia\Testing\AssertableInertia as Assert;

test('guests sign in before returning to Watched', function () {
    $this->get('/watched?sort=title')->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(url('/watched?sort=title'));
});

test('Watched sorts by its own dates, folded titles, or full release dates with undated Movies last', function (string $sort, array $expected) {
    $user = User::factory()->create();
    foreach ([
        ['zebra', '2026-03-02', '2026-01-03'],
        ['Éclair', null, '2026-01-01'],
        ['eclair', '2026-03-01', '2026-01-01'],
        ['Apple', '2025-01-01', '2026-01-02'],
    ] as $index => [$title, $releaseDate, $watchedAt]) {
        UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $index + 1, 'title' => $title, 'release_date' => $releaseDate]))->watched()->create(['watched_at' => $watchedAt]);
    }

    $this->actingAs($user)->get(route('watched', ['sort' => $sort]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sort', $sort)
        ->where('titles.data', fn ($cards) => collect($cards)->pluck('tmdbId')->all() === $expected));
})->with([
    'recently watched' => ['recently-watched', [1, 4, 3, 2]],
    'oldest watched' => ['oldest-watched', [2, 3, 4, 1]],
    'title' => ['title', [4, 2, 3, 1]],
    'newest release' => ['newest-release', [1, 3, 4, 2]],
    'oldest release' => ['oldest-release', [4, 3, 1, 2]],
]);

test('Watched rejects unknown, Watchlist-only, and non-text sorts with 422', function (mixed $sort) {
    $this->actingAs(User::factory()->create())->get(route('watched', ['sort' => $sort]))->assertUnprocessable();
})->with(['unknown' => ['rating'], 'Watchlist sort' => ['recently-added'], 'array' => [['title']]]);

test('Watched cursor paging never skips Movies after records on the preceding page are cleared', function (string $sort) {
    $user = User::factory()->create();
    foreach (Movie::factory()->count(51)->create(['title' => 'Éclair', 'release_date' => null]) as $movie) {
        UserMovie::factory()->for($user)->for($movie)->watched()->create(['watched_at' => '2026-01-01']);
    }
    $first = $this->actingAs($user)->get(route('watched', ['sort' => $sort]))->assertOk();
    $cards = collect($first->viewData('page')['props']['titles']['data']);
    $nextUrl = $first->viewData('page')['props']['titles']['next_page_url'];
    expect($cards)->toHaveCount(48);
    foreach ([$cards->first(), $cards->last()] as $card) {
        $this->delete(route('movies.watched.destroy', ['tmdbId' => $card['tmdbId']]), ['source' => 'watched'])->assertRedirect();
    }

    $next = $this->get($nextUrl)->assertOk();
    $remaining = collect($next->viewData('page')['props']['titles']['data']);
    expect($remaining)->toHaveCount(3);
    expect($cards->pluck('tmdbId')->intersect($remaining->pluck('tmdbId')))->toBeEmpty();
    expect($next->viewData('page')['props']['titles']['next_cursor'])->toBeNull();
    expect($first->viewData('page')['scrollProps']['titles']['pageName'])->toBe('cursor');
    if ($sort === 'recently-watched') {
        expect($nextUrl)->not->toContain('sort=');
    }
})->with(['recently-watched', 'oldest-watched', 'title', 'newest-release', 'oldest-release']);

test('Watched shows only the signed-in users watched Movies newest first, including queued rewatches', function () {
    $user = User::factory()->unverified()->create();
    $old = UserMovie::factory()->for($user)->watched()->create(['watched_at' => '2026-01-01']);
    $recent = UserMovie::factory()->for($user)->create(['watched_at' => '2026-02-01']);
    UserMovie::factory()->for($user)->create();
    UserMovie::factory()->watched()->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['title' => null]))->watched()->create();

    $this->actingAs($user)->get('/watched')->assertOk()->assertHeader('X-Robots-Tag', 'noindex')
        ->assertInertia(fn (Assert $page) => $page->component('Watched')
            ->where('sort', 'recently-watched')
            ->where('titles.data', fn ($cards) => collect($cards)->pluck('tmdbId')->all() === [$recent->movie->tmdb_id, $old->movie->tmdb_id])
            ->where('titles.data.0.href', $recent->movie->canonicalUrl(Surface::Watched))
            ->where('userMovies.'.$recent->movie->tmdb_id, ['watchlisted' => true, 'watched' => true, 'watchedDate' => '2026-02-01T00:00:00.000000Z'])
            ->where('trending', []));
});

test('an empty Watched page has no suggestions or Movies from another user', function () {
    UserMovie::factory()->watched()->create();
    $this->actingAs(User::factory()->create())->get('/watched')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Watched')->where('titles.data', [])->where('userMovies', [])->where('trending', []));
});

test('a restored Watched card uses its own cursor and cannot reveal a different users Movie or an unwatched Movie', function (string $sort) {
    $user = User::factory()->create();
    $facts = UserMovie::factory()->for($user)->watched()->create();
    $unwatched = UserMovie::factory()->for($user)->create();
    $other = UserMovie::factory()->watched()->create();
    $this->actingAs($user);

    $response = $this->get(route('watched', ['sort' => $sort, 'restore' => $facts->movie->tmdb_id]))->assertRedirect();
    $this->get($response->headers->get('Location'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sort', $sort)->where('titles.data.0.tmdbId', $facts->movie->tmdb_id));
    $this->get(route('watched', ['restore' => $unwatched->movie->tmdb_id]))->assertRedirect(route('watched'));
    $this->get(route('watched', ['restore' => $other->movie->tmdb_id]))->assertRedirect(route('watched'));
})->with(['recently-watched', 'oldest-watched', 'title', 'newest-release', 'oldest-release']);
