<?php

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;

it('marks a Movie as watched, finds it in Watched, queues a rewatch, and clears and undoes its Watched record', function (int $width) {
    config(['catalog.image_base_url' => '/test-images/']);
    $this->travelTo('2026-09-10 12:00:00');
    $facts = UserMovie::factory()->for(Movie::factory()->state(['tmdb_id' => 1, 'title' => 'Arrival', 'poster_path' => null]))->create();
    $this->actingAs($facts->user);

    $page = visitWithCsp('/watchlist', width: $width);
    $page->click('[data-slot="movie-menu"]')->click('Mark as watched')->assertSee('Watchlist · 0')
        ->click('nav[aria-label="Your movies"] a[href="/watched"]')->assertSee('Watched · 1')
        ->assertSee('Watched Sep 10, 2026')->assertAttribute('[data-slot="bookmark-toggle"]', 'aria-pressed', 'false')
        ->click('[data-slot="bookmark-toggle"]')->assertAttribute('[data-slot="bookmark-toggle"]', 'aria-pressed', 'true')
        ->assertSee('Last watched Sep 10, 2026')
        ->click('[data-slot="movie-menu"]')->click('Clear Watched')->assertSee('Watched · 0')
        ->assertSee('Nothing marked as watched yet')->assertSee('Mark a movie as watched from its page or your Watchlist.')
        ->assertSee('Cleared from Watched')->assertNoJavaScriptErrors();

    expect($page->script('document.activeElement.id'))->toBe('watched-empty-heading');
    $this->assertDatabaseHas('user_titles', ['id' => $facts->id, 'watched_at' => null]);
    $page->keys('*:focus', ['Alt+KeyT', 'Enter'])
        ->assertSee('Watched · 1')->assertSee('Last watched Sep 10, 2026')->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('user_titles', ['id' => $facts->id, 'watched_at' => '2026-09-10 12:00:00']);
    $page->click('[data-slot="bookmark-toggle"]')->assertAttribute('[data-slot="bookmark-toggle"]', 'aria-pressed', 'false')
        ->assertSee('Watched · 1')->assertSee('Watched Sep 10, 2026')->assertDontSee('Last watched Sep 10, 2026')
        ->assertSee('Removed from your Watchlist')->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('user_titles', ['id' => $facts->id, 'watchlisted_at' => null, 'watched_at' => '2026-09-10 12:00:00']);
    settleToasts($page);
    $page->assertNoAccessibilityIssues();
})->with(['desktop' => 1440, 'mobile' => 390]);

it('clears cards with focus on the next and previous menus and formats Watched dates in the viewers time zone', function () {
    config(['catalog.image_base_url' => '/test-images/']);
    $user = User::factory()->create();
    foreach (['Apple', 'Banana', 'Cherry'] as $index => $title) {
        UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $index + 1, 'title' => $title, 'poster_path' => null]))->watched()->create(['watched_at' => '2026-08-31 20:00:00']);
    }
    $this->actingAs($user);

    $page = visitWithCsp('/watched?sort=title', timezone: 'Pacific/Auckland');
    $page->assertSee('Watched Sep 1, 2026')
        ->click('[data-tmdb-id="2"] [data-slot="movie-menu"]')->click('Clear Watched')->assertSee('Watched · 2');
    expect($page->script('document.activeElement.closest("[data-tmdb-id]").dataset.tmdbId'))->toBe('3');
    expect($page->script('document.activeElement.dataset.slot'))->toBe('movie-menu');
    $page->click('[data-tmdb-id="3"] [data-slot="movie-menu"]')->click('Clear Watched')->assertSee('Watched · 1')->assertNoJavaScriptErrors();
    expect($page->script('document.activeElement.closest("[data-tmdb-id]").dataset.tmdbId'))->toBe('1');
});

it('restores and focuses a cleared Watched card after changing sort even when it belongs beyond the first page', function () {
    config(['catalog.image_base_url' => '/test-images/']);
    $user = User::factory()->create();
    foreach (range(1, 50) as $number) {
        UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $number, 'title' => sprintf('Movie %02d', $number), 'poster_path' => null]))->watched()->create(['watched_at' => $number === 1 ? '2026-01-01' : '2026-02-01']);
    }
    $this->actingAs($user);

    $page = visitWithCsp('/watched?sort=title');
    $page->click('[data-tmdb-id="1"] [data-slot="movie-menu"]')->click('Clear Watched')->assertSee('Watched · 49')
        ->select('#watched-sort', 'recently-watched')->assertQueryStringMissing('sort')
        ->click('Undo')->assertSee('Watched · 50')->assertPresent('[data-slot="watched-grid"] [data-tmdb-id="1"]')
        ->assertNoJavaScriptErrors();
    expect($page->script(<<<'JS'
        () => new Promise((resolve) => {
            const started = performance.now();
            const check = () => document.activeElement.closest('[data-tmdb-id]')?.dataset.tmdbId === '1' || performance.now() - started > 1500
                ? resolve(document.activeElement.dataset.slot) : requestAnimationFrame(check);
            check();
        })
        JS))->toBe('movie-menu');
    expect($page->script('document.activeElement.closest("[data-tmdb-id]").dataset.tmdbId'))->toBe('1');
});

it('formats Watched dates in the viewers locale', function (string $locale, string $expected) {
    config(['catalog.image_base_url' => '/test-images/']);
    $facts = UserMovie::factory()->for(Movie::factory()->state(['poster_path' => null]))->watched()->create(['watched_at' => '2026-03-12 12:00:00']);
    $this->actingAs($facts->user);

    visitWithCsp('/watched', locale: $locale)->assertSee('Watched '.$expected)->assertNoJavaScriptErrors();
})->with([
    'French' => ['fr-FR', '12 mars 2026'],
    'German' => ['de-DE', '12. März 2026'],
]);
