<?php

use App\Models\Movie;
use App\Models\User;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\RateLimiter;

it('adds a Movie to the Watchlist from its page and takes it off again', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['tmdb_id' => 157336, 'title' => 'Interstellar']);
    fakeCatalogKnowing($movie);
    $this->actingAs($user);

    $page = visitWithCsp($movie->canonicalUrl());
    $page->script(<<<'JS'
        () => {
            const setRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
            window.partialData = [];
            XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
                if (name.toLowerCase() === 'x-inertia-partial-data') {
                    window.partialData.push(value);
                }

                return setRequestHeader.call(this, name, value);
            };
        }
        JS);

    $page->assertSeeIn('[data-slot="watchlist-toggle"]', 'Add to Watchlist')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'false')
        ->click('[data-slot="watchlist-toggle"]')
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'On your Watchlist')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'true')
        ->assertSee('Added to your Watchlist');

    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => $movie->id]);

    $page->click('[data-slot="watchlist-toggle"]')
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'Add to Watchlist')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'false')
        ->assertSee('Removed from your Watchlist')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('user_movies', 0);
    expect($page->script('document.activeElement.dataset.slot'))->toBe('watchlist-toggle')
        ->and($page->script('window.partialData'))->toBe(['userMovies', 'userMovies']);

    settleToasts($page);
    $page->assertNoAccessibilityIssues();
});

it('rolls the toggle back and says so when saving fails', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['tmdb_id' => 157336, 'title' => 'Interstellar']);
    fakeCatalogKnowing($movie);
    $this->actingAs($user);

    $page = visitWithCsp($movie->canonicalUrl());
    $page->assertSeeIn('[data-slot="watchlist-toggle"]', 'Add to Watchlist');

    /*
     * With the row gone and the source down, the save has to create the Movie and gets a 503. The in-process
     * server answers before the browser paints, so requests are held back briefly to let the optimistic state show.
     */
    $movie->delete();
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    $page->script(<<<'JS'
        () => {
            const send = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.send = function (...args) {
                setTimeout(() => send.apply(this, args), 300);
            };
            const toggle = document.querySelector('[data-slot="watchlist-toggle"]');
            window.pressedStates = [];
            new MutationObserver(() => window.pressedStates.push(toggle.getAttribute('aria-pressed')))
                .observe(toggle, { attributeFilter: ['aria-pressed'] });
        }
        JS);

    $page->click('[data-slot="watchlist-toggle"]')
        ->assertSee("Couldn't save that. Try again.")
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'Add to Watchlist')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'false')
        ->assertDontSee('Added to your Watchlist')
        ->assertPathIs(parse_url($movie->canonicalUrl(), PHP_URL_PATH))
        ->assertNoJavaScriptErrors();

    expect($page->script('window.pressedStates'))->toBe(['true', 'false']);
    $this->assertDatabaseCount('user_movies', 0);
});

it('rolls back a throttled action and shows the 429 toast', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    fakeCatalogKnowing($movie);
    $this->actingAs($user)->freezeTime();

    $page = visitWithCsp($movie->canonicalUrl());
    for ($attempt = 0; $attempt < 60; $attempt++) {
        RateLimiter::hit(md5('user-movie-actions'.'user:'.$user->id));
    }

    $page->click('[data-slot="watchlist-toggle"]')
        ->assertSeeIn('[data-sonner-toast]', "You're going too fast. Try again in a moment.")
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'Add to Watchlist')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'false')
        ->assertDontSee('Too many requests')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('user_movies', 0);
});

it('keeps guests on the Movie page with a toast when a pending action is throttled', function () {
    $movie = Movie::factory()->create();
    fakeCatalogKnowing($movie);
    $this->freezeTime();
    $page = visitWithCsp($movie->canonicalUrl());
    for ($attempt = 0; $attempt < 60; $attempt++) {
        RateLimiter::hit(md5('user-movie-actions'.'ip:127.0.0.1'));
    }

    $page->click('[data-slot="watchlist-toggle"]')
        ->assertSeeIn('[data-sonner-toast]', "You're going too fast. Try again in a moment.")
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'Add to Watchlist')
        ->assertPathIs(parse_url($movie->canonicalUrl(), PHP_URL_PATH))
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('user_movies', 0);
});

it('sends a guest who uses the toggle to sign in, then saves the Movie and offers Undo back on its page', function () {
    $movie = Movie::factory()->create(['tmdb_id' => 157336, 'title' => 'Interstellar']);
    fakeCatalogKnowing($movie);
    User::factory()->create(['email' => 'alex@example.com']);

    $page = visitWithCsp($movie->canonicalUrl());

    $page->click('[data-slot="watchlist-toggle"]')
        ->assertPathIs('/login')
        ->assertSee('Log in to your account')
        ->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('user_movies', 0);

    pasteInto($page, '#email', 'alex@example.com');
    pasteInto($page, '#password', 'password');

    $page->click('@login-button')
        ->assertPathIs(parse_url($movie->fresh()->canonicalUrl(), PHP_URL_PATH))
        ->assertSeeIn('[data-sonner-toast]', 'Added to your Watchlist')
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'On your Watchlist')
        ->click('[data-sonner-toast] [data-action]')
        ->assertSeeIn('[data-slot="watchlist-toggle"]', 'Add to Watchlist')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseCount('user_movies', 0);
});
