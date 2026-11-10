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

it('marks a Season in bulk with an Undo toast', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108, 'slug' => 'chernobyl']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(
        87108, title: 'Chernobyl', seasons: [new SeasonDetails(100, 1, episodes: [
            new EpisodeDetails(200, 1, 1, name: 'One', airDate: '2019-05-06'),
            new EpisodeDetails(201, 1, 2, name: 'Two', airDate: '2019-05-13'),
        ])],
    )]));
    $this->actingAs(User::factory()->create());
    $page = visitWithCsp($tvShow->canonicalUrl());

    $page->click('[data-slot="season-mark"]')->assertSee('Season marked as watched')
        ->assertSee('2 of 2 Episodes watched')->assertSee('Completed')
        ->click('[data-sonner-toast] [data-action]')->assertSee('Action undone')
        ->assertSee('0 of 2 Episodes watched')->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('user_episodes', 0);
});

it('confirms a whole-TV Show mark in a focus-trapped dialog that Escape and Cancel leave unchanged', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108, 'slug' => 'chernobyl']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(
        87108, title: 'Chernobyl', seasons: [new SeasonDetails(100, 1, episodes: [
            new EpisodeDetails(200, 1, 1, name: 'One', airDate: '2019-05-06'),
            new EpisodeDetails(201, 1, 2, name: 'Two', airDate: '2019-05-13'),
        ])],
    )]));
    $this->actingAs(User::factory()->create());
    $page = visitWithCsp($tvShow->canonicalUrl());

    $page->click('[data-slot="tv-show-mark"]')->assertSee('This marks all 2 aired Episodes as watched.')->wait(0.5);
    expect($page->script('document.activeElement.closest("[role=dialog]") !== null'))->toBeTrue();
    $page->keys('*:focus', ['Tab', 'Tab', 'Tab', 'Tab', 'Tab']);
    expect($page->script('document.activeElement.closest("[role=dialog]") !== null'))->toBeTrue();
    $page->keys('*:focus', 'Escape')->assertDontSee('This marks all 2 aired Episodes');
    expect($page->script('document.activeElement.getAttribute("data-slot")'))->toBe('tv-show-mark');
    $this->assertDatabaseCount('user_episodes', 0);

    $page->click('[data-slot="tv-show-mark"]')->click('[data-slot="tv-show-mark-cancel"]')->assertDontSee('This marks all 2 aired Episodes');
    $this->assertDatabaseCount('user_episodes', 0);

    $page->click('[data-slot="tv-show-mark"]')->click('[data-slot="tv-show-mark-confirm"]')
        ->assertSee('TV show marked as watched')->assertSee('2 of 2 Episodes watched')->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('user_episodes', 2);
});

it('marks the next Episode from the Up next card and moves on to the one after', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108, 'slug' => 'chernobyl']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(
        87108, title: 'Chernobyl', seasons: [new SeasonDetails(100, 1, episodes: [
            new EpisodeDetails(200, 1, 1, name: '1:23:45', airDate: '2019-05-06'),
            new EpisodeDetails(201, 1, 2, name: 'Please Remain Calm', airDate: '2019-05-13'),
        ])],
    )]));
    $this->actingAs(User::factory()->create());

    $page = visitWithCsp($tvShow->canonicalUrl());
    $page->assertSeeIn('[data-slot="up-next"]', 'S1·E1 · 1:23:45')
        ->click('[data-slot="up-next-mark"]')->assertSee('Episode marked as watched')
        ->assertSeeIn('[data-slot="up-next"]', 'S1·E2 · Please Remain Calm')
        ->assertAttribute('[data-slot="episode-toggle"][aria-label^="Episode 1:"]', 'aria-pressed', 'true')
        ->assertSee('1 of 2 Episodes watched')
        ->click('[data-slot="up-next-mark"]')->assertSee('2 of 2 Episodes watched')
        ->assertNotPresent('[data-slot="up-next"]')->assertSeeIn('[data-slot="completed-chip"]', 'Completed')
        ->assertNoJavaScriptErrors();
    $this->assertDatabaseCount('user_episodes', 2);
});

it('opens and closes a Season with Episodes and gives an upcoming Season no toggle', function () {
    config(['catalog.tv_shows.enabled' => true]);
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108, 'slug' => 'chernobyl']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(
        87108, title: 'Chernobyl', seasons: [
            new SeasonDetails(100, 1, episodes: [new EpisodeDetails(200, 1, 1, name: 'One', overview: 'What happens.', airDate: '2019-05-06')]),
            new SeasonDetails(101, 2),
        ],
    )]));
    $this->actingAs(User::factory()->create());

    $page = visitWithCsp($tvShow->canonicalUrl());
    $page->assertSee('What happens.')->assertCount('[data-slot="season-toggle"]', 1)->assertSee('Upcoming')
        ->click('[data-slot="season-toggle"]')->assertDontSee('What happens.')
        ->assertAttribute('[data-slot="season-toggle"]', 'aria-expanded', 'false')
        ->click('[data-slot="season-toggle"]')->assertSee('What happens.')
        ->assertAttribute('[data-slot="season-toggle"]', 'aria-expanded', 'true')->assertNoJavaScriptErrors();
});
