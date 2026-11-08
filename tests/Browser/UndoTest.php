<?php

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Support\Facades\RateLimiter;
use Pest\Browser\Api\AwaitableWebpage;

/**
 * The focused element's `data-slot` once focus has settled on the page outside the toaster, as it does a frame or so
 * after Undo renders the restored control, waiting up to a second and a half.
 */
function focusedSlotAfterUndo(AwaitableWebpage $page): ?string
{
    return $page->script(<<<'JS'
        () => new Promise((resolve) => {
            const started = performance.now();
            const settled = () => document.activeElement !== document.body && document.activeElement?.closest('[data-sonner-toaster]') === null;
            const settle = () => settled() || performance.now() - started > 1500
                ? resolve(document.activeElement?.dataset.slot ?? null)
                : requestAnimationFrame(settle);
            settle();
        })
        JS);
}

it('undoes marking as watched from the toast, restoring the Watchlist and focus on the restored control', function () {
    $this->travelTo('2026-09-10 12:00:00');
    $facts = UserMovie::factory()->create(['watchlisted_at' => '2026-09-01 08:00:00']);
    $movie = $facts->movie;
    fakeCatalogKnowing($movie);
    $this->actingAs($facts->user);

    $page = visitWithCsp($movie->canonicalUrl());

    $page->click('[data-slot="mark-as-watched"]')
        ->assertSeeIn('[data-slot="watched-chip"]', 'Watched 10 Sep 2026')
        ->assertSeeIn('[data-sonner-toast]', 'Marked as watched')
        ->click('[data-sonner-toast] [data-action]')
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'On your Watchlist')
        ->assertPresent('[data-slot="mark-as-watched"]')
        ->assertNotPresent('[data-slot="watched-chip"]')
        ->assertNoJavaScriptErrors();

    expect(focusedSlotAfterUndo($page))->toBe('mark-as-watched');
    $this->assertDatabaseHas('user_movies', ['id' => $facts->id, 'watchlisted_at' => '2026-09-01 08:00:00', 'watched_at' => null]);
    settleToasts($page);
    $page->assertNoAccessibilityIssues();
});

it('reaches Undo by keyboard and restores the previous Watched date of a rewatch', function () {
    $this->travelTo('2026-09-10 12:00:00');
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    UserMovie::factory()->for($user)->for($movie)->create(['watchlisted_at' => '2026-09-01 08:00:00', 'watched_at' => '2026-08-31 20:00:00']);
    fakeCatalogKnowing($movie);
    $this->actingAs($user);

    $page = visitWithCsp($movie->canonicalUrl());

    $page->keys('[data-slot="clear-last-watched"]', 'Enter')
        ->assertNotPresent('[data-slot="last-watched"]')
        ->assertSeeIn('[data-sonner-toast]', 'Cleared from Watched')
        ->keys('*:focus', 'Alt+KeyT');

    expect($page->script('document.activeElement.textContent'))->toBe('Undo');

    $page->keys('*:focus', 'Escape');
    expect($page->script('document.activeElement.dataset.slot'))->toBe('mark-as-watched');

    $page->keys('*:focus', ['Alt+KeyT', 'Enter'])
        ->assertSeeIn('[data-slot="last-watched"]', 'Last watched 31 Aug 2026 · Clear')
        ->assertNoJavaScriptErrors();

    expect(focusedSlotAfterUndo($page))->toBe('clear-last-watched');
    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => $movie->id, 'watchlisted_at' => '2026-09-01 08:00:00', 'watched_at' => '2026-08-31 20:00:00']);
});

it('says so when the action can no longer be undone, then shows the facts as they stand, with focus on the actions', function () {
    $this->travelTo('2026-09-10 12:00:00');
    $facts = UserMovie::factory()->create(['watchlisted_at' => '2026-09-01 08:00:00']);
    $movie = $facts->movie;
    fakeCatalogKnowing($movie);
    $this->actingAs($facts->user);

    $page = visitWithCsp($movie->canonicalUrl());

    $page->click('[data-slot="mark-as-watched"]')
        ->assertSeeIn('[data-sonner-toast]', 'Marked as watched');

    $facts->refresh()->update(['watchlisted_at' => '2026-09-10 12:00:30']);

    $page->click('[data-sonner-toast] [data-action]')
        ->assertSee("That can't be undone any more")
        ->assertSeeIn('[data-slot="last-watched"]', 'Last watched 10 Sep 2026 · Clear')
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'On your Watchlist')
        ->assertNoJavaScriptErrors();

    expect(focusedSlotAfterUndo($page))->toBe('mark-as-watched');
    $this->assertDatabaseHas('user_movies', ['id' => $facts->id, 'watchlisted_at' => '2026-09-10 12:00:30', 'watched_at' => '2026-09-10 12:00:00']);
});

it('rolls back a throttled Undo and shows the 429 toast', function () {
    $facts = UserMovie::factory()->create();
    fakeCatalogKnowing($facts->movie);
    $this->actingAs($facts->user)->freezeTime();
    $page = visitWithCsp($facts->movie->canonicalUrl());
    $page->click('[data-slot="mark-as-watched"]')->assertSee('Marked as watched');
    for ($attempt = 0; $attempt < 60; $attempt++) {
        RateLimiter::hit(md5('user-movie-actions'.'user:'.$facts->user_id));
    }

    $page->click('[data-sonner-toast] [data-action]')
        ->assertSeeIn('[data-sonner-toast]', "You're going too fast. Try again in a moment.")
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'Add to Watchlist')
        ->assertPresent('[data-slot="watched-chip"]')
        ->assertDontSee('Too many requests')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('user_movies', ['id' => $facts->id, 'watchlisted_at' => null, 'watched_at' => now()]);
});
