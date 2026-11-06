<?php

use App\Models\Movie;
use App\Ratings\ExternalRating;
use App\Ratings\FakeRatingSource;
use App\Ratings\ImdbRatingSource;
use App\Ratings\Ratings;
use App\Ratings\RatingSourceName;
use App\Ratings\TmdbRatingSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

test('the TMDB source reads the rating from the movie row', function () {
    $movie = Movie::factory()->create(['vote_average' => 8.5, 'vote_count' => 41832, 'synced_at' => '2026-11-05 12:00:00']);

    $ratings = (new TmdbRatingSource)->ratingsFor(collect([$movie]));

    expect($ratings)->toHaveCount(1)
        ->and($ratings[$movie->id])->toEqual(new ExternalRating(RatingSourceName::Tmdb, 8.5, 41832, CarbonImmutable::parse('2026-11-05 12:00:00')));
});

test('a missing, unvoted or zero-vote TMDB rating is absent, never zero', function (?float $average, ?int $votes) {
    $movie = Movie::factory()->create(['vote_average' => $average, 'vote_count' => $votes]);

    expect((new TmdbRatingSource)->ratingsFor(collect([$movie])))->toBe([]);
})->with([
    'no data' => [null, null],
    'zero votes' => [0.0, 0],
    'votes but no average' => [null, 12],
    'average with no votes' => [7.0, null],
]);

test('ratings come back in one batch for many movies in the fixed IMDb then TMDB order', function () {
    $first = Movie::factory()->create(['vote_average' => 7.0, 'vote_count' => 10]);
    $second = Movie::factory()->create(['vote_average' => null, 'vote_count' => null]);
    $imdb = new FakeRatingSource([$first->id => new ExternalRating(RatingSourceName::Imdb, 8.7, 2_400_000, CarbonImmutable::now())]);
    $ratings = new Ratings([new TmdbRatingSource, $imdb]);

    $result = $ratings->ratingsFor(collect([$first, $second]));

    expect(array_map(fn (ExternalRating $rating) => $rating->source, $result[$first->id]))->toBe([RatingSourceName::Imdb, RatingSourceName::Tmdb])
        ->and($result[$second->id])->toBe([])
        ->and($imdb->batches)->toBe(1);
});

test('the fake source returns given ratings or none', function () {
    $movie = Movie::factory()->create();

    expect((new FakeRatingSource)->ratingsFor(collect([$movie])))->toBe([]);
});

test('the fake source returns only the given ratings for the requested movies', function () {
    $rated = Movie::factory()->create();
    $other = Movie::factory()->create();
    $rating = new ExternalRating(RatingSourceName::Imdb, 8.7, 2_400_000, CarbonImmutable::now());
    $fake = new FakeRatingSource([$rated->id => $rating, $other->id => $rating]);

    expect($fake->ratingsFor(collect([$rated])))->toBe([$rated->id => $rating]);
});

test('the movie page sends its External ratings in display order', function () {
    $movie = Movie::factory()->create(['vote_average' => 8.5, 'vote_count' => 41832]);

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertInertia(fn ($page) => $page
            ->has('movie.ratings', 1)
            ->where('movie.ratings.0.source', 'tmdb')
            ->where('movie.ratings.0.name', 'TMDB')
            ->where('movie.ratings.0.value', 8.5)
            ->where('movie.ratings.0.voteCount', 41832));
});

test('the movie page sends no ratings when there are none', function () {
    $movie = Movie::factory()->create(['vote_average' => 0, 'vote_count' => 0]);

    $this->get(route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertInertia(fn ($page) => $page->where('movie.ratings', []));
});

test('the IMDb source joins the imported table through imdb_id', function () {
    DB::table('imdb_ratings')->insert(['imdb_id' => 'tt0816692', 'rating' => 8.7, 'vote_count' => 2_400_000, 'imported_at' => '2026-11-05 05:00:00']);
    $movie = Movie::factory()->create(['imdb_id' => 'tt0816692']);

    expect((new ImdbRatingSource)->ratingsFor(collect([$movie])))
        ->toEqual([$movie->id => new ExternalRating(RatingSourceName::Imdb, 8.7, 2_400_000, CarbonImmutable::parse('2026-11-05 05:00:00'))]);
});

test('no imdb_id or no imported entry means the IMDb rating is absent, with no title search', function () {
    DB::table('imdb_ratings')->insert(['imdb_id' => 'tt0816692', 'rating' => 8.7, 'vote_count' => 2_400_000, 'imported_at' => now()]);
    $withoutId = Movie::factory()->create(['imdb_id' => null, 'title' => 'Interstellar']);
    $notImported = Movie::factory()->create(['imdb_id' => 'tt0000404']);

    expect((new ImdbRatingSource)->ratingsFor(collect([$withoutId, $notImported])))->toBe([]);
});

test('the IMDb source looks up many movies in one query', function () {
    $movies = Movie::factory()->count(5)->sequence(fn ($sequence) => ['imdb_id' => 'tt'.$sequence->index])->create();
    DB::enableQueryLog();

    (new ImdbRatingSource)->ratingsFor($movies);

    expect(DB::getQueryLog())->toHaveCount(1);
});

test('turning the IMDb source off removes only the IMDb rating', function () {
    DB::table('imdb_ratings')->insert(['imdb_id' => 'tt0816692', 'rating' => 8.7, 'vote_count' => 2_400_000, 'imported_at' => now()]);
    $movie = Movie::factory()->create(['imdb_id' => 'tt0816692', 'vote_average' => 8.5, 'vote_count' => 41832]);
    $url = route('movies.show', ['slug' => $movie->slug, 'id' => $movie->id]);

    $this->get($url)->assertInertia(fn ($page) => $page->where('movie.ratings.0.source', 'imdb')->where('movie.ratings.1.source', 'tmdb'));

    config(['ratings.imdb.enabled' => false]);

    $this->get($url)->assertInertia(fn ($page) => $page->has('movie.ratings', 1)->where('movie.ratings.0.source', 'tmdb'));
});
