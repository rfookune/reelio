<?php

use App\Enums\TitleKind;
use App\Jobs\RecordAnalyticsEvent;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\Queue;

/** Installs a fake source with two Browse pages for each kind, a genre list and a Streaming service list, and has a page read one source page. */
function fakeAnalyticsBrowse(bool $failing = false): FakeCatalogSource
{
    config(['catalog.search.browse_fill_target' => 1]);
    $source = new FakeCatalogSource(
        failing: $failing,
        genres: [['id' => 35, 'name' => 'Comedy']],
        browsePages: [movieListings(1, 3), movieListings(4, 5)],
        tvBrowsePages: [[movieListing(51, kind: TitleKind::TvShow), movieListing(52, kind: TitleKind::TvShow)]],
        tvGenres: [['id' => 9035, 'name' => 'Comedy']],
        streamingServices: ['US' => [['id' => 8, 'name' => 'Netflix']]],
        tvStreamingServices: ['US' => [['id' => 8, 'name' => 'Netflix']]],
    );
    app()->instance(CatalogSource::class, $source);

    return $source;
}

/** @return list<RecordAnalyticsEvent> the events queued, in order */
function queuedEvents(): array
{
    return Queue::pushed(RecordAnalyticsEvent::class)->values()->all();
}

test('the first page of a signed-in user\'s Browse records the tab, the result count and no filters', function (string $tab, int $count) {
    Queue::fake([RecordAnalyticsEvent::class]);
    $user = User::factory()->create();
    fakeAnalyticsBrowse();

    $this->actingAs($user)->get(route('search', ['tab' => $tab]))->assertOk();

    expect(queuedEvents())->toHaveCount(1)
        ->and(queuedEvents()[0]->userId)->toBe($user->id)
        ->and(queuedEvents()[0]->name)->toBe('title_browsed')
        ->and(queuedEvents()[0]->properties)->toBe(['tab' => $tab, 'filters' => '', 'result_count' => $count]);
})->with([
    'Movies' => ['movie', 3],
    'TV Shows' => ['tv', 2],
]);

test('it names the filters used and carries none of their values', function (array $query, string $filters) {
    Queue::fake([RecordAnalyticsEvent::class]);
    fakeAnalyticsBrowse();

    $this->actingAs(User::factory()->create())->get(route('search', $query))->assertOk();

    expect(queuedEvents())->toHaveCount(1)
        ->and(queuedEvents()[0]->properties)->toBe(['tab' => 'movie', 'filters' => $filters, 'result_count' => 3]);
})->with([
    'a genre' => [['genre' => 35], 'genre'],
    'a Streaming service' => [['services' => '8'], 'streaming_service'],
    'Highly rated' => [['highly_rated' => '1'], 'highly_rated'],
    'an era' => [['era' => '2010s'], 'era'],
    'a runtime' => [['runtime' => 'under_90'], 'runtime'],
    'an original language' => [['language' => 'ko'], 'original_language'],
    'Show seen' => [['show_seen' => '1'], 'show_seen'],
    'a sort alone is no filter' => [['sort' => 'newest'], ''],
    'an invalid filter is not used' => [['genre' => 999, 'language' => 'xx', 'services' => '77'], ''],
    'all of them' => [['genre' => 35, 'services' => '8', 'highly_rated' => '1', 'era' => '2010s', 'runtime' => 'under_90', 'language' => 'ko', 'show_seen' => '1'], 'genre,streaming_service,highly_rated,era,runtime,original_language,show_seen'],
]);

test('only the first page is recorded', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    fakeAnalyticsBrowse();

    $this->actingAs(User::factory()->create())->get(route('search', ['page' => 2]))->assertOk();

    expect(queuedEvents())->toBe([]);
});

test('reloading the viewer\'s facts alone records nothing', function () {
    $facts = UserMovie::factory()->for(Movie::factory()->state(['tmdb_id' => 2]))->create();
    fakeAnalyticsBrowse();
    $this->actingAs($facts->user)->freezeTime();
    Queue::fake([RecordAnalyticsEvent::class]);
    $version = (string) $this->get(route('search'))->viewData('page')['version'];
    $partial = ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'Search', 'X-Inertia-Partial-Data' => 'userMovies'];
    expect(queuedEvents())->toHaveCount(1);

    $this->get(route('search'), $partial)->assertOk();

    expect(queuedEvents())->toHaveCount(1);
});

test('nothing is recorded for a guest', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    fakeAnalyticsBrowse();

    $this->get(route('search'))->assertOk();

    expect(queuedEvents())->toBe([]);
});

test('nothing is recorded when the source can\'t give the page', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    fakeAnalyticsBrowse(failing: true);

    $this->actingAs(User::factory()->create())->get(route('search'))->assertOk();

    expect(queuedEvents())->toBe([]);
});

test('Browse records title_browsed and a search by Name records only title_searched', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $source = fakeAnalyticsBrowse();
    $source->searches = ['inter' => [movieListings(7, 8)]];
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('search'))->assertOk();
    $this->actingAs($user)->get(route('search', ['q' => 'inter', 'genre' => 35]))->assertOk();

    expect(array_map(fn (RecordAnalyticsEvent $event): string => $event->name, queuedEvents()))->toBe(['title_browsed', 'title_searched'])
        ->and(queuedEvents()[1]->properties)->toBe(['query_length' => 5, 'result_count' => 2]);
});

test('later pages with Show seen on, and the lookup of IMDb ratings, record nothing', function () {
    config(['ratings.sources.imdb.enabled' => true]);
    Queue::fake([RecordAnalyticsEvent::class]);
    fakeAnalyticsBrowse();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('search', ['page' => 2, 'show_seen' => '1']))->assertOk();
    $this->actingAs($user)->getJson(route('search.ratings'))->assertOk();

    expect(queuedEvents())->toBe([]);
});
