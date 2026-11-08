<?php

use App\Models\Episode;
use App\Models\TvShow;
use App\Models\User;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\EpisodeDetails;
use App\Services\Catalog\Sources\Details\SeasonDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;

it('optimistically marks an Episode and rolls back a failed save with a message', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108, 'slug' => 'chernobyl']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(
        87108, title: 'Chernobyl', seasons: [new SeasonDetails(100, 1, episodes: [new EpisodeDetails(200, 1, 1, name: '1:23:45', airDate: '2019-05-06')])],
    )]));
    $this->actingAs(User::factory()->create());
    $page = visitWithCsp($tvShow->canonicalUrl());
    $page->assertAttribute('[data-slot="episode-toggle"]', 'aria-pressed', 'false');
    $page->script(<<<'JS'
        () => {
            const send = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.send = function (...args) {
                setTimeout(() => send.apply(this, args), 300);
            };
            const toggle = document.querySelector('[data-slot="episode-toggle"]');
            window.pressedStates = [];
            new MutationObserver(() => window.pressedStates.push(toggle.getAttribute('aria-pressed')))
                .observe(toggle, { attributeFilter: ['aria-pressed'] });
        }
        JS);
    Episode::query()->update(['removed_at' => now()]);
    $page->click('[data-slot="episode-toggle"]')
        ->assertSee("Couldn't save that. Try again.")
        ->assertAttribute('[data-slot="episode-toggle"]', 'aria-pressed', 'false')
        ->assertSee('0 of 1 Episodes watched')->assertNoJavaScriptErrors();
    expect($page->script('window.pressedStates'))->toBe(['true', 'false']);
    $this->assertDatabaseCount('user_episodes', 0);
    Episode::query()->update(['removed_at' => null]);
    $page->click('[data-slot="episode-toggle"]')->assertSee('Episode marked as watched')
        ->assertAttribute('[data-slot="episode-toggle"]', 'aria-pressed', 'true')
        ->assertSee('Completed')->assertSee('1 of 1 Episodes watched')
        ->click('[data-sonner-toast] [data-action]')->assertSee('Action undone')
        ->assertAttribute('[data-slot="episode-toggle"]', 'aria-pressed', 'false')->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('user_episodes', 0);
});

it('replays a guest Episode tap after login and offers Undo on returning to the TV Show', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108, 'slug' => 'chernobyl']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(
        87108, title: 'Chernobyl', seasons: [new SeasonDetails(100, 1, episodes: [new EpisodeDetails(200, 1, 1, name: '1:23:45', airDate: '2019-05-06')])],
    )]));
    User::factory()->create(['email' => 'alex@example.com']);
    $page = visitWithCsp($tvShow->canonicalUrl());
    $page->click('[data-slot="episode-toggle"]')->assertPathIs('/login');
    $this->assertDatabaseCount('user_episodes', 0);
    pasteInto($page, '#email', 'alex@example.com');
    pasteInto($page, '#password', 'password');
    $page->click('@login-button')->assertSee('Episode updated')
        ->assertAttribute('[data-slot="episode-toggle"]', 'aria-pressed', 'true')
        ->click('[data-sonner-toast] [data-action]')->assertSee('Action undone')
        ->assertAttribute('[data-slot="episode-toggle"]', 'aria-pressed', 'false')->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('user_episodes', 0);
});
