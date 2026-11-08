<?php

use App\Models\Movie;
use App\Models\User;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/** The `X-Robots-Tag` a response carries, or null when it has none and so may be indexed. */
function robotsTag($response): ?string
{
    return $response->headers->get('X-Robots-Tag');
}

/** Every response that is never indexable, whatever the flag says, as the ticket lists them. */
function neverIndexablePages(): array
{
    $removed = Movie::factory()->removed()->create();
    $cleared = Movie::factory()->capCleared()->create();
    $known = Movie::factory()->create(['tmdb_id' => 157336]);
    app()->instance(CatalogSource::class, new FakeCatalogSource([new MovieDetails(tmdbId: 157336, title: 'Interstellar')]));

    return [
        'Search' => fn ($test) => $test->get(route('search', ['q' => 'matrix'])),
        'Login' => fn ($test) => $test->get(route('login')),
        'Register' => fn ($test) => $test->get(route('register')),
        'Forgot password' => fn ($test) => $test->get(route('password.request')),
        'Watchlist' => fn ($test) => $test->actingAs(User::factory()->create())->get(route('watchlist')),
        'Watched' => fn ($test) => $test->actingAs(User::factory()->create())->get(route('watched')),
        'Settings' => fn ($test) => $test->actingAs(User::factory()->create())->get(route('profile.edit')),
        'Picker' => fn ($test) => $test->actingAs(User::factory()->create())->get(route('watchlist.pick')),
        'Entry route redirect' => fn ($test) => $test->get(route('movies.entry', ['tmdbId' => 157336])),
        'Watch-page redirect' => fn ($test) => $test->get(route('movies.watch', ['id' => $known->id, 'country' => 'US'])),
        '410 page' => fn ($test) => $test->get($removed->canonicalUrl()),
        'Cap-cleared 503' => fn ($test) => $test->get($cleared->canonicalUrl()),
        'Stale-slug redirect' => fn ($test) => $test->get(route('movies.show', ['slug' => 'old-slug', 'id' => $known->id])),
        'Full cast page' => fn ($test) => $test->get(route('movies.cast', ['slug' => 'movie', 'id' => $known->id])),
    ];
}

test('with the flag off, robots.txt disallows everything and every page is noindex', function () {
    config(['seo.indexing' => false]);
    $movie = Movie::factory()->create();
    fakeCatalogKnowing($movie);

    $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertSeeText('Disallow: /', false);
    expect(trim($this->get('/robots.txt')->getContent()))->toBe("User-agent: *\nDisallow: /");

    foreach ([route('home'), $movie->canonicalUrl()] as $url) {
        expect(robotsTag($this->get($url)))->toBe('noindex');
    }
});

test('with the flag on, robots.txt allows crawling and only Discover and Movie pages are indexable', function () {
    config(['seo.indexing' => true]);
    $movie = Movie::factory()->create();
    fakeCatalogKnowing($movie);

    expect(trim($this->get('/robots.txt')->getContent()))->toStartWith("User-agent: *\nDisallow:\n");

    foreach ([route('home'), $movie->canonicalUrl()] as $url) {
        $this->get($url)->assertOk();
        expect(robotsTag($this->get($url)))->toBeNull();
    }
});

test('the About, Privacy, Terms and Accessibility pages are indexable when the flag is on', function (string $name) {
    Route::middleware('web')->get("/{$name}-page", fn () => 'ok')->name($name);

    config(['seo.indexing' => true]);
    expect(robotsTag($this->get("/{$name}-page")))->toBeNull();

    config(['seo.indexing' => false]);
    expect(robotsTag($this->get("/{$name}-page")))->toBe('noindex');
})->with(['about', 'privacy', 'terms', 'accessibility']);

test('these responses are noindex whether the flag is on or off', function (bool $flag, string $page) {
    config(['seo.indexing' => $flag]);

    $response = neverIndexablePages()[$page]($this);

    expect(robotsTag($response))->toBe('noindex');
})->with([true, false])->with(['Search', 'Login', 'Register', 'Forgot password', 'Watchlist', 'Watched', 'Settings', 'Picker', 'Entry route redirect', 'Watch-page redirect', '410 page', 'Cap-cleared 503', 'Stale-slug redirect', 'Full cast page']);

test('the Movie page hands the head what it needs: a canonical URL without a query string, and the static share card', function () {
    $movie = Movie::factory()->create(['title' => 'Interstellar', 'slug' => 'interstellar', 'backdrop_path' => '/b.jpg']);
    fakeCatalogKnowing($movie);

    $this->get($movie->canonicalUrl().'?referrer=discover')->assertOk()->assertInertia(fn ($page) => $page
        ->where('canonicalUrl', $movie->canonicalUrl())
        ->where('seo.url', $movie->canonicalUrl())
        ->where('seo.card', asset('og-card.png'))
        ->where('movie.backdropPath', '/b.jpg'));
    expect(file_exists(public_path('og-card.png')))->toBeTrue();
});

test('Discover hands the head its own URL as canonical, without a query string', function () {
    fakeDiscoverSource(['trending' => [movieListings(1, 5)]]);

    $this->get(route('home', ['utm_source' => 'x']))->assertOk()->assertInertia(fn ($page) => $page->where('seo.url', route('home')));
});

test('a route can be indexable without being server-rendered', function () {
    config(['seo.indexing' => true, 'seo.ssr_routes' => [], 'inertia.ssr.enabled' => true]);
    Http::fake();

    $response = $this->get(route('home'));

    expect(robotsTag($response))->toBeNull();
    Http::assertNothingSent();
});
