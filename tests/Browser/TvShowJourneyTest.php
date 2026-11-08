<?php

use App\Enums\TitleKind;
use App\Models\TvShow;
use App\Models\User;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\EpisodeDetails;
use App\Services\Catalog\Sources\Details\SeasonDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;

it('finds a TV Show, saves it, watches Episodes and the rest, then restores its Watchlist and progress with Undo', function (int $width, int $height) {
    $this->travelTo('2026-11-08 12:00:00');
    config(['catalog.image_base_url' => '/test-images/']);
    $user = User::factory()->create();
    app()->instance(CatalogSource::class, new FakeCatalogSource(
        searches: ['chernobyl' => [[movieListing(87108, title: 'Chernobyl', kind: TitleKind::TvShow, posterPath: null)]]],
        tvShows: [new TvShowDetails(
            87108, title: 'Chernobyl', firstAirDate: '2019-05-06', status: 'Ended',
            seasons: [new SeasonDetails(100, 1, episodes: [
                new EpisodeDetails(200, 1, 1, name: '1:23:45', airDate: '2019-05-06'),
                new EpisodeDetails(201, 1, 2, name: 'Please Remain Calm', airDate: '2019-05-13'),
                new EpisodeDetails(202, 1, 3, name: 'Open Wide, O Earth', airDate: '2019-05-20'),
            ])],
        )],
    ));
    $this->actingAs($user);

    $page = visitWithCsp('/search', $width, $height);
    $page->type('#search-query', 'chernobyl')
        ->assertSeeIn('[data-slot="search-results"] [data-slot="poster-card"] > a:not([aria-hidden])', 'Chernobyl')
        ->click('[data-slot="search-results"] [data-slot="poster-card"] > a:not([aria-hidden])')
        ->assertPathBeginsWith('/tv-shows/chernobyl-')->assertSee('Not started')
        ->click('[data-slot="watchlist-toggle"]')->assertSee('Added to your Watchlist')
        ->click('[data-slot="episode-toggle"][aria-label^="Episode 1:"]')->assertSee('1 of 3 Episodes watched')
        ->click('[data-slot="episode-toggle"][aria-label^="Episode 2:"]')->assertSee('2 of 3 Episodes watched')
        ->assertSee('In progress');
    $tvShow = TvShow::query()->where('tmdb_id', 87108)->sole();
    $this->assertDatabaseCount('user_episodes', 2);
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => '2026-11-08 12:00:00']);

    $page->click('nav[aria-label="Primary"] a[href$="/watchlist"]:visible')->assertSee('Watchlist · 1')
        ->assertSeeIn('[data-card-key="tv:87108"] [data-slot="tv-state"]', 'In progress · 2/3')
        ->click('[data-card-key="tv:87108"] [data-slot="movie-menu"]')->click('Mark as watched')
        ->assertSee('This marks all 3 aired Episodes as watched.')
        ->click('[data-slot="tv-show-mark-confirm"]')->assertSee('TV show marked as watched')
        ->assertSee('Watchlist · 0')->assertNotPresent('[data-card-key="tv:87108"]')
        ->click('main nav a[href$="/watched"]')->assertSee('Watched · 1')
        ->assertSeeIn('[data-card-key="tv:87108"] [data-slot="tv-state"]', 'Completed · 3/3');
    $this->assertDatabaseCount('user_episodes', 3);
    $this->assertDatabaseMissing('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id]);

    $page->click('[data-sonner-toast]:has-text("TV show marked as watched") [data-action]')
        ->assertSee('Action undone')
        ->assertSeeIn('[data-card-key="tv:87108"] [data-slot="tv-state"]', 'In progress · 2/3')
        ->click('main nav a[href$="/watchlist"]')->assertSee('Watchlist · 1')
        ->assertSeeIn('[data-card-key="tv:87108"] [data-slot="tv-state"]', 'In progress · 2/3');
    $this->assertDatabaseCount('user_episodes', 2);
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $tvShow->id, 'watchlisted_at' => '2026-11-08 12:00:00']);
    settleToasts($page);
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
})->with(['desktop' => [1440, 900], 'mobile' => [390, 844]]);
