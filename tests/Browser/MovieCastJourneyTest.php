<?php

use App\Models\Movie;

/**
 * A Movie with a 40-person billed cast, "Actor 1" to "Actor 40", none with a photo, whose full cast page is open.
 * Billing puts Actors 1–6 in Starring, 7–30 in Supporting and 31–40 in Also appearing.
 */
function visitFullCast(int $width = 1280, int $height = 900)
{
    $movie = Movie::factory()->create(['title' => 'Avengers: Doomsday', 'slug' => 'avengers-doomsday', 'release_date' => '2026-12-18']);
    $cast = array_map(fn (int $i): array => ['id' => $i, 'name' => "Actor {$i}", 'character' => "Role {$i}", 'profilePath' => null], range(1, 40));
    fakeCatalogKnowing(sourceDetailsFor($movie, cast: $cast, crew: [
        ['id' => 901, 'name' => 'Jane Director', 'job' => 'Director'],
        ['id' => 902, 'name' => 'Sam Writer', 'job' => 'Screenplay'],
        ['id' => 901, 'name' => 'Jane Director', 'job' => 'Producer'],
    ]));

    return visitWithCsp(route('movies.cast', ['slug' => 'avengers-doomsday', 'id' => $movie->id]), $width, $height);
}

it('lists the billed cast in tiers, behind a breadcrumb back to the movie', function () {
    $page = visitFullCast();

    $page->assertSee('Full cast')->assertSee('· 40')
        ->assertSeeIn('[data-slot="breadcrumb"]', 'Discover')
        ->assertSeeIn('[data-slot="breadcrumb"]', 'Avengers: Doomsday (2026)')
        ->assertSeeIn('[data-slot="breadcrumb-page"]', 'Full cast')
        ->assertSeeIn('#cast-starring', 'Starring')->assertSee('Supporting')->assertSee('Also appearing · 10')
        ->assertSee('Actor 6')->assertSee('Actor 40')
        ->assertSeeIn('[data-slot="crew-directing"]', 'Jane Director · Director')
        ->assertSeeIn('[data-slot="crew-writing"]', 'Sam Writer · Screenplay')
        ->assertSeeIn('[data-slot="crew-producing"]', 'Jane Director · Producer')
        ->assertSeeIn('[aria-controls="cast-filter"]', 'Filter')->assertMissing('#cast-filter')
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues(level: 1);
});

it('goes back to the movie from the breadcrumb', function () {
    $page = visitFullCast();

    $page->click('[data-slot="back-to-title"]')->assertMissing('[data-slot="back-to-title"]');

    $page->assertSee('Avengers: Doomsday')->assertNoJavaScriptErrors();

    expect($page->script('location.pathname'))->toStartWith('/movies/avengers-doomsday-')->not->toContain('/casts');
});

it('replaces the tiers with the matches while the filter has text, and restores them when it is closed with Escape', function () {
    $page = visitFullCast();

    $page->click('[aria-controls="cast-filter"]')->assertPresent('#cast-filter');
    expect($page->script('document.activeElement.id'))->toBe('cast-filter');

    $page->type('#cast-filter', 'Actor 3')->assertSee('11 matches')
        ->assertMissing('#cast-starring')->assertSee('Actor 30')->assertSee('Actor 3')->assertDontSee('Actor 1')
        ->assertSee('Starring')->assertSee('Supporting')->assertSee('Also appearing')
        ->assertNoAccessibilityIssues(level: 1);
    expect($page->script('document.querySelectorAll("mark").length'))->toBeGreaterThan(0);

    $page->keys('#cast-filter', 'Escape')->assertMissing('#cast-filter')->assertPresent('#cast-starring')->assertSee('Also appearing · 10');
    expect($page->script('document.activeElement.textContent.trim()'))->toStartWith('Filter');
    $page->assertNoJavaScriptErrors();
});

it('matches on the character too, and says when no one matches', function () {
    $page = visitFullCast();

    $page->click('[aria-controls="cast-filter"]')->type('#cast-filter', 'role 12')->assertSee('1 match')->assertSee('Actor 12');

    $page->clear('#cast-filter')->type('#cast-filter', 'nobody')->assertSee('No one matches "nobody".')->assertNoJavaScriptErrors();
});

/**
 * Dispatches a "/" keydown on `body`, where a real one would land (the harness can't press a key there), with the
 * Command key, the Control key or neither held, and says whether the page is on a Mac, whose shortcut is Command.
 */
function pressSlash($page, string $modifier): void
{
    $page->script(sprintf('document.body.dispatchEvent(new KeyboardEvent("keydown", { key: "/", metaKey: %s, ctrlKey: %s, bubbles: true, cancelable: true }))', $modifier === 'meta' ? 'true' : 'false', $modifier === 'ctrl' ? 'true' : 'false'));
}

function pageIsOnAMac($page): bool
{
    return $page->script('/Mac|iPhone|iPad|iPod/.test(navigator.platform)');
}

it('opens the filter with Command + / on a Mac and Control + / elsewhere', function () {
    $page = visitFullCast();
    $mac = pageIsOnAMac($page);

    pressSlash($page, $mac ? 'meta' : 'ctrl');

    $page->assertPresent('#cast-filter');
    expect($page->script('document.activeElement.id'))->toBe('cast-filter');

    $page->type('#cast-filter', 'a/b')->assertSee('No one matches "a/b".')->assertNoJavaScriptErrors();
});

it('does not open the filter for a bare /, or for the other platform\'s modifier', function () {
    $page = visitFullCast();
    $mac = pageIsOnAMac($page);

    pressSlash($page, 'none');
    pressSlash($page, $mac ? 'ctrl' : 'meta');

    $page->assertMissing('#cast-filter')->assertPresent('#cast-starring')->assertNoJavaScriptErrors();
});

it('names the platform\'s shortcut on the Filter button', function () {
    $page = visitFullCast();
    $mac = pageIsOnAMac($page);

    $page->assertSeeIn('[aria-controls="cast-filter"] kbd', $mac ? '⌘' : 'Ctrl');
});

it('closes the filter with its button, clearing the text', function () {
    $page = visitFullCast();

    $page->click('[aria-controls="cast-filter"]')->type('#cast-filter', 'Actor 3')->assertSee('11 matches')
        ->click('[aria-label="Close the filter"]')->assertMissing('#cast-filter')->assertPresent('#cast-starring');
});

it('fits a phone, with the filter under the title', function () {
    $page = visitFullCast(390, 800);

    $page->assertSee('Full cast')->click('[aria-controls="cast-filter"]')->type('#cast-filter', 'Actor 3')->assertSee('11 matches')->assertNoJavaScriptErrors();

    expect($page->script('document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
});
