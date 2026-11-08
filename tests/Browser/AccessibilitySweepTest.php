<?php

use App\Models\Genre;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\MovieDetails;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * Sets up the named page state and returns the URL to visit.
 */
function accessibilitySweepUrl(string $scenario): string
{
    config(['catalog.image_base_url' => '/test-images/']);
    $user = fn (): User => tap(User::factory()->create(), fn (User $user) => test()->actingAs($user));

    return match ($scenario) {
        'discover' => (function (): string {
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

            return '/';
        })(),
        'search-empty' => '/search',
        'search-results' => (function (): string {
            app()->instance(CatalogSource::class, new FakeCatalogSource(
                array_map(fn ($listing): MovieDetails => new MovieDetails(tmdbId: $listing->tmdbId, title: $listing->title), movieListings(1, 8)),
                searches: ['inter' => [movieListings(1, 8)]],
            ));

            return '/search?q=inter';
        })(),
        'search-no-results' => (function (): string {
            app()->instance(CatalogSource::class, new FakeCatalogSource([], searches: ['zzzz' => [[]]]));

            return '/search?q=zzzz';
        })(),
        'movie' => (function (): string {
            $movie = Movie::factory()->create(['tmdb_id' => 157336, 'title' => 'Interstellar']);
            fakeCatalogKnowing($movie);

            return $movie->canonicalUrl();
        })(),
        'removed-movie' => Movie::factory()->removed()->create()->canonicalUrl(),
        'watchlist-empty' => (function () use ($user): string {
            $user();

            return '/watchlist';
        })(),
        'watchlist-full', 'picker' => (function () use ($user): string {
            $member = $user();
            $drama = Genre::factory()->create(['id' => 18, 'name' => 'Drama']);
            foreach ([1 => 'Apple', 2 => 'Banana', 3 => 'Cherry'] as $tmdbId => $title) {
                $movie = Movie::factory()->create(['tmdb_id' => $tmdbId, 'title' => $title, 'slug' => strtolower($title), 'runtime' => 95, 'poster_path' => null]);
                $movie->genres()->attach($drama);
                UserMovie::factory()->for($member)->for($movie)->create();
            }
            UserMovie::factory()->for($member)->for(Movie::factory()->removed()->state(['tmdb_id' => 4]))->create();

            return '/watchlist';
        })(),
        'watched' => (function () use ($user): string {
            $member = $user();
            foreach ([1 => 'Apple', 2 => 'Banana'] as $tmdbId => $title) {
                UserMovie::factory()->for($member)->for(Movie::factory()->state(['tmdb_id' => $tmdbId, 'title' => $title, 'poster_path' => null]))->create(['watched_at' => '2026-03-12']);
            }

            return '/watched';
        })(),
        'login' => '/login',
        'register' => '/register',
        'forgot-password' => '/forgot-password',
        'settings-profile' => (function () use ($user): string {
            $user();

            return '/settings/profile';
        })(),
        'settings-security' => (function () use ($user): string {
            $user();

            return '/settings/security';
        })(),
        'settings-appearance' => (function () use ($user): string {
            $user();

            return '/settings/appearance';
        })(),
        '404' => '/no-such-page',
        '429' => (function (): string {
            test()->freezeTime();
            for ($attempt = 0; $attempt < 60; $attempt++) {
                RateLimiter::hit(md5('search'.'ip:127.0.0.1'));
            }

            return '/search';
        })(),
        '500' => (function (): string {
            Route::get('/sweep-server-error', fn () => abort(500))->middleware('web');

            return '/sweep-server-error';
        })(),
    };
}

it('has no level 1 accessibility issues on :scenario at :width px in the :appearance theme', function (string $scenario, int $width, string $appearance) {
    $url = accessibilitySweepUrl($scenario);

    $page = visitWithAppearance($url, $appearance, $width, $width > 700 ? 900 : 844);

    if ($scenario === 'picker') {
        $page->click('[data-slot="pick-for-me"]')->assertPresent('[data-slot="picked-movie"]');
    }
    settleToasts($page);

    $page->assertNoAccessibilityIssues(level: 1)->assertNoJavaScriptErrors();
    if (! in_array($scenario, ['404', '500'], true)) {
        expect($page->script('document.documentElement.classList.contains("dark")'))->toBe($appearance === 'dark');
    }
})->with(function () {
    $scenarios = [
        'discover', 'search-empty', 'search-results', 'search-no-results', 'movie', 'removed-movie',
        'watchlist-empty', 'watchlist-full', 'watched', 'picker', 'login', 'register', 'forgot-password',
        'settings-profile', 'settings-security', 'settings-appearance', '404', '429', '500',
    ];

    foreach ($scenarios as $scenario) {
        foreach ([1440 => 'desktop', 390 => 'mobile'] as $width => $size) {
            foreach (['light', 'dark'] as $appearance) {
                yield "{$scenario} {$size} {$appearance}" => [$scenario, $width, $appearance];
            }
        }
    }
});
