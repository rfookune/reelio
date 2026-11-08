<?php

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;

it('marks a Movie on the Watchlist as watched, then clears it, keeping focus on the actions', function () {
    $this->travelTo('2026-09-10 12:00:00');
    $facts = UserMovie::factory()->create();
    $movie = $facts->movie;
    fakeCatalogKnowing($movie);
    $this->actingAs($facts->user);

    $page = visitWithCsp($movie->canonicalUrl());

    $page->assertSeeIn('[data-slot="watchlist-toggle"]', 'On your Watchlist')
        ->click('[data-slot="mark-as-watched"]')
        ->assertSeeIn('[data-slot="watched-chip"]', 'Watched 10 Sep 2026')
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'Add to Watchlist')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'false')
        ->assertSee('Marked as watched')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.activeElement.dataset.slot'))->toBe('clear-watched');
    $this->assertDatabaseHas('user_titles', ['id' => $facts->id, 'watchlisted_at' => null, 'watched_at' => '2026-09-10 12:00:00']);
    settleToasts($page);
    $page->assertNoAccessibilityIssues();

    $page->click('[data-slot="clear-watched"]')
        ->assertSeeIn('[data-slot="mark-as-watched"]', 'Mark as watched')
        ->assertNotPresent('[data-slot="watched-chip"]')
        ->assertSee('Cleared from Watched')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.activeElement.dataset.slot'))->toBe('mark-as-watched');
    $this->assertDatabaseCount('user_titles', 0);
});

it('shows a queued rewatch with its last Watched date in the viewer\'s time zone, and clears only Watched', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    UserMovie::factory()->for($user)->for($movie)->create(['watched_at' => '2026-08-31 20:00:00']);
    fakeCatalogKnowing($movie);
    $this->actingAs($user);

    $page = visitWithCsp($movie->canonicalUrl(), timezone: 'Pacific/Auckland');

    $page->assertSeeIn('[data-slot="watchlist-toggle"]', 'On your Watchlist')
        ->assertSeeIn('[data-slot="mark-as-watched"]', 'Mark as watched')
        ->assertSeeIn('[data-slot="last-watched"]', 'Last watched 1 Sep 2026 · Clear')
        ->assertNotPresent('[data-slot="watched-chip"]')
        ->assertNoAccessibilityIssues();

    $page->click('[data-slot="last-watched"] button')
        ->assertNotPresent('[data-slot="last-watched"]')
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'On your Watchlist')
        ->assertSee('Cleared from Watched')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.activeElement.dataset.slot'))->toBe('mark-as-watched');
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $movie->id, 'watched_at' => null]);
});
