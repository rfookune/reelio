<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Season;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserTitle;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\Listings\MovieListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\InitScript;
use Pest\Browser\Playwright\Playwright;
use Pest\Browser\Support\ComputeUrl;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

pest()->beforeEach(function () {
    Vite::useHotFile(storage_path('framework/testing/browser.hot'));
})->in('Browser');

pest()->beforeEach(function () {
    app()->instance(CatalogSource::class, new FakeCatalogSource);
})->in('Feature', 'Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Use built assets and enforce the real CSP from the first document onward.
 *
 * Pest 5.2 bypasses CSP by default and does not collect policy violations.
 * Its context API lets us install the error collector before navigation.
 *
 * @param  array<string, string>  $headers  Extra headers sent with every request, such as the edge's country header.
 * @param  string  $timezone  The browser's time zone.
 * @param  string  $locale  The browser's locale.
 * @param  string  $colorScheme  The operating system's colour scheme, "light" or "dark".
 */
function visitWithCsp(string $url, int $width = 1440, int $height = 900, array $headers = [], string $timezone = 'UTC', string $locale = 'en-US', string $colorScheme = 'light'): AwaitableWebpage
{
    $context = Playwright::browser(Playwright::defaultBrowserType())->launch()->newContext([
        'bypassCSP' => false,
        'extraHTTPHeaders' => array_map(fn (string $name, string $value): array => ['name' => $name, 'value' => $value], array_keys($headers), $headers),
        'viewport' => ['width' => $width, 'height' => $height],
        'locale' => $locale,
        'timezoneId' => $timezone,
        'colorScheme' => $colorScheme,
        'permissions' => ['clipboard-read', 'clipboard-write'],
    ]);

    $context->addInitScript(InitScript::get().<<<'JS'

        document.addEventListener('securitypolicyviolation', (event) => {
            window.__pestBrowser.jsErrors.push({
                message: `CSP violation: ${event.effectiveDirective} blocked ${event.blockedURI}`,
            });
        });
        JS);

    $url = ComputeUrl::from($url);

    return new AwaitableWebpage($context->newPage()->goto($url), $url);
}

/**
 * Visits a URL with the `appearance` cookie set to "light" or "dark", so the server renders that theme, and the
 * operating system set to the same scheme, for the pages that follow it instead, such as Laravel's own 404 and 500.
 * The cookie is written in the browser and the page loaded again, as a visitor's earlier choice would be.
 */
function visitWithAppearance(string $url, string $appearance, int $width = 1440, int $height = 900): AwaitableWebpage
{
    $page = visitWithCsp($url, $width, $height, colorScheme: $appearance);
    $page->script("() => { document.cookie = 'appearance={$appearance};path=/;SameSite=Lax'; }");

    return $page->navigate(ComputeUrl::from($url));
}

/** Waits for Sonner's toast transitions to finish, so accessibility checks see settled colours. */
function settleToasts(AwaitableWebpage $page): void
{
    $page->script('() => Promise.all(document.getAnimations().map((animation) => animation.finished))');
}

function pasteInto(AwaitableWebpage $page, string $selector, string $value): void
{
    $page->script('() => navigator.clipboard.writeText('.json_encode($value, JSON_THROW_ON_ERROR).')');
    $page->keys($selector, 'ControlOrMeta+V')->assertValue($selector, $value);
}

/**
 * The Catalog source's details for a stored Movie, matching its row, so a refresh keeps what the test stored.
 * Named arguments override any field, such as `directors` or `availability`.
 */
function sourceDetailsFor(Movie $movie, mixed ...$overrides): MovieDetails
{
    return new MovieDetails(...[
        'tmdbId' => $movie->tmdb_id,
        'title' => $movie->title,
        'originalTitle' => $movie->original_title,
        'overview' => $movie->overview,
        'tagline' => $movie->tagline,
        'releaseDate' => $movie->release_date?->toDateString(),
        'runtime' => $movie->runtime,
        'posterPath' => $movie->poster_path,
        'backdropPath' => $movie->backdrop_path,
        'originalLanguage' => $movie->original_language,
        'imdbId' => $movie->imdb_id,
        ...$overrides,
    ]);
}

/**
 * Binds a fake Catalog source that knows the given Movies: a stored Movie as its row stands, or explicit details.
 */
function fakeCatalogKnowing(Movie|MovieDetails ...$movies): FakeCatalogSource
{
    $source = new FakeCatalogSource(array_map(fn (Movie|MovieDetails $movie): MovieDetails => $movie instanceof Movie ? sourceDetailsFor($movie) : $movie, array_values($movies)));
    app()->instance(CatalogSource::class, $source);

    return $source;
}

/** A list entry for a Discover section, with a poster, a backdrop and an overview unless told otherwise. */
function movieListing(int $tmdbId, mixed ...$overrides): MovieListing
{
    return new MovieListing(...[
        'tmdbId' => $tmdbId,
        'title' => "Movie {$tmdbId}",
        'overview' => "About movie {$tmdbId}.",
        'releaseDate' => '2026-01-01',
        'posterPath' => "/poster{$tmdbId}.jpg",
        'backdropPath' => "/backdrop{$tmdbId}.jpg",
        'voteAverage' => 7.5,
        'voteCount' => 1000,
        ...$overrides,
    ]);
}

/** @return list<MovieListing> */
function movieListings(int $from, int $to): array
{
    return array_map(fn (int $tmdbId): MovieListing => movieListing($tmdbId), range($from, $to));
}

/**
 * Installs a fake source with the given section pages, and returns it. Every listed Movie has details, the given
 * ones or bare ones, unless it is named as unknown.
 *
 * @param  list<MovieDetails>  $movies
 * @param  list<int>  $unknown
 */
function fakeDiscoverSource(array $sections, array $movies = [], array $failingSections = [], array $unknown = []): FakeCatalogSource
{
    $given = collect($movies)->keyBy('tmdbId');
    $listed = collect($sections)->flatten(2)->map(fn (MovieListing $listing): int => $listing->tmdbId)->unique()->diff($unknown);
    $details = $listed->map(fn (int $tmdbId): MovieDetails => $given->get($tmdbId) ?? new MovieDetails(tmdbId: $tmdbId, title: "Movie {$tmdbId}"))->values()->all();
    $source = new FakeCatalogSource(movies: $details, sections: $sections, failingSections: $failingSections);
    app()->instance(CatalogSource::class, $source);

    return $source;
}

/** A TV Show with the given Name and first-air date, on the user's Watchlist since the given date when one is given. */
function listedTvShow(User $user, int $tmdbId, string $title, ?string $firstAirDate, ?string $watchlistedAt = null): TvShow
{
    $tvShow = TvShow::factory()->create(['tmdb_id' => $tmdbId, 'title' => $title]);
    $tvShow->details->update(['first_air_date' => $firstAirDate]);

    if ($watchlistedAt !== null) {
        UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => $watchlistedAt]);
    }

    return $tvShow;
}

/** An Episode of the TV Show's Season (a new one when none is given), marked as watched by the user on the given date when one is given. */
function episodeOf(TvShow $tvShow, ?User $user = null, ?string $watchedAt = null, int $seasonNumber = 1, string $airDate = '2025-01-01', ?Season $season = null): Episode
{
    $season ??= Season::query()->where('title_id', $tvShow->id)->where('season_number', $seasonNumber)->first()
        ?? Season::factory()->create(['title_id' => $tvShow->id, 'season_number' => $seasonNumber]);
    $episode = Episode::factory()->create(['season_id' => $season->id, 'air_date' => $airDate, 'episode_number' => Episode::query()->where('season_id', $season->id)->count() + 1]);

    if ($user !== null && $watchedAt !== null) {
        UserEpisode::factory()->create(['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => $watchedAt]);
    }

    return $episode;
}
