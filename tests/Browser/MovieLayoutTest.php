<?php

use App\Catalog\CatalogSource;
use App\Catalog\FakeCatalogSource;
use App\Catalog\MovieDetails;
use App\Models\Movie;
use App\Ratings\RatingSourceName;
use App\Ratings\RatingStore;

/** @param array{key: string, name: string, type: string}|null $trailer */
function layoutMovie(?array $trailer = null): Movie
{
    config(['catalog.image_base_url' => '/test-images/']);
    $movie = Movie::factory()->create([
        'tmdb_id' => 42,
        'title' => 'Interstellar',
        'original_title' => 'Intersteller',
        'original_language' => 'fr',
        'tagline' => 'Go further.',
        'poster_path' => '/poster.jpg',
        'backdrop_path' => '/backdrop.jpg',
        'runtime' => 169,
    ]);
    app()->instance(CatalogSource::class, new FakeCatalogSource([new MovieDetails(
        tmdbId: 42,
        directors: ['Christopher Nolan'],
        writers: ['Jonathan Nolan'],
        cast: [['id' => 1, 'name' => 'Matthew McConaughey', 'character' => 'Cooper', 'profilePath' => null]],
        certification: 'PG-13',
        usReleaseDate: '2014-11-07',
        trailer: $trailer,
    )]));

    return $movie;
}

it('lays the page out as a hero with a sticky details column on desktop', function () {
    $movie = layoutMovie();

    $page = visitWithCsp(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id], absolute: false));

    $page->assertSee('Go further.')
        ->assertSee('Matthew McConaughey')
        ->assertSee('Cooper')
        ->assertSee('Christopher Nolan')
        ->assertSee('Released (US)')
        ->assertSee('PG-13')
        ->assertSee('2h 49m')
        ->assertAttribute('[data-slot="movie-hero"] picture source', 'srcset', '/test-images/w1280/backdrop.jpg')
        ->assertAttribute('[data-slot="movie-hero"] picture img', 'src', '/test-images/w780/backdrop.jpg')
        ->assertAttribute('[data-slot="movie-hero"] picture img', 'fetchpriority', 'high')
        ->assertPresent('[data-slot="movie-glow"] img')
        ->assertAttribute('[data-slot="movie-hero"] p[lang="fr"]', 'lang', 'fr')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelectorAll("[fetchpriority=high]").length'))->toBe(1)
        ->and($page->script('getComputedStyle(document.querySelector("[data-slot=movie-details-column]")).position'))->toBe('sticky')
        ->and($page->script('document.querySelector("[data-slot=movie-details-column]").getBoundingClientRect().left > document.querySelector("[data-slot=movie-overview]").getBoundingClientRect().right'))->toBeTrue();
});

it('stacks the sections in the decided order below 1024px without the hero poster', function () {
    $movie = layoutMovie();

    $page = visitWithCsp(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id], absolute: false), 390, 800);

    expect($page->script('getComputedStyle(document.querySelector("[data-slot=movie-details-column]")).position'))->toBe('static')
        ->and($page->script('(() => { const y = (s) => document.querySelector(s).getBoundingClientRect().top; return y("[data-slot=movie-hero]") < y("[data-slot=movie-overview]") && y("[data-slot=movie-overview]") < y("#cast-heading") && y("#cast-heading") < y("[data-slot=movie-details]"); })()'))->toBeTrue()
        ->and($page->script('document.querySelector("[data-slot=movie-hero] [data-slot=tmdb-image] img").currentSrc'))->toBe('')
        ->and($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
});

it('renders a sparse movie with a blurred poster hero and no optional sections', function () {
    config(['catalog.image_base_url' => '/test-images/']);
    $movie = Movie::factory()->create(['tagline' => null, 'overview' => null, 'backdrop_path' => null, 'poster_path' => '/poster.jpg']);

    $page = visitWithCsp(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id], absolute: false));

    $page->assertPresent('[data-slot="movie-hero-backdrop"] img')
        ->assertMissing('[data-slot="movie-hero-backdrop"] picture')
        ->assertMissing('#cast-heading')
        ->assertNoJavaScriptErrors();
});

it('shows temporarily unavailable panels when the source is down', function () {
    $movie = Movie::factory()->create(['title' => 'Stored Title']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $page = visitWithCsp(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id], absolute: false));

    $page->assertSee('Stored Title')
        ->assertSee('Cast is temporarily unavailable.')
        ->assertSee('Temporarily unavailable')
        ->assertMissing('[data-slot="movie-hero"] .border-control')
        ->assertNoJavaScriptErrors();
});

it('loads nothing from YouTube until play is pressed, then embeds the trailer', function () {
    $movie = layoutMovie(trailer: ['key' => 'LY19rHKAaAg', 'name' => 'Trailer 4', 'type' => 'Trailer']);

    $page = visitWithCsp(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id], absolute: false));

    $page->assertSee('Trailer · Trailer 4')
        ->assertSee('Plays from YouTube. YouTube may set cookies once you press play.')
        ->assertAttribute('[data-slot="trailer"] a', 'href', 'https://www.youtube.com/watch?v=LY19rHKAaAg')
        ->assertAttribute('[data-slot="trailer"] a', 'target', '_blank')
        ->assertAttribute('[data-slot="trailer"] a', 'rel', 'noopener noreferrer')
        ->assertAttribute('[data-slot="trailer"] button', 'aria-label', 'Play trailer: Interstellar')
        ->assertMissing('[data-slot="trailer"] iframe');

    $page->click('[data-slot="trailer"] button')
        ->assertPresent('[data-slot="trailer"] iframe')
        ->assertAttribute('[data-slot="trailer"] iframe', 'src', 'https://www.youtube-nocookie.com/embed/LY19rHKAaAg?rel=0&autoplay=1')
        ->assertAttribute('[data-slot="trailer"] iframe', 'title', 'Trailer: Interstellar')
        ->assertAttribute('[data-slot="trailer"] iframe', 'referrerpolicy', 'strict-origin-when-cross-origin')
        ->assertAttribute('[data-slot="trailer"] iframe', 'allow', 'autoplay; fullscreen; encrypted-media');

    expect($page->script('document.activeElement === document.querySelector("[data-slot=trailer] iframe")'))->toBeTrue();
    expect($page->script('(() => { const r = document.querySelector("[data-slot=trailer] iframe").getBoundingClientRect(); return r.width >= 200 && r.height >= 200; })()'))->toBeTrue();
});

it('hides the trailer section without a trailer and shows a panel when the source is down', function () {
    $movie = layoutMovie();
    $page = visitWithCsp(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id], absolute: false));
    $page->assertMissing('[data-slot="trailer"]')->assertMissing('[data-slot="trailer-unavailable"]');

    $down = Movie::factory()->create();
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    $page = visitWithCsp(route('movies.show', ['slug' => $down->slug, 'id' => $down->id], absolute: false));
    $page->assertSee('The trailer is temporarily unavailable.');
});

it('shows the TMDB rating with a compact vote count, or "Not yet rated"', function () {
    $rated = Movie::factory()->create(['tmdb_id' => 157336]);
    $unrated = Movie::factory()->create();
    app(RatingStore::class)->record(RatingSourceName::Tmdb, '157336', 8.5, 41832);

    $page = visitWithCsp(route('movies.show', ['slug' => $rated->slug, 'id' => $rated->id], absolute: false));
    $page->assertSeeIn('[data-slot="external-ratings"]', 'TMDB 8.5 41.8K')->assertDontSee('Not yet rated');

    $page = visitWithCsp(route('movies.show', ['slug' => $unrated->slug, 'id' => $unrated->id], absolute: false));
    $page->assertSeeIn('[data-slot="external-ratings"]', 'Not yet rated')->assertDontSee('0.0');
});

it('shows the IMDb rating before TMDB with IMDb\'s credit, and turning IMDb off leaves TMDB in place', function () {
    $movie = Movie::factory()->create(['tmdb_id' => 157336, 'imdb_id' => 'tt0816692']);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0816692', 8.7, 2_400_000);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, '157336', 8.5, 41832);
    $url = route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id], absolute: false);

    $page = visitWithCsp($url);
    $page->assertSeeIn('[data-slot="external-ratings"]', 'IMDb 8.7 2.4M · TMDB 8.5 41.8K')
        ->assertSeeIn('[data-slot="imdb-credit"]', 'Information courtesy of IMDb (https://www.imdb.com). Used with permission.')
        ->assertNoJavaScriptErrors();

    config(['ratings.sources.imdb.enabled' => false]);

    $page = visitWithCsp($url);
    $page->assertSeeIn('[data-slot="external-ratings"]', 'TMDB 8.5 41.8K')
        ->assertDontSee('IMDb 8.7')
        ->assertMissing('[data-slot="imdb-credit"]');
});
