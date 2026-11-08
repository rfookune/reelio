<?php

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Support\Facades\DB;

it('filters the Watchlist by kind, marks a TV Show as watched after confirming, and undoes it', function (int $width) {
    config(['catalog.image_base_url' => '/test-images/']);
    $user = User::factory()->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 1, 'title' => 'Arrival', 'poster_path' => null]))->create(['watchlisted_at' => '2026-01-01']);
    $first = listedTvShow($user, 2, 'Chernobyl', '2019-05-06', '2026-01-03');
    episodeOf($first, $user, '2026-02-01');
    episodeOf($first);
    $second = listedTvShow($user, 3, 'Severance', '2022-02-18', '2026-01-02');
    episodeOf($second);
    $this->actingAs($user);

    $page = visitWithCsp('/watchlist', width: $width);
    $page->assertSee('Watchlist · 3')
        ->assertSeeIn('[data-filter-chip="all"]', '3')->assertSeeIn('[data-filter-chip="movies"]', '1')->assertSeeIn('[data-filter-chip="tv-shows"]', '2')
        ->assertSeeIn('[data-card-key="tv:2"] [data-slot="tv-badge"]', 'TV')
        ->assertSeeIn('[data-card-key="tv:2"] [data-slot="tv-state"]', 'In progress · 1/2')
        ->assertSeeIn('[data-card-key="tv:3"] [data-slot="tv-state"]', 'Not started · 0/1')
        ->assertPresent('[data-card-key="tv:2"] [data-slot="tv-progress"]')
        ->click('[data-filter-chip="tv-shows"]')
        ->assertMissing('[data-card-key="1"]')->assertPresent('[data-card-key="tv:2"]')->assertPresent('[data-card-key="tv:3"]')
        ->assertPresent('[data-slot="pick-for-me"]');
    expect($page->script('location.search'))->toBe('?filter=tv-shows');

    $page->click('[data-card-key="tv:2"] [data-slot="movie-menu"]')->click('Mark as watched')
        ->assertSee('This marks all 2 aired Episodes as watched.')->click('Cancel')
        ->assertPresent('[data-card-key="tv:2"]')->assertSee('Watchlist · 3');
    $this->assertDatabaseCount('user_episodes', 1);

    $page->click('[data-card-key="tv:2"] [data-slot="movie-menu"]')->click('Mark as watched')
        ->click('[data-slot="tv-show-mark-confirm"]')
        ->assertSee('TV show marked as watched')->assertSee('Watchlist · 2')
        ->assertNotPresent('[data-card-key="tv:2"]')->assertSeeIn('[data-filter-chip="tv-shows"]', '1');
    $this->assertDatabaseCount('user_episodes', 2);
    expect(DB::table('events')->where('name', 'tv_show_marked_watched')->where('properties->source', 'watchlist')->count())->toBe(1);
    expect($page->script('document.activeElement.closest("[data-card-key]")?.dataset.cardKey'))->toBe('tv:3');

    $page->click('[data-sonner-toast]:has-text("TV show marked as watched") [data-action]')
        ->assertSee('Action undone')->assertSee('Watchlist · 3')->assertPresent('[data-card-key="tv:2"]')
        ->assertSeeIn('[data-card-key="tv:2"] [data-slot="tv-state"]', 'In progress · 1/2')->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('user_episodes', 1);
    settleToasts($page);
    $page->assertNoAccessibilityIssues();
})->with(['desktop' => 1440, 'mobile' => 390]);

it('lists a watched TV Show and clears its Episodes from the Watched menu with Undo', function () {
    config(['catalog.image_base_url' => '/test-images/']);
    $user = User::factory()->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 1, 'title' => 'Arrival', 'poster_path' => null]))->watched()->create(['watched_at' => '2026-01-01']);
    $tvShow = listedTvShow($user, 2, 'Chernobyl', '2019-05-06');
    episodeOf($tvShow, $user, '2026-03-02');
    episodeOf($tvShow, $user, '2026-03-01');
    $this->actingAs($user);

    $page = visitWithCsp('/watched');
    $page->assertSee('Watched · 2')->assertSeeIn('[data-card-key="tv:2"] [data-slot="tv-state"]', 'Completed · 2/2')
        ->assertSeeIn('[data-card-key="tv:2"]', 'Watched Mar 2, 2026')
        ->click('[data-card-key="tv:2"] [data-slot="movie-menu"]')->click('Clear Watched')
        ->assertSee('TV show cleared')->assertSee('Watched · 1')->assertNotPresent('[data-card-key="tv:2"]');
    $this->assertDatabaseCount('user_episodes', 0);
    expect(DB::table('events')->where('name', 'tv_show_unmarked_watched')->where('properties->source', 'watched')->count())->toBe(1);
    expect($page->script('document.activeElement.closest("[data-card-key]")?.dataset.cardKey'))->toBe('1');

    $page->click('[data-sonner-toast]:has-text("TV show cleared") [data-action]')
        ->assertSee('Action undone')->assertSee('Watched · 2')->assertPresent('[data-card-key="tv:2"]')->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('user_episodes', 2);
    expect($page->script('document.activeElement.closest("[data-card-key]")?.dataset.cardKey'))->toBe('tv:2');
});
