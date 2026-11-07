<?php

use App\Models\Movie;
use App\Models\User;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Sources\MovieDetails;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;

/**
 * Builds Discover from a fake source on 6 Nov 2026: Movie 1 is the hero, with a trailer; Movie 31 opens on 21 Nov;
 * every Movie can be opened.
 */
function buildBrowserDiscover(): void
{
    test()->travelTo('2026-11-06 05:00:00');
    config(['catalog.image_base_url' => '/test-images/']);
    fakeDiscoverSource([
        'trending' => [movieListings(1, 10)],
        'popular' => [movieListings(11, 20)],
        'coming_soon' => [[movieListing(31)]],
        'top_rated' => [movieListings(21, 30)],
    ], [
        new MovieDetails(tmdbId: 1, title: 'Movie 1', runtime: 169, posterPath: '/poster1.jpg', trailer: ['key' => 'zSWdZVtXT7E', 'name' => 'Trailer 4', 'type' => 'Trailer']),
        new MovieDetails(tmdbId: 31, title: 'Movie 31', usReleaseDate: '2026-11-21'),
    ]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);
}

it('opens a Movie from Discover and saves it from its page', function (int $width, int $height) {
    buildBrowserDiscover();
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visitWithCsp('/', $width, $height);

    $page->assertSeeIn('#discover-hero-title', 'Movie 1')
        ->assertSeeIn('[data-section="trending"]', 'Trending this week')
        ->assertSeeIn('[data-section="popular"]', 'Popular')
        ->assertSeeIn('[data-section="coming_soon"] [data-tmdb-id="31"]', 'Opens 21 Nov')
        ->assertSeeIn('[data-section="top_rated"]', 'Top rated')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(level: 1)
        ->click('[data-section="popular"] [data-tmdb-id="12"] a:not([aria-hidden])')
        ->assertPathBeginsWith('/movies/movie-12-')
        ->click('[data-slot="watchlist-toggle"]')
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'On your Watchlist')
        ->assertNoJavaScriptErrors();

    $movie = Movie::query()->where('tmdb_id', 12)->sole();
    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => $movie->id]);
    $this->assertDatabaseHas('events', ['name' => 'movie_viewed', 'properties->referrer' => 'discover']);
})->with([
    'desktop' => [1440, 900],
    'mobile' => [390, 844],
]);

it('saves a Movie from its Discover row and keeps the hero and Trending row in step', function () {
    buildBrowserDiscover();
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visitWithCsp('/');

    $page->click('[data-section="trending"] [data-tmdb-id="1"] [data-slot="bookmark-toggle"]')
        ->assertAttribute('[data-section="trending"] [data-tmdb-id="1"] [data-slot="bookmark-toggle"]', 'aria-pressed', 'true')
        ->assertSeeIn('[data-slot="discover-hero"] [data-slot="watchlist-toggle"]', 'On your Watchlist')
        ->assertSeeIn('[data-sonner-toast]', 'Added to your Watchlist')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => Movie::query()->where('tmdb_id', 1)->value('id')]);
});

it('floats the top bar over the hero backdrop as a blurred glow, and opens the trailer player', function () {
    buildBrowserDiscover();

    $page = visitWithCsp('/');

    $page->assertAttribute('[data-slot="discover-hero-backdrop"]', 'fetchpriority', 'high')
        ->assertSeeIn('[data-slot="discover-hero"]', '2h 49m')
        ->click('[data-slot="watch-trailer"]')
        ->assertPresent('[role="dialog"] button[aria-label="Play trailer: Movie 1"]')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelectorAll("[fetchpriority=high]").length'))->toBe(1)
        ->and($page->script('document.querySelector("[data-slot=discover-hero]").getBoundingClientRect().top'))->toBe(0)
        ->and($page->script('getComputedStyle(document.querySelector("[data-slot=top-bar]")).borderBottomColor'))->toBe('rgba(0, 0, 0, 0)')
        ->and($page->script('getComputedStyle(document.querySelector("[data-slot=top-bar]")).backdropFilter'))->toContain('blur');
});

it('says when no movie data can be reached, with Retry and Search', function () {
    $page = visitWithCsp('/');

    $page->assertSee("We can't reach our movie data right now")
        ->assertSee('Retry')
        ->assertPresent('[data-slot="discover-unavailable"] a[href="/search"]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(level: 1);
});

it('credits IMDb in short on a Discover card, linking IMDb itself', function () {
    config(['ratings.sources.imdb.enabled' => true, 'catalog.image_base_url' => '/test-images/']);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000002', 8.1, 5000);
    fakeDiscoverSource(['trending' => [movieListings(1, 6)]], [new MovieDetails(tmdbId: 2, title: 'Movie 2', imdbId: 'tt0000002')]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);

    visitWithCsp('/')
        ->assertSeeIn('[data-slot="discover-row"][data-tmdb-id="2"] [data-slot="imdb-credit"]', 'Information courtesy of IMDb. Used with permission.')
        ->assertDontSeeIn('[data-slot="discover-row"][data-tmdb-id="2"] [data-slot="imdb-credit"]', 'https://www.imdb.com')
        ->assertAttribute('[data-tmdb-id="2"] [data-slot="imdb-credit"] a', 'href', 'https://www.imdb.com')
        ->assertNoJavaScriptErrors();
});
