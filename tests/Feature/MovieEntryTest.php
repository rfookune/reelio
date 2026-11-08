<?php

use App\Models\Movie;
use App\Models\User;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\SourceMisconfigured;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Inertia\Testing\AssertableInertia as Assert;

test('opening a confirmed catalog id creates a movie and redirects to its local page', function () {
    $this->travelTo('2026-11-05 12:00:00');
    $this->app->instance(CatalogSource::class, new FakeCatalogSource([
        new MovieDetails(tmdbId: 157336, title: 'Interstellar', overview: 'A journey beyond the stars.', releaseDate: '2014-11-04', genres: [['id' => 878, 'name' => 'Science Fiction']]),
    ]));

    $response = $this->get('/movies/tmdb/157336');

    $response->assertStatus(301);
    $url = $response->headers->get('Location');
    expect($url)->toMatch('/\/movies\/interstellar-\d+$/');
    $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('movies/Show')
        ->where('movie.title', 'Interstellar')
        ->where('movie.year', 2014)
        ->where('movie.overview', 'A journey beyond the stars.')
    );
    $this->assertDatabaseHas('titles', [
        'tmdb_id' => 157336,
        'slug' => 'interstellar',
        'synced_at' => '2026-11-05 12:00:00',
        'last_viewed_at' => '2026-11-05 12:00:00',
    ]);
    $this->assertDatabaseHas('genres', ['id' => 878, 'name' => 'Science Fiction']);
    $this->assertDatabaseHas('genre_title', ['title_id' => Movie::query()->sole()->id, 'genre_id' => 878]);
});

test('unknown catalog ids return 404 without creating rows', function () {
    $this->app->instance(CatalogSource::class, new FakeCatalogSource);

    $this->get('/movies/tmdb/999999')->assertNotFound();

    $this->assertDatabaseCount('titles', 0);
});

test('a source outage returns 503 without creating a movie', function () {
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get('/movies/tmdb/157336')->assertServiceUnavailable();

    $this->assertDatabaseCount('titles', 0);
});

test('existing movies redirect without contacting the source or creating another row', function () {
    $movie = Movie::factory()->create(['tmdb_id' => 157336, 'slug' => 'interstellar']);
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get('/movies/tmdb/157336')->assertStatus(301)
        ->assertRedirect(route('movies.show', ['slug' => 'interstellar', 'id' => $movie->id]));

    $this->assertDatabaseCount('titles', 1);
});

test('guests can check only twenty new catalog ids per minute while existing movies stay accessible', function () {
    $this->freezeTime();
    $movie = Movie::factory()->create();
    $this->app->instance(CatalogSource::class, new FakeCatalogSource);

    for ($attempt = 1; $attempt <= 20; $attempt++) {
        $this->get('/movies/tmdb/157336')->assertNotFound();
    }

    $this->get('/movies/tmdb/157336')->assertTooManyRequests()->assertHeader('Retry-After', '60');
    $this->get('/movies/tmdb/'.$movie->tmdb_id)->assertStatus(301);
    $this->assertDatabaseCount('titles', 1);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->get('/movies/tmdb/157336')->assertNotFound();
});

test('signed-in creation limits follow the user across addresses and do not block another user', function () {
    $this->freezeTime();
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $this->app->instance(CatalogSource::class, new FakeCatalogSource);
    $this->actingAs($firstUser);

    for ($attempt = 1; $attempt <= 20; $attempt++) {
        $this->get('/movies/tmdb/157336')->assertNotFound();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->get('/movies/tmdb/157336')->assertTooManyRequests();
    $this->actingAs($secondUser)->get('/movies/tmdb/157336')->assertNotFound();
});

test('oversized catalog ids return 404 without contacting the source', function () {
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get('/movies/tmdb/999999999999999999999999999')->assertNotFound();

    $this->assertDatabaseCount('titles', 0);
});

test('a source outage on the entry route renders the 503 page with Retry-After and noindex', function () {
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get('/movies/tmdb/157336')
        ->assertServiceUnavailable()
        ->assertHeader('Retry-After', '30')
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 503)
            ->where('title', 'Details temporarily unavailable'));
});

test('a rejected TMDB token is reported to the owner and still shows the 503 page', function () {
    $this->app->instance(CatalogSource::class, new class extends FakeCatalogSource
    {
        public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
        {
            throw new SourceMisconfigured('rejected');
        }
    });
    $reported = [];
    $this->app->make(ExceptionHandler::class)->reportable(function (SourceMisconfigured $e) use (&$reported) {
        $reported[] = $e;

        return false;
    });

    $this->get('/movies/tmdb/157336')->assertServiceUnavailable();

    expect($reported)->toHaveCount(1);
});
