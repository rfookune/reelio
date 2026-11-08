<?php

use App\Jobs\BuildDiscover;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Catalog\Sources\Details\MovieDetails;

test('the job rebuilds Discover with every Movie\'s details', function () {
    fakeDiscoverSource(['trending' => [movieListings(1, 10)]], [new MovieDetails(tmdbId: 2, runtime: 95)]);

    BuildDiscover::dispatchSync();

    expect(app(DiscoverBuilder::class)->discover()->sections[0]->movies[1]->runtime)->toBe(95);
});
