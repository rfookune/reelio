<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\Availability;
use App\Models\Movie;
use App\Models\StreamingService;
use App\Models\TvShow;
use App\Models\User;
use App\Services\Catalog\Enums\OfferType;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Titles\AvailabilityWriter;
use Illuminate\Support\Facades\Queue;

/** @return array{serviceId: int, serviceName: string, logoPath: ?string, country: string, offerType: string, displayPriority: int} */
function offer(int $serviceId, string $offerType = 'stream', string $country = 'US', int $priority = 1, string $name = 'Service'): array
{
    return ['serviceId' => $serviceId, 'serviceName' => $name, 'logoPath' => "/logo{$serviceId}.png", 'country' => $country, 'offerType' => $offerType, 'displayPriority' => $priority];
}

test('creating a Movie stores its Availability and the Streaming services it references', function () {
    $this->travelTo('2026-11-05 12:00:00');
    $this->app->instance(CatalogSource::class, new FakeCatalogSource([new MovieDetails(
        tmdbId: 157336,
        title: 'Interstellar',
        availability: [offer(350, 'stream', name: 'Apple TV'), offer(10, 'rent', priority: 9, name: 'Amazon Video'), offer(10, 'buy', 'GB', 4, 'Amazon Video')],
    )]));

    $this->get('/movies/tmdb/157336')->assertStatus(301);

    $movie = Movie::query()->sole();
    expect(StreamingService::query()->orderBy('id')->get(['id', 'name', 'logo_path'])->toArray())->toBe([
        ['id' => 10, 'name' => 'Amazon Video', 'logo_path' => '/logo10.png'],
        ['id' => 350, 'name' => 'Apple TV', 'logo_path' => '/logo350.png'],
    ])
        ->and($movie->availabilities()->orderBy('id')->get()->map(fn (Availability $row) => [$row->streaming_service_id, $row->country, $row->offer_type, $row->display_priority])->all())
        ->toBe([[350, 'US', OfferType::Stream, 1], [10, 'US', OfferType::Rent, 9], [10, 'GB', OfferType::Buy, 4]]);
});

test('a Movie\'s Availability is replaced wholesale and services are updated in place', function () {
    $movie = Movie::factory()->create();
    $other = Movie::factory()->create();
    Availability::factory()->for($other)->create();
    $writer = app(AvailabilityWriter::class);
    $writer->replace($movie, [offer(1, name: 'Old name'), offer(2, 'rent')]);

    $writer->replace($movie, [offer(1, 'free', name: 'New name')]);

    expect($movie->availabilities()->get()->map(fn (Availability $row) => [$row->streaming_service_id, $row->offer_type])->all())->toBe([[1, OfferType::Free]])
        ->and(StreamingService::query()->find(1)->name)->toBe('New name')
        ->and($other->availabilities()->count())->toBe(1);
});

test('the Movie page groups the visitor\'s country\'s Availability by offer type in display priority order', function () {
    $this->travelTo('2026-11-05 12:00:00');
    $movie = Movie::factory()->create();
    fakeCatalogKnowing(sourceDetailsFor($movie, availability: [
        offer(2, 'stream', priority: 17, name: 'Paramount Plus Premium'),
        offer(1, 'stream', priority: 5, name: 'Apple TV'),
        offer(3, 'stream', priority: 29, name: 'Paramount+ Amazon Channel'),
        offer(4, 'buy', priority: 9, name: 'Amazon Video'),
        offer(5, 'stream', 'GB', 1, 'Not shown in the US'),
    ]));

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertInertia(fn ($page) => $page
            ->where('whereToWatch.country', 'US')
            ->where('whereToWatch.countryName', 'United States')
            ->where('whereToWatch.updated', 'Updated today')
            ->where('whereToWatch.outOfDate', false)
            ->where('whereToWatch.groups', [
                ['type' => 'stream', 'label' => 'Stream', 'caption' => null, 'services' => [
                    ['id' => 1, 'name' => 'Apple TV', 'logoPath' => '/logo1.png'],
                    ['id' => 2, 'name' => 'Paramount Plus Premium', 'logoPath' => '/logo2.png'],
                    ['id' => 3, 'name' => 'Paramount+ Amazon Channel', 'logoPath' => '/logo3.png'],
                ]],
                ['type' => 'buy', 'label' => 'Buy', 'caption' => null, 'services' => [
                    ['id' => 4, 'name' => 'Amazon Video', 'logoPath' => '/logo4.png'],
                ]],
            ]));
});

test('the free group carries its ads caption', function () {
    $movie = Movie::factory()->create();
    fakeCatalogKnowing(sourceDetailsFor($movie, availability: [offer(73, 'free', name: 'Tubi TV')]));

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertInertia(fn ($page) => $page->where('whereToWatch.groups.0.type', 'free')->where('whereToWatch.groups.0.caption', 'Free, may include ads'));
});

test('the freshness line counts calendar days since the last sync', function (string $syncedAt, string $updated) {
    $this->travelTo('2026-11-05 12:00:00');
    $movie = Movie::factory()->create(['synced_at' => $syncedAt]);
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertInertia(fn ($page) => $page->where('whereToWatch.updated', $updated));
})->with([
    'earlier today' => ['2026-11-05 00:10:00', 'Updated today'],
    'yesterday' => ['2026-11-04 23:50:00', 'Updated yesterday'],
    'seven days ago, early' => ['2026-10-29 00:10:00', 'Updated 7 days ago'],
    'seven days ago, late' => ['2026-10-29 23:50:00', 'Updated 7 days ago'],
    'a month ago' => ['2026-10-06 12:00:00', 'Updated 30 days ago'],
]);

test('Availability renders from storage even when the source is down', function () {
    $movie = Movie::factory()->create();
    app(AvailabilityWriter::class)->replace($movie, [offer(1, name: 'Apple TV')]);
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertInertia(fn ($page) => $page->where('movie.detailsUnavailable', true)->has('whereToWatch.groups', 1)->where('whereToWatch.outOfDate', false));
});

test('Availability over a week old is flagged even when a longer refresh age keeps the Movie from refreshing', function () {
    config(['catalog.refresh.on_view_after_seconds' => 30 * 86400]);
    $this->travelTo('2026-11-05 12:00:00');
    $movie = Movie::factory()->create();
    fakeCatalogKnowing($movie);
    $url = route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]);
    $this->get($url)->assertOk();

    $this->travelTo('2026-11-12 23:50:00');
    $this->get($url)->assertInertia(fn ($page) => $page->where('whereToWatch.updated', 'Updated 7 days ago')->where('whereToWatch.outOfDate', false));
    $this->travelTo('2026-11-13 00:10:00');
    $this->get($url)->assertInertia(fn ($page) => $page->where('whereToWatch.updated', 'Updated 8 days ago')->where('whereToWatch.outOfDate', true));
});

test('Availability is flagged as possibly out of date when the Movie\'s details are served stale', function () {
    $movie = Movie::factory()->create();
    $source = fakeCatalogKnowing($movie);
    $url = route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]);
    $this->get($url)->assertInertia(fn ($page) => $page->where('whereToWatch.outOfDate', false));

    $this->travel(2)->days();
    $source->failing = true;

    $this->get($url)->assertInertia(fn ($page) => $page->where('whereToWatch.outOfDate', true));
});

test('no Availability gives an empty set of groups', function () {
    $movie = Movie::factory()->create();
    fakeCatalogKnowing($movie);

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertInertia(fn ($page) => $page->where('whereToWatch.groups', []));
});

test('the watch-page link redirects to TMDB for a supported country and records the click for signed-in users', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['tmdb_id' => 157336]);

    $this->actingAs($user)->get(route('movies.watch', ['id' => $movie->id, 'country' => 'GB']))
        ->assertStatus(302)
        ->assertRedirect('https://www.themoviedb.org/movie/157336/watch?locale=GB')
        ->assertHeader('X-Robots-Tag', 'noindex');

    Queue::assertPushed(RecordAnalyticsEvent::class, fn ($job) => $job->name === 'availability_link_clicked'
        && $job->properties === ['movie_id' => $movie->id, 'country' => 'GB']);
});

test('a guest following the watch-page link is redirected without an event', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $movie = Movie::factory()->create(['tmdb_id' => 157336]);

    $this->get(route('movies.watch', ['id' => $movie->id, 'country' => 'US']))->assertRedirect('https://www.themoviedb.org/movie/157336/watch?locale=US');

    Queue::assertNothingPushed();
});

test('the watch-page link refuses anything but a known Movie and a supported country', function (string $query) {
    $movie = Movie::factory()->create();

    $this->get("/movies/{$movie->id}/watch{$query}")->assertNotFound();
})->with([
    'no country' => [''],
    'an unsupported country' => ['?country=BR'],
    'lower case' => ['?country=us'],
    'an array' => ['?country[]=US'],
    'a URL' => ['?country=https://evil.example'],
]);

test('the watch-page link 404s for an unknown Movie', function () {
    $this->get('/movies/999999/watch?country=US')->assertNotFound();
});

test('a TV Show id cannot use the Movie watch redirect', function () {
    $tvShow = TvShow::factory()->create();

    $this->get(route('movies.watch', ['id' => $tvShow->id, 'country' => 'US']))->assertNotFound();
});
