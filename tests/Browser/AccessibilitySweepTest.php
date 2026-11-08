<?php

use App\Enums\ProgressState;
use App\Enums\TitleKind;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserMovie;
use App\Models\UserTitle;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\EpisodeDetails;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\Details\SeasonDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/** A complete TV Show page, with three aired Episodes and the requested user progress. */
function accessibilitySweepTvShow(User $user, ProgressState $state): TvShow
{
    $show = TvShow::factory()->create(['tmdb_id' => 87108, 'title' => 'Chernobyl', 'slug' => 'chernobyl']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(
        87108, title: 'Chernobyl', overview: 'The cost of lies.', firstAirDate: '2019-05-06', lastAirDate: '2019-06-03', status: 'Ended',
        creators: ['Craig Mazin'], networks: ['HBO'], genres: [['id' => 18, 'name' => 'Drama']],
        voteAverage: 8.5, voteCount: 100,
        availability: [['serviceId' => 49, 'serviceName' => 'HBO', 'logoPath' => null, 'country' => 'US', 'offerType' => 'stream', 'displayPriority' => 1]],
        seasons: [new SeasonDetails(100, 1, name: 'Miniseries', episodes: [
            new EpisodeDetails(200, 1, 1, name: '1:23:45', overview: 'An accident changes everything.', airDate: '2019-05-06', runtime: 61),
            new EpisodeDetails(201, 1, 2, name: 'Please Remain Calm', airDate: '2019-05-13', runtime: 65),
            new EpisodeDetails(202, 1, 3, name: 'Open Wide, O Earth', airDate: '2019-05-20', runtime: 65),
        ])],
    )]));
    app(Catalog::class)->tvShowDetails($show);
    $watched = match ($state) {
        ProgressState::NotStarted => 0,
        ProgressState::InProgress => 1,
        ProgressState::Completed => 3,
    };
    foreach ($show->episodes()->orderBy('episode_number')->get()->take($watched) as $episode) {
        UserEpisode::factory()->for($user)->for($episode)->create(['watched_at' => '2026-01-01']);
    }

    return $show;
}

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
            app(DiscoverBuilder::class)->build(CatalogProfile::Background);

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
        'search-tv-results' => (function (): string {
            app()->instance(CatalogSource::class, new FakeCatalogSource(searches: ['chernobyl' => [[
                movieListing(87108, title: 'Chernobyl', kind: TitleKind::TvShow, posterPath: null),
                movieListing(1, title: 'A Movie', posterPath: null),
            ]]]));

            return '/search?q=chernobyl';
        })(),
        'tv-not-started-open', 'tv-not-started-closed', 'tv-in-progress-open', 'tv-in-progress-closed', 'tv-completed-open', 'tv-completed-closed' => (function () use ($user, $scenario): string {
            $state = match (true) {
                str_contains($scenario, 'not-started') => ProgressState::NotStarted,
                str_contains($scenario, 'in-progress') => ProgressState::InProgress,
                default => ProgressState::Completed,
            };

            return accessibilitySweepTvShow($user(), $state)->canonicalUrl();
        })(),
        'watchlist-tv', 'watched-tv' => (function () use ($user, $scenario): string {
            $member = $user();
            $show = accessibilitySweepTvShow($member, $scenario === 'watchlist-tv' ? ProgressState::InProgress : ProgressState::Completed);
            $removed = TvShow::factory()->removed()->create();
            $isWatchlist = $scenario === 'watchlist-tv';
            foreach ([$show, $removed] as $tvShow) {
                UserTitle::query()->create(['user_id' => $member->id, 'title_id' => $tvShow->id, 'watchlisted_at' => $isWatchlist ? '2026-01-01' : null]);
            }
            episodeOf($removed, $member, '2026-01-01');
            UserMovie::factory()->for($member)->for(Movie::factory()->state(['title' => 'Arrival', 'poster_path' => null]))->watched()->create(['watchlisted_at' => '2026-01-01']);

            return $scenario === 'watchlist-tv' ? '/watchlist' : '/watched';
        })(),
        'removed-tv' => (function () use ($user): string {
            $member = $user();
            $show = TvShow::factory()->removed()->create();
            UserTitle::query()->create(['user_id' => $member->id, 'title_id' => $show->id, 'watchlisted_at' => '2026-01-01']);
            episodeOf($show, $member, '2026-01-01');

            return $show->canonicalUrl();
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

    if (str_starts_with($scenario, 'tv-')) {
        $state = match (true) {
            str_contains($scenario, 'not-started') => 'Not started',
            str_contains($scenario, 'in-progress') => 'In progress',
            default => 'Completed',
        };
        $page->assertSeeIn('[data-slot="tv-show-progress"]', $state);
        if (str_ends_with($scenario, '-closed')) {
            $page->click('section[aria-labelledby="seasons-heading"] > details > summary')
                ->assertNotPresent('section[aria-labelledby="seasons-heading"] > details[open]');
        } else {
            $page->assertPresent('section[aria-labelledby="seasons-heading"] > details[open]');
        }
    }
    if (in_array($scenario, ['watchlist-tv', 'watched-tv'], true)) {
        $page->assertPresent('[data-card-key="tv:87108"]')
            ->assertSee('Removed TV show')->assertPresent('[data-slot="tv-state"]');
    }
    if ($scenario === 'search-tv-results') {
        $page->assertPresent('[data-slot="search-results"] [data-slot="tv-badge"]');
    }
    if ($scenario === 'removed-tv') {
        $page->assertSee('This TV show is no longer available.')->assertSee('You marked 1 Episodes as watched');
    }
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
        'tv-not-started-open', 'tv-not-started-closed', 'tv-in-progress-open', 'tv-in-progress-closed', 'tv-completed-open', 'tv-completed-closed',
        'search-tv-results', 'watchlist-tv', 'watched-tv', 'removed-tv',
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
