<?php

use App\Models\Movie;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\MovieDetails;
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
 */
function visitWithCsp(string $url, int $width = 1440, int $height = 900, array $headers = []): AwaitableWebpage
{
    $context = Playwright::browser(Playwright::defaultBrowserType())->launch()->newContext([
        'bypassCSP' => false,
        'extraHTTPHeaders' => array_map(fn (string $name, string $value): array => ['name' => $name, 'value' => $value], array_keys($headers), $headers),
        'viewport' => ['width' => $width, 'height' => $height],
        'locale' => 'en-US',
        'timezoneId' => 'UTC',
        'colorScheme' => 'light',
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
