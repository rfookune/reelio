<?php

use App\Models\Movie;
use App\Services\Catalog\AvailabilityWriter;
use Illuminate\Support\Facades\Route;

function availabilityMovie(): Movie
{
    config(['catalog.image_base_url' => '/test-images/']);
    Route::get('/test-images/{size}/{file}', fn () => response('<svg xmlns="http://www.w3.org/2000/svg" width="2" height="2"></svg>', 200, ['Content-Type' => 'image/svg+xml']));
    $movie = Movie::factory()->create(['tmdb_id' => 157336, 'title' => 'Interstellar', 'synced_at' => now()->subDays(9)]);
    $offer = fn (int $id, string $type, int $priority, string $name) => ['serviceId' => $id, 'serviceName' => $name, 'logoPath' => "/logo{$id}.svg", 'country' => 'US', 'offerType' => $type, 'displayPriority' => $priority];
    app(AvailabilityWriter::class)->replace($movie, [
        $offer(350, 'stream', 5, 'Apple TV'),
        $offer(2303, 'stream', 17, 'Paramount Plus Premium'),
        $offer(73, 'free', 3, 'Tubi TV'),
        $offer(10, 'rent', 9, 'Amazon Video'),
    ]);

    return $movie;
}

it('shows Availability on a Movie page at desktop, with keyboard tabs', function () {
    $movie = availabilityMovie();

    $page = visitWithCsp(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id], absolute: false));

    $page->assertSeeIn('[data-slot="where-to-watch"]', 'Where to watch')
        ->assertSeeIn('[data-slot="where-to-watch"]', 'Showing United States')
        ->assertSeeIn('[data-slot="where-to-watch"]', 'Availability changes often and may be incomplete. Not listed doesn\'t mean unavailable.')
        ->assertSeeIn('[data-slot="availability-credit"]', 'Streaming data from JustWatch · Updated 9 days ago · may be out of date')
        ->assertAttribute('[data-slot="where-to-watch"] [role="tab"][aria-selected="true"]', 'data-state', 'active')
        ->assertSeeIn('[data-slot="where-to-watch"] [role="tab"][aria-selected="true"]', 'Stream 2')
        ->assertAttribute('[data-slot="streaming-service"]:first-child img', 'alt', 'Apple TV')
        ->assertAttribute('[data-slot="streaming-service"]:first-child img', 'src', '/test-images/w92/logo350.svg')
        ->assertAttribute('[data-slot="where-to-watch"] a', 'href', "/movies/{$movie->id}/watch?country=US")
        ->assertAttribute('[data-slot="where-to-watch"] a', 'target', '_blank')
        ->assertAttribute('[data-slot="where-to-watch"] a', 'rel', 'nofollow noopener noreferrer')
        ->assertNoJavaScriptErrors();

    expect($page->script('[...document.querySelectorAll("[data-slot=where-to-watch] [role=tab]")].map((tab) => tab.textContent.trim().replace(/\\s+/g, " "))'))
        ->toBe(['Stream 2', 'Free 1', 'Rent 1'])
        ->and($page->script('[...document.querySelectorAll("[data-slot=streaming-service]")].map((row) => row.textContent.trim())'))
        ->toBe(['Apple TV', 'Paramount Plus Premium'])
        ->and($page->script('document.querySelector("[data-slot=where-to-watch]").getBoundingClientRect().top > document.querySelector("[data-slot=movie-details]").getBoundingClientRect().bottom'))->toBeTrue()
        ->and($page->script('document.querySelector("[data-slot=where-to-watch]").getBoundingClientRect().left > document.querySelector("[data-slot=movie-overview]").getBoundingClientRect().right'))->toBeTrue()
        ->and($page->script('document.querySelector("[data-slot=streaming-service]").closest("a, button")'))->toBeNull();

    $page->keys('[data-slot="where-to-watch"] [role="tab"][aria-selected="true"]', 'ArrowRight');

    $page->assertSeeIn('[data-slot="where-to-watch"] [role="tab"][aria-selected="true"]', 'Free 1')
        ->assertSeeIn('[data-slot="where-to-watch"] [role="tabpanel"]', 'Free, may include ads')
        ->assertSeeIn('[data-slot="where-to-watch"] [role="tabpanel"]', 'Tubi TV');

    expect($page->script('document.activeElement.textContent.trim().replace(/\\s+/g, " ")'))->toBe('Free 1');

    $page->assertNoAccessibilityIssues();
});

it('shows Availability right after the overview on mobile', function () {
    $movie = availabilityMovie();
    $movie->update(['overview' => 'A journey beyond the stars.']);

    $page = visitWithCsp(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id], absolute: false), 390, 844);

    $page->assertSeeIn('[data-slot="where-to-watch"]', 'Apple TV')->assertNoJavaScriptErrors();

    expect($page->script('(() => { const y = (s) => document.querySelector(s).getBoundingClientRect().top; return y("[data-slot=movie-overview]") < y("[data-slot=where-to-watch]") && y("[data-slot=where-to-watch]") < y("[data-slot=movie-details]"); })()'))->toBeTrue();

    $page->assertNoAccessibilityIssues();
});

it('shows the empty state with the caveat, credit and link still present', function () {
    $movie = Movie::factory()->create(['synced_at' => now()]);

    $page = visitWithCsp(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id], absolute: false));

    $page->assertSeeIn('[data-slot="availability-empty"]', 'We don\'t have streaming information for this movie in United States.')
        ->assertMissing('[data-slot="where-to-watch"] [role="tablist"]')
        ->assertSeeIn('[data-slot="availability-credit"]', 'Streaming data from JustWatch · Updated today')
        ->assertDontSee('may be out of date')
        ->assertSeeIn('[data-slot="where-to-watch"]', 'See all watch options on TMDB')
        ->assertNoJavaScriptErrors();
});
