<?php

use App\Models\Movie;
use Illuminate\Support\Facades\Route;

it('builds responsive poster URLs and falls back when an image fails', function () {
    config(['catalog.image_base_url' => '/test-images/']);
    Route::get('/test-images/{size}/{file}', fn () => response('<svg xmlns="http://www.w3.org/2000/svg" width="2" height="3"></svg>', 200, ['Content-Type' => 'image/svg+xml']));
    $movie = Movie::factory()->create(['title' => 'Interstellar', 'poster_path' => '/yQvGrMoipbRoddT0ZR8tPoR7NfX.jpg']);
    fakeCatalogKnowing($movie);

    $page = visitWithCsp(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id], absolute: false));

    $page->assertSee('Interstellar')
        ->assertAttribute('[data-slot="tmdb-image"] img', 'src', '/test-images/w342/yQvGrMoipbRoddT0ZR8tPoR7NfX.jpg')
        ->assertAttribute('[data-slot="tmdb-image"] img', 'srcset', '/test-images/w342/yQvGrMoipbRoddT0ZR8tPoR7NfX.jpg 342w, /test-images/w500/yQvGrMoipbRoddT0ZR8tPoR7NfX.jpg 500w')
        ->assertAttribute('[data-slot="tmdb-image"] img', 'sizes', '(min-width: 768px) 208px, 160px')
        ->assertNoJavaScriptErrors();

    $page->script('document.querySelector(\'[data-slot="tmdb-image"] img\').dispatchEvent(new Event("error"))');

    $page->assertMissing('[data-slot="tmdb-image"] img')
        ->assertPresent('[data-slot="tmdb-image"] svg')
        ->assertNoJavaScriptErrors();
});

it('uses the missing poster tile for absent or unsafe paths', function (?string $path) {
    $movie = Movie::factory()->create(['poster_path' => $path]);
    fakeCatalogKnowing($movie);

    $page = visitWithCsp(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id], absolute: false));

    $page->assertMissing('[data-slot="tmdb-image"] img')
        ->assertPresent('[data-slot="tmdb-image"] svg')
        ->assertNoJavaScriptErrors();
})->with([null, 'https://untrusted.example/poster.jpg', '//untrusted.example/poster.jpg', '/../../poster.jpg', '/poster.jpg?redirect=1', '/poster.webp']);
