<?php

use App\Models\TvShow;
use App\Models\User;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\EpisodeDetails;
use App\Services\Catalog\Sources\Details\SeasonDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;

function watchlistTvShow(): TvShow
{
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108, 'slug' => 'chernobyl']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(
        87108, title: 'Chernobyl', seasons: [new SeasonDetails(100, 1, episodes: [
            new EpisodeDetails(200, 1, 1, name: '1:23:45', airDate: '2019-05-06'),
            new EpisodeDetails(201, 1, 2, name: 'Please Remain Calm', airDate: '2019-05-13'),
        ])],
    )]));

    return $tvShow;
}

it('keeps the header Watchlist toggle in sync with Episode completion and Undo', function () {
    $tvShow = watchlistTvShow();
    $this->actingAs(User::factory()->create());
    $page = visitWithCsp($tvShow->canonicalUrl());
    $page->assertSee('Not started')->click('[data-slot="watchlist-toggle"]')->assertSee('Added to your Watchlist')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'true');
    $page->click('[data-slot="episode-toggle"][aria-label^="Episode 1:"]')->assertSee('In progress')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'true');
    $page->click('[data-slot="episode-toggle"][aria-label^="Episode 2:"]')->assertSeeIn('[data-slot="completed-chip"]', 'Completed')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'false');
    $page->click('[data-slot="watchlist-toggle"]')->assertSee('Added to your Watchlist')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'true');
    $page->click('[data-slot="watchlist-toggle"]')->assertSee('Removed from your Watchlist')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'false')
        ->click('[data-sonner-toast]:has-text("Removed from your Watchlist") [data-action]')->assertSee('Action undone')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'true')->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('user_episodes', 2);
    settleToasts($page);
    $page->assertNoAccessibilityIssues();
});

it('rolls back a failed header Watchlist save with a message', function () {
    $tvShow = watchlistTvShow();
    $this->actingAs(User::factory()->create());
    $page = visitWithCsp($tvShow->canonicalUrl());
    $page->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'false');
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
    $tvShow->update(['removed_at' => now()]);
    $page->click('[data-slot="watchlist-toggle"]')->assertSee("Couldn't save that. Try again.")
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'false')->assertNoJavaScriptErrors();
    expect($page->script('window.pressedStates'))->toBe(['true', 'false']);
    $this->assertDatabaseCount('user_titles', 0);
});

it('replays a guest Watchlist tap and offers Undo after sign-in', function () {
    $tvShow = watchlistTvShow();
    User::factory()->create(['email' => 'alex@example.com']);
    $page = visitWithCsp($tvShow->canonicalUrl());
    $page->click('[data-slot="watchlist-toggle"]')->assertPathIs('/login');
    $this->assertDatabaseCount('user_titles', 0);
    pasteInto($page, '#email', 'alex@example.com');
    pasteInto($page, '#password', 'password');
    $page->click('@login-button')->assertSee('Added to your Watchlist')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'true')
        ->click('[data-sonner-toast] [data-action]')->assertSee('Action undone')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'false')->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('user_titles', 0);
});

it('keeps saved Watchlist membership when returning with the browser Back button', function () {
    $tvShow = watchlistTvShow();
    $this->actingAs(User::factory()->create());
    $page = visitWithCsp($tvShow->canonicalUrl());
    $page->click('[data-slot="watchlist-toggle"]')->assertSee('Added to your Watchlist')
        ->click('footer a[href$="/about"]')->assertPathIs('/about')->back()
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'true')
        ->click('[data-slot="watchlist-toggle"]')->assertSee('Removed from your Watchlist')
        ->assertAttribute('[data-slot="watchlist-toggle"]', 'aria-pressed', 'false')->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('user_titles', 0);
});
