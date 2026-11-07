<?php

use App\Models\Genre;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Support\Facades\RateLimiter;

it('picks from the Watchlist, narrows by genre, and marks the pick as watched with Undo', function (int $width) {
    config(['catalog.image_base_url' => '/test-images/']);
    $user = User::factory()->create();
    $drama = Genre::factory()->create(['id' => 18, 'name' => 'Drama']);
    $comedy = Genre::factory()->create(['id' => 35, 'name' => 'Comedy']);
    foreach ([[1, 'Apple', $drama], [2, 'Banana', $comedy]] as [$tmdbId, $title, $genre]) {
        $movie = Movie::factory()->create(['tmdb_id' => $tmdbId, 'title' => $title, 'slug' => strtolower($title), 'release_date' => '2020-05-01', 'runtime' => 95, 'poster_path' => null]);
        $movie->genres()->attach($genre);
        UserMovie::factory()->for($user)->for($movie)->create();
    }
    UserMovie::factory()->for($user)->for(Movie::factory()->removed()->state(['tmdb_id' => 3]))->create();
    $this->actingAs($user);

    $page = visitWithCsp('/watchlist', width: $width);
    $page->click('[data-slot="pick-for-me"]')->assertPresent('[data-slot="picked-movie"]')
        ->assertSee('Streaming data from JustWatch')->assertSee('1h 35m');
    $first = $page->text('[data-slot="picked-movie"] h3');
    expect($first)->toBeIn(['Apple', 'Banana'])
        ->and($page->text('[role="status"][aria-live="polite"]'))->toBe("Picked: {$first} (2020)");
    $other = $first === 'Apple' ? 'Banana' : 'Apple';
    settleToasts($page);
    $page->assertNoAccessibilityIssues();

    $page->click('Pick again')->assertSeeIn('[data-slot="picked-movie"] h3', $other)
        ->click('[data-slot="picker-genre"][data-genre-id="35"]')
        ->assertAttribute('[data-slot="picker-genre"][data-genre-id="35"]', 'aria-pressed', 'true')
        ->assertSeeIn('[data-slot="picked-movie"] h3', 'Banana')->assertSee('This is the only match')
        ->assertAttribute('[data-slot="pick-again"]', 'disabled', '')
        ->keys('[data-slot="pick-again"]', 'Escape')->assertNotPresent('[role="dialog"]');
    expect($page->script('document.activeElement.dataset.slot'))->toBe('pick-for-me');

    $page->click('[data-slot="pick-for-me"]')->assertPresent('[data-slot="picked-movie"]')
        ->assertAttribute('[data-slot="picker-genre"][data-genre-id="35"]', 'aria-pressed', 'false');
    $picked = $page->text('[data-slot="picked-movie"] h3');
    $pickedId = $picked === 'Apple' ? 1 : 2;
    $page->click('[data-slot="picker-mark-as-watched"]')->assertNotPresent('[role="dialog"]')
        ->assertSee('Marked as watched')->assertSee('Watchlist · 2')
        ->assertNotPresent("[data-slot=\"watchlist-grid\"] [data-tmdb-id=\"{$pickedId}\"]");
    expect($page->script('document.activeElement.dataset.slot'))->toBe('pick-for-me');
    $page
        ->click('Undo')->assertPresent("[data-slot=\"watchlist-grid\"] [data-tmdb-id=\"{$pickedId}\"]")
        ->assertSee('Watchlist · 3')->assertNotPresent('[role="dialog"]')->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => Movie::where('tmdb_id', $pickedId)->value('id'), 'watched_at' => null]);

    $page->click('[data-tmdb-id="1"] [data-slot="movie-menu"]')->click('Mark as watched')->assertSee('Watchlist · 2')
        ->click('[data-slot="pick-for-me"]')->assertSeeIn('[data-slot="picked-movie"] h3', 'Banana')
        ->click('[data-slot="picker-mark-as-watched"]')->assertNotPresent('[role="dialog"]')
        ->assertSee('Watchlist · 1')->assertNotPresent('[data-slot="pick-for-me"]')->assertNoJavaScriptErrors();
    expect($page->script('document.activeElement.id'))->toBe('watchlist-heading');
})->with(['desktop' => 1440, 'mobile' => 390]);

it('retries a failed first pick, keeps the shown pick and its genres on a 429, and opens View details with the picker referrer', function () {
    config(['catalog.image_base_url' => '/test-images/']);
    $user = User::factory()->create();
    $drama = Genre::factory()->create(['id' => 18, 'name' => 'Drama']);
    foreach (['Apple', 'Banana'] as $title) {
        $movie = Movie::factory()->create(['slug' => strtolower($title), 'title' => $title, 'poster_path' => null]);
        $movie->genres()->attach($drama);
        UserMovie::factory()->for($user)->for($movie)->create();
    }
    fakeCatalogKnowing(...Movie::all()->all());
    $this->actingAs($user)->freezeTime();
    $key = md5('movie-picker'.'user:'.$user->id);
    $exhaustPicks = function () use ($key): void {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            RateLimiter::hit($key);
        }
    };
    $exhaustPicks();

    $page = visitWithCsp('/watchlist');
    $page->click('[data-slot="pick-for-me"]')->assertSee("You're going too fast. Try again in a moment.")
        ->assertNotPresent('[data-slot="picked-movie"]');
    RateLimiter::clear($key);
    $page->click('[data-slot="picker-retry"]')->assertPresent('[data-slot="picked-movie"]')
        ->assertDontSee("You're going too fast. Try again in a moment.");
    $picked = $page->text('[data-slot="picked-movie"] h3');
    $exhaustPicks();

    $page->click('[data-slot="picker-genre"][data-genre-id="18"]')->assertSee("You're going too fast. Try again in a moment.")
        ->assertAttribute('[data-slot="picker-genre"][data-genre-id="18"]', 'aria-pressed', 'false')
        ->click('Pick again')->assertSee("You're going too fast. Try again in a moment.")
        ->assertSeeIn('[data-slot="picked-movie"] h3', $picked)->assertNoJavaScriptErrors();
    expect($page->text('[role="status"][aria-live="polite"]'))->toBe("You're going too fast. Try again in a moment.");

    $page->click('View details')->assertPathBeginsWith('/movies/'.strtolower($picked).'-')->assertNoJavaScriptErrors();
    expect($page->url())->toEndWith('referrer=picker');
});
