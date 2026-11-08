<?php

use App\Models\Episode;
use App\Models\Season;
use App\Models\TvShow;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

test('stores Seasons and Episodes by catalog identity with dates and removal markers', function () {
    $tvShow = TvShow::factory()->create();
    $season = Season::factory()->for($tvShow, 'title')->create(['tmdb_id' => 316060, 'season_number' => 0, 'removed_at' => now()]);
    $episode = Episode::factory()->for($season)->create(['tmdb_id' => 4050464, 'season_number' => 0, 'episode_number' => 1, 'air_date' => null, 'runtime' => null, 'episode_type' => 'finale']);

    expect($tvShow->seasons->sole()->is($season))->toBeTrue();
    expect($tvShow->episodes->sole()->is($episode))->toBeTrue();
    expect($season->episodes->sole()->title->is($tvShow))->toBeTrue();
    expect($season->fresh()->removed_at)->toBeInstanceOf(CarbonImmutable::class);
    expect($episode->fresh())->toMatchArray(['tmdb_id' => 4050464, 'season_number' => 0, 'air_date' => null, 'runtime' => null, 'episode_type' => 'finale']);

    $episode->update(['episode_number' => 2, 'air_date' => '2026-12-01', 'removed_at' => now()]);
    expect($episode->fresh()->air_date->toDateString())->toBe('2026-12-01');
    expect($episode->fresh()->removed_at)->toBeInstanceOf(CarbonImmutable::class);
    expect($episode->fresh()->tmdb_id)->toBe(4050464);
});

test('rejects an Episode attached to a Season belonging to another Title', function () {
    $season = Season::factory()->create();
    $other = TvShow::factory()->create();

    expect(fn () => Episode::factory()->for($season)->create(['title_id' => $other->id]))
        ->toThrow(QueryException::class);
});
