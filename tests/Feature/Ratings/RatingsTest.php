<?php

use App\Catalog\Source\CatalogSource;
use App\Catalog\Source\FakeCatalogSource;
use App\Catalog\Source\MovieDetails;
use App\Models\Movie;
use App\Ratings\ExternalRating;
use App\Ratings\FakeRatingSource;
use App\Ratings\Ratings;
use App\Ratings\RatingSourceName;
use App\Ratings\RatingStore;
use App\Ratings\StoredRatingSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function recordRating(RatingSourceName $source, string $sourceId, ?float $value, ?int $votes): void
{
    app(RatingStore::class)->record($source, $sourceId, $value, $votes);
}

test('a stored source reads each Movie\'s score through the identifier its source uses', function (RatingSourceName $source, array $movieAttributes, string $sourceId) {
    $this->travelTo('2026-11-05 12:00:00');
    $movie = Movie::factory()->create($movieAttributes);
    recordRating($source, $sourceId, 8.493, 41780);

    expect((new StoredRatingSource($source))->ratingsFor(collect([$movie])))
        ->toEqual([$movie->id => new ExternalRating($source, 8.493, 41780, CarbonImmutable::parse('2026-11-05 12:00:00'))]);
})->with([
    'IMDb by imdb_id' => [RatingSourceName::Imdb, ['imdb_id' => 'tt0816692'], 'tt0816692'],
    'TMDB by tmdb_id' => [RatingSourceName::Tmdb, ['tmdb_id' => 157336], '157336'],
]);

test('no identifier or no stored row means absent, with no title search', function () {
    recordRating(RatingSourceName::Imdb, 'tt0816692', 8.7, 2_400_000);
    $withoutId = Movie::factory()->create(['imdb_id' => null, 'title' => 'Interstellar']);
    $notStored = Movie::factory()->create(['imdb_id' => 'tt0000404']);

    expect((new StoredRatingSource(RatingSourceName::Imdb))->ratingsFor(collect([$withoutId, $notStored])))->toBe([]);
});

test('a stored source looks up many movies in one query', function () {
    $movies = Movie::factory()->count(5)->create();
    DB::enableQueryLog();

    (new StoredRatingSource(RatingSourceName::Tmdb))->ratingsFor($movies);

    expect(DB::getQueryLog())->toHaveCount(1);
});

test('a missing, out-of-range or zero-vote score is never stored, and recording one removes the old row', function (?float $value, ?int $votes) {
    recordRating(RatingSourceName::Tmdb, '157336', 8.5, 100);

    recordRating(RatingSourceName::Tmdb, '157336', $value, $votes);

    expect(DB::table('ratings')->count())->toBe(0);
})->with([
    'no data' => [null, null],
    'zero votes' => [0.0, 0],
    'votes but no average' => [null, 12],
    'average with no votes' => [7.0, null],
    'above ten' => [10.5, 3],
    'more votes than the column holds' => [7.0, 3_000_000_000],
]);

test('recording a score again updates it in place', function () {
    recordRating(RatingSourceName::Tmdb, '157336', 8.5, 100);
    recordRating(RatingSourceName::Tmdb, '157336', 8.4, 120);

    expect(DB::table('ratings')->sole())->toMatchObject(['source' => 'tmdb', 'source_id' => '157336', 'value' => '8.400', 'vote_count' => 120]);
});

test('ratings come back in one batch for many movies in the fixed IMDb then TMDB order', function () {
    $first = Movie::factory()->create(['tmdb_id' => 1]);
    $second = Movie::factory()->create(['tmdb_id' => 2]);
    recordRating(RatingSourceName::Tmdb, '1', 7.0, 10);
    $imdb = new FakeRatingSource([$first->id => new ExternalRating(RatingSourceName::Imdb, 8.7, 2_400_000, CarbonImmutable::now())]);

    $result = (new Ratings([new StoredRatingSource(RatingSourceName::Tmdb), $imdb]))->ratingsFor(collect([$first, $second]));

    expect(array_map(fn (ExternalRating $rating) => $rating->source, $result[$first->id]))->toBe([RatingSourceName::Imdb, RatingSourceName::Tmdb])
        ->and($result[$second->id])->toBe([])
        ->and($imdb->batches)->toBe(1);
});

test('the fake source returns only the given ratings for the requested movies', function () {
    $rated = Movie::factory()->create();
    $other = Movie::factory()->create();
    $rating = new ExternalRating(RatingSourceName::Imdb, 8.7, 2_400_000, CarbonImmutable::now());
    $fake = new FakeRatingSource([$rated->id => $rating, $other->id => $rating]);

    expect($fake->ratingsFor(collect([$rated])))->toBe([$rated->id => $rating])
        ->and((new FakeRatingSource)->ratingsFor(collect([$rated])))->toBe([]);
});

test('creating a Movie records its TMDB score', function () {
    $this->app->instance(CatalogSource::class, new FakeCatalogSource([new MovieDetails(tmdbId: 157336, title: 'Interstellar', voteAverage: 8.493, voteCount: 41780)]));

    $this->get('/movies/tmdb/157336')->assertStatus(301);

    expect(DB::table('ratings')->sole())->toMatchObject(['source' => 'tmdb', 'source_id' => '157336', 'value' => '8.493', 'vote_count' => 41780]);
});

test('refetching a Movie\'s details records its latest TMDB score', function () {
    $movie = Movie::factory()->create(['tmdb_id' => 157336]);
    $this->app->instance(CatalogSource::class, new FakeCatalogSource([new MovieDetails(tmdbId: 157336, voteAverage: 8.6, voteCount: 50000)]));

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))->assertOk();

    expect(DB::table('ratings')->sole())->toMatchObject(['source' => 'tmdb', 'source_id' => '157336', 'value' => '8.600', 'vote_count' => 50000]);
});

test('a switched-off source records nothing', function () {
    config(['ratings.sources.tmdb.enabled' => false]);

    recordRating(RatingSourceName::Tmdb, '157336', 8.5, 100);

    expect(DB::table('ratings')->count())->toBe(0);
});

test('the movie page sends its External ratings in display order', function () {
    $movie = Movie::factory()->create(['tmdb_id' => 157336, 'imdb_id' => 'tt0816692']);
    recordRating(RatingSourceName::Tmdb, '157336', 8.5, 41832);
    recordRating(RatingSourceName::Imdb, 'tt0816692', 8.7, 2_400_000);

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertInertia(fn ($page) => $page
            ->has('movie.ratings', 2)
            ->where('movie.ratings.0.source', 'imdb')
            ->where('movie.ratings.1.source', 'tmdb')
            ->where('movie.ratings.1.name', 'TMDB')
            ->where('movie.ratings.1.value', 8.5)
            ->where('movie.ratings.1.voteCount', 41832));
});

test('the movie page sends no ratings when there are none', function () {
    $movie = Movie::factory()->create();

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertInertia(fn ($page) => $page->where('movie.ratings', []));
});

test('switching a source off hides only that source', function () {
    $movie = Movie::factory()->create(['tmdb_id' => 157336, 'imdb_id' => 'tt0816692']);
    recordRating(RatingSourceName::Tmdb, '157336', 8.5, 41832);
    recordRating(RatingSourceName::Imdb, 'tt0816692', 8.7, 2_400_000);
    config(['ratings.sources.imdb.enabled' => false]);

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertInertia(fn ($page) => $page->has('movie.ratings', 1)->where('movie.ratings.0.source', 'tmdb'));
});

test('the TMDB score columns come off movies, and rolling back restores them', function () {
    Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_11_06_045705_drop_tmdb_scores_from_movies_table.php']);

    expect(Schema::hasColumns('movies', ['vote_average', 'vote_count']))->toBeTrue();

    Artisan::call('migrate');

    expect(Schema::hasColumn('movies', 'vote_average'))->toBeFalse()
        ->and(Schema::hasColumn('movies', 'vote_count'))->toBeFalse();
});
