<?php

use App\Http\Exceptions\ErrorPageException;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\Route;

it('removes and undoes a Removed Movie from each list without losing its date', function (string $list, string $label, string $date, string $action, string $toast, string $column) {
    $facts = UserMovie::factory()->for(Movie::factory()->removed())->create([
        'watchlisted_at' => '2026-10-02 12:00:00', 'watched_at' => '2026-10-01 12:00:00',
    ]);
    $this->actingAs($facts->user);

    $page = visitWithCsp('/'.$list, width: 390);
    $page->assertSee($label.' · 1')->assertSee('No longer available')
        ->assertSee("TMDB has removed these, so we can't show their details.")
        ->assertSee('Removed movie')->assertSee($date)->assertMissing('[data-slot="bookmark-toggle"]')
        ->click('[data-slot="movie-menu"]')->click($action)->assertSee($label.' · 0')
        ->assertDontSee('No longer available')->assertSee($toast);
    $this->assertDatabaseHas('user_titles', ['id' => $facts->id, $column => null]);
    $page->click('Undo')->assertSee($label.' · 1')->assertSee($date)->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('user_titles', ['id' => $facts->id, $column => $list === 'watchlist' ? '2026-10-02 12:00:00' : '2026-10-01 12:00:00']);
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
    $this->assertDatabaseHas('user_titles', ['id' => $facts->id, 'watchlisted_at' => null, 'watched_at' => '2026-10-01 12:00:00']);
    $page->click('Undo')->assertSee('On your Watchlist');
    $page->click('[data-slot="clear-watched"]')->assertDontSee('Watched Oct 1, 2026')->assertSee('Cleared from Watched');
    $this->assertDatabaseHas('user_titles', ['id' => $facts->id, 'watchlisted_at' => '2026-10-02 12:00:00', 'watched_at' => null]);
    $page->click('[data-sonner-toast]:has-text("Cleared from Watched") button:has-text("Undo")')
        ->assertSee('Watched Oct 1, 2026')->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('user_titles', ['id' => $facts->id, 'watchlisted_at' => '2026-10-02 12:00:00', 'watched_at' => '2026-10-01 12:00:00']);
    $page->click('a:has-text("Your Watchlist")')->assertSee('Watchlist · 1')->assertSee('Added Oct 2, 2026');
});

it('restores the last fact on a directly opened 410 page and formats its date in the viewers locale', function () {
    $facts = UserMovie::factory()->for(Movie::factory()->removed())->watched()->create(['watched_at' => '2026-10-01 20:00:00']);
    $this->actingAs($facts->user);

    $page = visitWithCsp($facts->movie->canonicalUrl(), width: 390, locale: 'fr-FR', timezone: 'Pacific/Auckland');
    $page->assertSee('Watched 2 oct. 2026')->click('[data-slot="clear-watched"]')
        ->assertDontSee("It's still in your lists")->assertSee('Cleared from Watched');
    $this->assertDatabaseMissing('user_titles', ['user_id' => $facts->user_id, 'title_id' => $facts->title_id]);
    $page->click('Undo')->assertSee("It's still in your lists")->assertSee('Watched 2 oct. 2026')->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('user_titles', ['user_id' => $facts->user_id, 'title_id' => $facts->title_id, 'watched_at' => '2026-10-01 20:00:00']);
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

it('shows Removed Movie controls only for the MovieRemoved exception class', function () {
    $user = User::factory()->create();
    Route::middleware('web')->get('/another-error', fn () => throw new class(410, 'Another error', 'Another description', data: ['tmdbId' => 1, 'userMovies' => [1 => ['watchlisted' => true, 'watched' => true, 'watchedDate' => '2026-10-01T12:00:00.000000Z']]]) extends ErrorPageException {});
    $this->actingAs($user);

    visitWithCsp('/another-error')->assertSee('Another error')->assertSee('Another description')
        ->assertDontSee("It's still in your lists")->assertMissing('[data-slot="remove-watchlist"]')
        ->assertMissing('[data-slot="clear-watched"]')->assertNoJavaScriptErrors();
});

it('offers Remove and Clear with Undo on a cap-cleared Movie\'s 503 page while its refresh fails', function () {
    $facts = UserMovie::factory()->for(Movie::factory()->capCleared())->create([
        'watchlisted_at' => '2026-10-02 12:00:00', 'watched_at' => '2026-10-01 12:00:00',
    ]);
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    $this->actingAs($facts->user);

    $page = visitWithCsp('/watchlist');
    $page->assertSee('Details temporarily unavailable')->click('[data-slot="poster-card"] > a')
        ->assertSee("We can't reach our movie data source right now.")->assertSee("It's still in your lists")
        ->click('[data-slot="remove-watchlist"]')->assertDontSee('On your Watchlist')->assertSee('Removed from your Watchlist');
    $this->assertDatabaseHas('user_titles', ['id' => $facts->id, 'watchlisted_at' => null, 'watched_at' => '2026-10-01 12:00:00']);
    $page->click('Undo')->assertSee('On your Watchlist')->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('user_titles', ['id' => $facts->id, 'watchlisted_at' => '2026-10-02 12:00:00']);
});

it('shows no list controls on a 503 page that carries no Movie', function () {
    $this->actingAs(User::factory()->create());
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    visitWithCsp('/movies/tmdb/157336')->assertSee('Details temporarily unavailable')
        ->assertDontSee("It's still in your lists")->assertDontSee('Your Watchlist')->assertNoJavaScriptErrors();
});
