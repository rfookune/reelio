<?php

use App\Models\Movie;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
});

/** The components the SSR server was asked to render. */
function renderedComponents(): array
{
    return Http::recorded()->map(fn (array $pair): string => $pair[0]->data()['component'] ?? '')->filter()->values()->all();
}

test('Discover and Movie pages are rendered on the server', function () {
    Http::fake(['127.0.0.1:13714/render' => Http::response(['head' => ['<title>SSR</title>'], 'body' => '<div id="app" data-server-rendered="true"></div>'])]);
    $movie = Movie::factory()->create();
    fakeCatalogKnowing($movie);

    $this->get(route('home'))->assertOk()->assertSee('data-server-rendered', false);
    $this->get($movie->canonicalUrl())->assertOk()->assertSee('data-server-rendered', false);

    expect(renderedComponents())->toBe(['Discover', 'movies/Show']);
});

test('the About, Privacy, Terms and Accessibility pages are rendered on the server', function (string $name) {
    Http::fake(['127.0.0.1:13714/render' => Http::response(['head' => [], 'body' => '<div></div>'])]);
    Route::middleware('web')->get("/{$name}-page", fn () => inertia('Discover'))->name($name);

    $this->get("/{$name}-page");

    expect(renderedComponents())->toBe(['Discover']);
})->with(['about', 'privacy', 'terms', 'accessibility']);

test('Search, auth, Watchlist, Watched and settings pages are not rendered on the server', function () {
    Http::fake();
    $this->get(route('search', ['q' => 'matrix']));
    $this->get(route('login'));
    $this->get(route('register'));
    $this->actingAs(User::factory()->create());
    $this->get(route('watchlist'));
    $this->get(route('watched'));
    $this->get(route('profile.edit'));
    $this->get(route('appearance.edit'));

    Http::assertNothingSent();
});

test('when the SSR server is down the page is rendered on the client', function () {
    Http::fake(['127.0.0.1:13714/render' => fn () => throw new ConnectionException('Connection refused')]);

    $this->get(route('home'))->assertOk()->assertSee('data-page', false);
});

test('SSR is off for every page when it is switched off', function () {
    config(['inertia.ssr.enabled' => false]);
    Http::fake();

    $this->get(route('home'))->assertOk();

    Http::assertNothingSent();
});
