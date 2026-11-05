<?php

use App\Catalog\CatalogSource;
use App\Catalog\FakeCatalogSource;
use App\Catalog\MovieDetails;
use App\Models\Movie;

function layoutMovie(): Movie
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
