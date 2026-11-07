<?php

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;

it('removes and undoes a Removed Movie from each list without losing its date', function (string $list, string $label, string $date, string $action, string $toast, string $column) {
    $facts = UserMovie::factory()->for(Movie::factory()->removed())->create([
        'watchlisted_at' => '2026-10-02 12:00:00', 'watched_at' => '2026-10-01 12:00:00',
    ]);
    $this->actingAs($facts->user);

    $page = visitWithCsp('/'.$list, width: 390);
    $page->assertSee($label.' · 1')->assertSee('No longer available')
        ->assertSee("TMDB has removed these movies, so we can't show their details.")
        ->assertSee('Removed movie')->assertSee($date)->assertMissing('[data-slot="bookmark-toggle"]')
        ->click('[data-slot="movie-menu"]')->click($action)->assertSee($label.' · 0')
        ->assertDontSee('No longer available')->assertSee($toast);
    $this->assertDatabaseHas('user_movies', ['id' => $facts->id, $column => null]);
    $page->click('Undo')->assertSee($label.' · 1')->assertSee($date)->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('user_movies', ['id' => $facts->id, $column => $list === 'watchlist' ? '2026-10-02 12:00:00' : '2026-10-01 12:00:00']);
})->with([
    'Watchlist' => ['watchlist', 'Watchlist', 'Added Oct 2, 2026', 'Remove', 'Removed from your Watchlist', 'watchlisted_at'],
    'Watched' => ['watched', 'Watched', 'Watched Oct 1, 2026', 'Clear Watched', 'Cleared from Watched', 'watched_at'],
]);

it('clears and undoes both facts on the 410 page with the normal toasts', function () {
    $facts = UserMovie::factory()->for(Movie::factory()->removed())->create([
        'watchlisted_at' => '2026-10-02 12:00:00', 'watched_at' => '2026-10-01 12:00:00',
    ]);
    $this->actingAs($facts->user);

    $page = visitWithCsp('/watchlist');
    $page->click('[data-slot="poster-card"] > a')->assertSee("It's still in your lists")
        ->assertSee('On your Watchlist')->assertSee('Watched Oct 1, 2026')
        ->click('[data-slot="remove-watchlist"]')->assertDontSee('On your Watchlist')->assertSee('Removed from your Watchlist');
    $this->assertDatabaseHas('user_movies', ['id' => $facts->id, 'watchlisted_at' => null, 'watched_at' => '2026-10-01 12:00:00']);
    $page->click('Undo')->assertSee('On your Watchlist');
    $page->click('[data-slot="clear-watched"]')->assertDontSee('Watched Oct 1, 2026')->assertSee('Cleared from Watched');
    $this->assertDatabaseHas('user_movies', ['id' => $facts->id, 'watchlisted_at' => '2026-10-02 12:00:00', 'watched_at' => null]);
    $page->click('[data-sonner-toast]:has-text("Cleared from Watched") button:has-text("Undo")')
        ->assertSee('Watched Oct 1, 2026')->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('user_movies', ['id' => $facts->id, 'watchlisted_at' => '2026-10-02 12:00:00', 'watched_at' => '2026-10-01 12:00:00']);
    $page->click('a:has-text("Your Watchlist")')->assertSee('Watchlist · 1')->assertSee('Added Oct 2, 2026');
});

it('restores the last fact on a directly opened 410 page and formats its date in the viewers locale', function () {
    $facts = UserMovie::factory()->for(Movie::factory()->removed())->watched()->create(['watched_at' => '2026-10-01 20:00:00']);
    $this->actingAs($facts->user);

    $page = visitWithCsp($facts->movie->canonicalUrl(), width: 390, locale: 'fr-FR', timezone: 'Pacific/Auckland');
    $page->assertSee('Watched 2 oct. 2026')->click('[data-slot="clear-watched"]')
        ->assertDontSee("It's still in your lists")->assertSee('Cleared from Watched');
    $this->assertDatabaseMissing('user_movies', ['user_id' => $facts->user_id, 'movie_id' => $facts->movie_id]);
    $page->click('Undo')->assertSee("It's still in your lists")->assertSee('Watched 2 oct. 2026')->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('user_movies', ['user_id' => $facts->user_id, 'movie_id' => $facts->movie_id, 'watched_at' => '2026-10-01 20:00:00']);
});

it('keeps keyboard focus on the remaining Removed Movie when the last available card leaves', function () {
    config(['catalog.image_base_url' => '/test-images/']);
    $user = User::factory()->create();
    $removed = Movie::factory()->removed()->create(['tmdb_id' => 1]);
    UserMovie::factory()->for($user)->for($removed)->create();
    UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => 2, 'poster_path' => null]))->create();
    $this->actingAs($user);

    $page = visitWithCsp('/watchlist');
    $page->click('[data-tmdb-id="2"] [data-slot="bookmark-toggle"]')->assertSee('Watchlist · 1')->assertNoJavaScriptErrors();

    expect($page->script('document.activeElement.closest("[data-tmdb-id]")?.dataset.tmdbId'))->toBe('1');
    expect($page->script('document.activeElement.dataset.slot'))->toBe('movie-menu');
});
