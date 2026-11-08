<?php

use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Sources\Tmdb\TmdbClient;
use App\Services\Catalog\Sources\Tmdb\TvShowParser;
use Illuminate\Support\Facades\Http;

/**
 * Opt in with RUN_LIVE_TESTS=1 and --group=live. Only this stable TV Show's three endpoints can use the network;
 * normal CI still prevents every stray HTTP request. The Season request asserts that TMDB honours the external_ids and watch/providers appends, so a failure means Season-level appends stopped working.
 */
test('live TV details, one Season and watch providers support Season-level external_ids and watch/providers appends', function () {
    expect(config('catalog.tmdb.token'))->not->toBeEmpty('TMDB_READ_TOKEN is required for the opt-in live check.');
    Http::allowStrayRequests([
        'https://api.themoviedb.org/3/tv/456?*',
        'https://api.themoviedb.org/3/tv/456/season/1?*',
        'https://api.themoviedb.org/3/tv/456/watch/providers?*',
    ]);
    $client = app(TmdbClient::class);
    $context = new CatalogContext('en-US', CatalogProfile::Background);
    $parser = app(TvShowParser::class);

    $details = $client->tvDetails(456, $context, deadline: null);
    expect($details)->not->toBeNull();
    $data = $parser->details((array) $details->json(), 456);
    expect($data)->toMatchArray(['id' => 456, 'name' => 'The Simpsons']);
    expect($data['number_of_seasons'])->toBeGreaterThan(1);
    expect($data)->toHaveKeys(['external_ids', 'content_ratings', 'videos', 'credits', 'watch/providers']);
    $summary = collect($data['seasons'])->firstWhere('season_number', 1);
    expect($summary)->not->toBeNull();

    $season = $client->get('tv/456/season/1', [
        'language' => 'en-US', 'append_to_response' => 'external_ids,watch/providers',
    ], $context);
    expect($season)->not->toBeNull();
    expect($season->json('external_ids'))->toBeArray()->toHaveKey('id');
    expect($season->json('watch/providers'))->toBeArray()->toHaveKey('results');
    $parsedSeason = $parser->season((array) $season->json(), $summary);
    expect($parsedSeason->episodes)->not->toBeEmpty();
    expect($parsedSeason->number)->toBe(1);

    $providers = $client->get('tv/456/watch/providers', ['language' => 'en-US'], $context);
    expect($providers)->not->toBeNull();
    expect($providers->json())->toHaveKeys(['id', 'results']);
    expect($providers->json('id'))->toBe(456);
    expect($providers->json('results'))->toBeArray();
})->group('live')->skip(fn (): bool => ! filter_var(getenv('RUN_LIVE_TESTS'), FILTER_VALIDATE_BOOL), 'Opt in with RUN_LIVE_TESTS=1 and a TMDB_READ_TOKEN.');
