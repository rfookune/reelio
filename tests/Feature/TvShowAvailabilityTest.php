<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\Movie;
use App\Models\TvShow;
use App\Models\User;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\Queue;

/** @return array{serviceId: int, serviceName: string, logoPath: ?string, country: string, offerType: string, displayPriority: int} */
function tvOffer(int $serviceId, string $offerType = 'stream', string $country = 'US', int $priority = 1): array
{
    return ['serviceId' => $serviceId, 'serviceName' => "Service {$serviceId}", 'logoPath' => null, 'country' => $country, 'offerType' => $offerType, 'displayPriority' => $priority];
}

test('the TV Show page displays country-specific Availability and replaces it on refresh', function () {
    $source = new FakeCatalogSource(tvShows: [new TvShowDetails(87108, title: 'Chernobyl', availability: [
        tvOffer(2, 'stream', priority: 9), tvOffer(1, 'stream', priority: 1), tvOffer(3, 'free'), tvOffer(4, 'rent'), tvOffer(5, 'buy'), tvOffer(6, 'stream', 'GB'),
    ])]);
    app()->instance(CatalogSource::class, $source);
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    $url = $tvShow->canonicalUrl();

    $this->get($url)->assertInertia(fn ($page) => $page
        ->where('whereToWatch.country', 'US')->where('whereToWatch.updated', 'Updated today')
        ->where('whereToWatch.outOfDate', false)->has('whereToWatch.groups', 4)
        ->where('whereToWatch.groups.0.services.0.id', 1)->where('whereToWatch.groups.0.services.1.id', 2)
        ->where('whereToWatch.groups.1.caption', 'Free, may include ads'));

    $this->travel(2)->days();
    $source->tvShows = [new TvShowDetails(87108, title: 'Chernobyl', availability: [tvOffer(7, 'buy')])];
    $this->get($url)->assertInertia(fn ($page) => $page->has('whereToWatch.groups', 1)->where('whereToWatch.groups.0.services.0.id', 7));
    expect($tvShow->availabilities()->count())->toBe(1);
});

test('TV Show Availability freshness comes from its sync and survives an outage', function (int $days, bool $failing, bool $outOfDate) {
    $source = new FakeCatalogSource(tvShows: [new TvShowDetails(87108, title: 'Chernobyl', availability: [tvOffer(1)])]);
    app()->instance(CatalogSource::class, $source);
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    config(['catalog.refresh.on_view_after_seconds' => $failing ? 86400 : 30 * 86400]);
    $this->travel($days)->days();
    $source->failing = $failing;

    $this->get($tvShow->canonicalUrl())->assertInertia(fn ($page) => $page
        ->where('whereToWatch.updated', "Updated {$days} days ago")
        ->where('whereToWatch.outOfDate', $outOfDate)->has('whereToWatch.groups', 1));
})->with([[7, false, false], [8, false, true], [2, true, true]]);

test('the TV watch redirect records a signed-in click and excludes indexing', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108]);
    $this->actingAs(User::factory()->create());

    $this->get('/tv-shows/tmdb/87108/watch?country=GB')
        ->assertRedirect('https://www.themoviedb.org/tv/87108/watch?locale=GB')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    Queue::assertPushed(RecordAnalyticsEvent::class, fn ($job) => $job->name === 'availability_link_clicked'
        && $job->properties === ['title_id' => $tvShow->id, 'kind' => 'tv', 'country' => 'GB']);
});

test('a guest can follow the TV watch link from a card without a local row or an event', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(87108, title: 'Chernobyl')]));

    $this->get('/tv-shows/tmdb/87108/watch?country=US')
        ->assertRedirect('https://www.themoviedb.org/tv/87108/watch?locale=US');

    Queue::assertNothingPushed();
});

test('the TV watch redirect rejects invalid countries', function (string $query) {
    $tvShow = TvShow::factory()->create();
    $this->get("/tv-shows/tmdb/{$tvShow->tmdb_id}/watch{$query}")->assertNotFound();
})->with(['', '?country=BR', '?country=us', '?country[]=US', '?country=https://evil.example']);

test('the TV watch redirect rejects Movies unknown ids and the disabled feature', function () {
    $movie = Movie::factory()->create();
    app()->instance(CatalogSource::class, new FakeCatalogSource);
    $this->get("/tv-shows/tmdb/{$movie->tmdb_id}/watch?country=US")->assertNotFound();
    $this->get('/tv-shows/tmdb/999999/watch?country=US')->assertNotFound();
    $tvShow = TvShow::factory()->create();
    config(['catalog.tv_shows.enabled' => false]);
    $this->get("/tv-shows/tmdb/{$tvShow->tmdb_id}/watch?country=US")->assertNotFound();
});

test('a country partial reload uses the saved preference without recording another TV Show view', function () {
    $source = new FakeCatalogSource(tvShows: [new TvShowDetails(87108, title: 'Chernobyl', availability: [
        tvOffer(1), tvOffer(2, 'rent', 'GB'),
    ])]);
    app()->instance(CatalogSource::class, $source);
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    $this->actingAs(User::factory()->create(['preferred_country' => 'GB']));
    $version = $this->get($tvShow->canonicalUrl())->viewData('page')['version'];
    $viewedAt = now()->subHour();
    $tvShow->update(['last_viewed_at' => $viewedAt]);

    $this->get($tvShow->canonicalUrl(), [
        'X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'tv-shows/Show', 'X-Inertia-Partial-Data' => 'whereToWatch',
    ])->assertOk()->assertJsonPath('props.whereToWatch.country', 'GB')
        ->assertJsonCount(1, 'props.whereToWatch.groups')
        ->assertJsonPath('props.whereToWatch.groups.0.services.0.id', 2)->assertJsonMissingPath('props.tvShow');

    expect($tvShow->fresh()->last_viewed_at->toDateTimeString())->toBe($viewedAt->toDateTimeString());
});

test('the TV watch redirect reports catalog failures without a click event', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $this->actingAs(User::factory()->create());
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get('/tv-shows/tmdb/87108/watch?country=US')->assertServiceUnavailable();

    Queue::assertNothingPushed();
});

test('TV watch links share the entry route creation budget', function () {
    config(['catalog.creation_limit.attempts' => 1]);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(87108, title: 'Chernobyl')]));
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);

    $this->get('/tv-shows/tmdb/999/watch?country=US')->assertTooManyRequests()->assertHeader('Retry-After');
    $this->get('/tv-shows/tmdb/87108/watch?country=US')->assertRedirect('https://www.themoviedb.org/tv/87108/watch?locale=US');
});

test('the TV watch redirect refuses invalid TMDB ids', function (string $tmdbId) {
    $this->get("/tv-shows/tmdb/{$tmdbId}/watch?country=US")->assertNotFound();
})->with(['0', 'nope', '999999999999999999999999']);
