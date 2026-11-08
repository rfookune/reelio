<?php

use App\Http\Exceptions\DetailsUnavailable;
use App\Jobs\RecordAnalyticsEvent;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\MovieDetails;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('a cap-cleared Movie\'s page refreshes it in the request, restoring its details and its slug', function () {
    $movie = Movie::factory()->capCleared()->create(['tmdb_id' => 157336]);
    fakeCatalogKnowing(new MovieDetails(tmdbId: 157336, title: 'Interstellar'));

    $this->get($movie->canonicalUrl())->assertMovedPermanently()->assertRedirect(route('movies.show', ['slug' => 'interstellar', 'id' => $movie->id]));

    $this->assertDatabaseHas('titles', ['id' => $movie->id, 'title' => 'Interstellar', 'slug' => 'interstellar', 'removed_at' => null]);
});

test('a cap-cleared Movie whose refresh fails is a 503 with no stored fields and the viewer\'s facts, recording no view, at any slug', function (string $slug) {
    $movie = Movie::factory()->capCleared()->create(['last_viewed_at' => '2026-11-01 00:00:00']);
    $user = User::factory()->create();
    UserMovie::factory()->for($user)->for($movie)->create(['watchlisted_at' => '2026-10-02', 'watched_at' => '2026-10-01']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    Queue::fake([RecordAnalyticsEvent::class]);

    $this->actingAs($user)->get(route('movies.show', ['slug' => $slug, 'id' => $movie->id]))
        ->assertServiceUnavailable()
        ->assertHeader('Retry-After')
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertInertia(fn (Assert $page) => $page->component('Error')
            ->where('exceptionClass', DetailsUnavailable::class)
            ->where('title', 'Details temporarily unavailable')
            ->where('data.tmdbId', $movie->tmdb_id)
            ->where('data.userMovies', [$movie->tmdb_id => ['watchlisted' => true, 'watched' => true, 'watchedDate' => '2026-10-01T00:00:00.000000Z']])
            ->missing('movie'));

    $this->assertDatabaseHas('titles', ['id' => $movie->id, 'last_viewed_at' => '2026-11-01 00:00:00', 'removed_at' => null]);
    Queue::assertNothingPushed();
})->with(['its canonical slug' => 'movie', 'an old slug' => 'interstellar']);

test('the 503 page\'s partial reload after Remove or Clear stays the 503 with the viewer\'s facts', function () {
    $movie = Movie::factory()->capCleared()->create();
    $facts = UserMovie::factory()->for($movie)->watched()->create(['watched_at' => '2026-10-01']);
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    $version = $this->actingAs($facts->user)->get($movie->canonicalUrl())->assertServiceUnavailable()->viewData('page')['version'];

    $this->get($movie->canonicalUrl(), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => 'Error',
        'X-Inertia-Partial-Data' => 'data,exceptionClass',
    ])->assertServiceUnavailable()
        ->assertJsonPath('component', 'Error')
        ->assertJsonPath("props.data.userMovies.{$movie->tmdb_id}.watched", true)
        ->assertJsonMissingPath('props.movie');
});

test('a cap-cleared Movie the source has since removed becomes a Removed Movie with a 410', function () {
    $movie = Movie::factory()->capCleared()->create();
    fakeCatalogKnowing();

    $this->get($movie->canonicalUrl())->assertGone();

    expect($movie->fresh()->isRemoved())->toBeTrue();
});

test('a cap-cleared Movie\'s cast page is the same 503 while its refresh fails', function () {
    $movie = Movie::factory()->capCleared()->create();
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get(route('movies.cast', ['slug' => 'movie', 'id' => $movie->id]))->assertServiceUnavailable()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('exceptionClass', DetailsUnavailable::class)->missing('movie'));
});
