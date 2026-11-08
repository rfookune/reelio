<?php

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Season;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserEpisode;
use App\Models\UserMovie;
use App\Models\UserTitle;

it('tidies a Removed TV Show from its page and lists with Undo', function () {
    $show = TvShow::factory()->removed()->create();
    $user = User::factory()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $show->id, 'watchlisted_at' => '2026-01-01']);
    $episode = Episode::factory()->create(['season_id' => Season::factory()->create(['title_id' => $show->id])->id]);
    UserEpisode::query()->create(['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-02-01']);
    $this->actingAs($user);

    $page = visitWithCsp('/watchlist', width: 390);
    $page->assertSee('Watchlist · 1')->assertSee('Removed TV show')
        ->assertSee("TMDB has removed these, so we can't show their details.")
        ->assertMissing('[data-slot="tv-progress"]')->assertMissing('[data-slot="tv-state"]')
        ->click('[data-slot="movie-menu"]')->click('Remove')->assertSee('Watchlist · 0');
    $this->assertDatabaseMissing('user_titles', ['user_id' => $user->id, 'title_id' => $show->id]);
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-02-01']);
    $page->click('Undo')->assertSee('Watchlist · 1')->assertSee('Added Jan 1, 2026')
        ->click('[data-slot="poster-card"] > a')->assertSee('This TV show is no longer available.')
        ->assertSee('You marked 1 Episodes as watched')->click('[data-slot="clear-watched"]')
        ->assertDontSee('You marked 1 Episodes as watched');
    $this->assertDatabaseMissing('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id]);
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $show->id, 'watchlisted_at' => '2026-01-01']);
    $page->click('Undo')->assertSee('You marked 1 Episodes as watched')
        ->click('[data-slot="remove-watchlist"]')->assertDontSee('On your Watchlist');
    $page->click('[data-sonner-toast]:has-text("Removed from your Watchlist") button:has-text("Undo")')->assertSee('On your Watchlist')
        ->assertNoJavaScriptErrors();
    $page->navigate('/watched')->assertSee('Last watched Feb 1, 2026')->click('[data-slot="movie-menu"]')->click('Clear Watched')
        ->assertSee('Watched · 0')->click('[data-sonner-toast]:has-text("TV show cleared") button:has-text("Undo")')
        ->assertSee('Watched · 1')->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $show->id, 'watchlisted_at' => '2026-01-01']);
    $this->assertDatabaseHas('user_episodes', ['user_id' => $user->id, 'episode_id' => $episode->id, 'watched_at' => '2026-02-01']);
});

it('reveals an undone Movie beyond the first page when a Removed TV Show shares its catalog id', function () {
    $user = User::factory()->create();
    foreach (range(1, 50) as $number) {
        UserMovie::factory()->for($user)->for(Movie::factory()->state(['tmdb_id' => $number, 'title' => sprintf('Movie %02d', $number), 'poster_path' => null]))->create(['watchlisted_at' => '2026-01-01']);
    }
    $show = TvShow::factory()->removed()->create(['tmdb_id' => 1]);
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $show->id, 'watchlisted_at' => '2026-01-01']);
    $this->actingAs($user);

    $page = visitWithCsp('/watchlist?sort=title');
    $page->click('[data-card-key="1"] [data-slot="bookmark-toggle"]')->assertSee('Watchlist · 50')
        ->select('#watchlist-sort', 'recently-added')->assertQueryStringMissing('sort')
        ->click('Undo')->assertSee('Watchlist · 51')
        ->assertPresent('[data-slot="watchlist-grid"] [data-card-key="1"]')->assertNoJavaScriptErrors();
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => Movie::query()->where('tmdb_id', 1)->sole()->id, 'watchlisted_at' => '2026-01-01']);
});
