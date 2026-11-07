<?php

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Enums\CatalogProfile;
use Illuminate\Support\Facades\RateLimiter;

it('removes cards with Undo, moves focus to the next and previous controls, and focuses the empty heading', function (int $width) {
    config(['catalog.image_base_url' => '/test-images/']);
    $user = User::factory()->create();
    foreach (['Apple', 'Banana', 'Cherry'] as $index => $title) {
        UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $index + 1, 'title' => $title, 'poster_path' => null]))->create(['watched_at' => '2026-03-12']);
    }
    $this->actingAs($user);

    $page = visitWithCsp('/watchlist?sort=title', width: $width);

    $page->assertSee('Watchlist · 3')->assertSee('Last watched Mar 12, 2026')
        ->click('[data-tmdb-id="2"] [data-slot="bookmark-toggle"]')
        ->assertNotPresent('[data-slot="watchlist-grid"] [data-tmdb-id="2"]')
        ->assertSee('Watchlist · 2')->assertSee('Removed from your Watchlist');
    expect($page->script('document.activeElement.closest("[data-tmdb-id]").dataset.tmdbId'))->toBe('3');
    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => Movie::where('tmdb_id', 2)->value('id'), 'watchlisted_at' => null]);

    $page->click('Undo')->assertPresent('[data-slot="watchlist-grid"] [data-tmdb-id="2"]')->assertSee('Watchlist · 3');
    expect($page->script('document.activeElement.closest("[data-tmdb-id]").dataset.tmdbId'))->toBe('2');

    $page->click('[data-tmdb-id="3"] [data-slot="movie-menu"]')->click('Mark as watched')
        ->assertNotPresent('[data-slot="watchlist-grid"] [data-tmdb-id="3"]')->assertSee('Watchlist · 2');
    expect($page->script('document.activeElement.dataset.slot'))->toBe('movie-menu');
    expect($page->script('document.activeElement.closest("[data-tmdb-id]").dataset.tmdbId'))->toBe('2');
    $page->click('[data-tmdb-id="2"] [data-slot="bookmark-toggle"]')->assertSee('Watchlist · 1')
        ->click('[data-tmdb-id="1"] [data-slot="bookmark-toggle"]')->assertSee('Your Watchlist is empty')
        ->assertSee('Tap the bookmark on any movie to save it here.')->assertSee('Watchlist · 0')
        ->assertNoJavaScriptErrors();
    expect($page->script('document.activeElement.id'))->toBe('watchlist-empty-heading');
    settleToasts($page);
    $page->assertNoAccessibilityIssues();
})->with(['desktop' => 1440, 'mobile' => 390]);

it('loads all Movies after removals and restores the sort, loaded pages and scroll on Back', function () {
    config(['catalog.image_base_url' => '/test-images/']);
    $user = User::factory()->create();
    foreach (range(1, 50) as $number) {
        $movie = Movie::factory()->create(['tmdb_id' => $number, 'title' => sprintf('Movie %02d', $number), 'slug' => sprintf('movie-%02d', $number), 'poster_path' => null]);
        UserMovie::factory()->for($user)->for($movie)->create();
    }
    $last = Movie::where('tmdb_id', 50)->first();
    fakeCatalogKnowing($last);
    $this->actingAs($user);

    $page = visitWithCsp('/watchlist');
    $page->select('#watchlist-sort', 'title')->assertSee('Movie 01');
    expect($page->url())->toEndWith('/watchlist?sort=title');
    expect($page->script('document.querySelectorAll("[data-slot=watchlist-grid] [data-slot=poster-card]").length'))->toBe(48);
    $page->click('[data-tmdb-id="1"] [data-slot="bookmark-toggle"]')->assertSee('Watchlist · 49')
        ->click('[data-tmdb-id="48"] [data-slot="bookmark-toggle"]')->assertSee('Watchlist · 48')
        ->click('Show more')->assertSee('Movie 50')->assertDontSee('Show more');
    expect($page->script('document.querySelectorAll("[data-slot=watchlist-grid] [data-slot=poster-card]").length'))->toBe(48);
    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->script(<<<'JS'
        () => new Promise((resolve) => {
            const started = performance.now();
            const check = () => history.state?.documentScrollPosition?.top === window.scrollY || performance.now() - started > 1500
                ? resolve(true) : requestAnimationFrame(check);
            check();
        })
        JS);
    $scrolledTo = $page->script('window.scrollY');
    $page->click('[data-tmdb-id="50"] a:not([aria-hidden])')->assertPathBeginsWith('/movies/movie-50-')->back()->assertSee('Movie 50')->assertNoJavaScriptErrors();
    $page->script(<<<'JS'
        () => new Promise((resolve) => {
            const started = performance.now();
            const check = () => window.scrollY > 0 || performance.now() - started > 1500
                ? resolve(true) : requestAnimationFrame(check);
            check();
        })
        JS);
    expect($page->value('#watchlist-sort'))->toBe('title');
    expect($page->script('document.querySelectorAll("[data-slot=watchlist-grid] [data-slot=poster-card]").length'))->toBe(48);
    expect($page->script('window.scrollY'))->toBe($scrolledTo);
    $page->select('#watchlist-sort', 'recently-added')->assertQueryStringMissing('sort')->assertNoJavaScriptErrors();
    expect($page->url())->toEndWith('/watchlist');
});

it('saves a cached Trending suggestion from the empty state and returns it with Undo', function () {
    config(['catalog.image_base_url' => '/test-images/']);
    fakeDiscoverSource(['trending' => [[movieListing(901)]]]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);
    $this->actingAs(User::factory()->create());

    $page = visitWithCsp('/watchlist');
    $page->assertSee('Your Watchlist is empty')->assertSee('Trending this week')
        ->click('[data-tmdb-id="901"] [data-slot="bookmark-toggle"]')
        ->assertSee('Watchlist · 1')->assertPresent('[data-slot="watchlist-grid"] [data-tmdb-id="901"]')
        ->assertDontSee('Your Watchlist is empty')->assertSee('Added to your Watchlist')
        ->click('Undo')->assertSee('Your Watchlist is empty')->assertSee('Watchlist · 0')->assertSee('Trending this week')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('user_movies', 0);
});

it('rolls a refused removal back into the grid and restores the count', function () {
    config(['catalog.image_base_url' => '/test-images/']);
    $facts = UserMovie::factory()->for(Movie::factory()->state(['poster_path' => null]))->create();
    $this->actingAs($facts->user)->freezeTime();
    $key = md5('user-movie-actions'.'user:'.$facts->user_id);
    for ($attempt = 0; $attempt < 60; $attempt++) {
        RateLimiter::hit($key);
    }

    $page = visitWithCsp('/watchlist');
    $page->click('[data-slot="bookmark-toggle"]')->assertSee("Couldn't save that. Try again.")
        ->assertPresent('[data-slot="watchlist-grid"] [data-slot="poster-card"]')->assertSee('Watchlist · 1')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('user_movies', ['id' => $facts->id, 'watchlisted_at' => $facts->watchlisted_at]);
});

it('restores and focuses an undone Movie after the sort changes, even beyond the first page', function () {
    config(['catalog.image_base_url' => '/test-images/']);
    $user = User::factory()->create();
    foreach (range(1, 50) as $number) {
        UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $number, 'title' => sprintf('Movie %02d', $number), 'poster_path' => null]))->create(['watchlisted_at' => '2026-01-01']);
    }
    $this->actingAs($user);

    $page = visitWithCsp('/watchlist?sort=title');
    $page->click('[data-tmdb-id="1"] [data-slot="bookmark-toggle"]')->assertSee('Watchlist · 49')
        ->select('#watchlist-sort', 'recently-added')->assertQueryStringMissing('sort')
        ->click('Undo')->assertSee('Watchlist · 50')
        ->assertPresent('[data-slot="watchlist-grid"] [data-tmdb-id="1"]')->assertNoJavaScriptErrors();

    expect($page->script(<<<'JS'
        () => new Promise((resolve) => {
            const started = performance.now();
            const check = () => document.activeElement.closest('[data-tmdb-id]')?.dataset.tmdbId === '1' || performance.now() - started > 1500
                ? resolve(document.activeElement.dataset.slot) : requestAnimationFrame(check);
            check();
        })
        JS))->toBe('bookmark-toggle');
    expect($page->script('document.activeElement.closest("[data-tmdb-id]").dataset.tmdbId'))->toBe('1');
});

it('keeps focus on the Watchlist heading when the last loaded card leaves a later page', function () {
    config(['catalog.image_base_url' => '/test-images/']);
    $user = User::factory()->create();
    foreach (range(1, 49) as $number) {
        UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $number, 'title' => sprintf('Movie %02d', $number), 'poster_path' => null]))->create();
    }
    $this->actingAs($user);
    $response = $this->get('/watchlist?sort=title');
    $query = parse_url($response->viewData('page')['props']['movies']['next_page_url'], PHP_URL_QUERY);

    $page = visitWithCsp('/watchlist?'.$query);
    $page->click('[data-slot="bookmark-toggle"]')->assertSee('Watchlist · 48')->assertNoJavaScriptErrors();

    expect($page->script('document.activeElement.id'))->toBe('watchlist-heading');
});
