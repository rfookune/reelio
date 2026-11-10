<?php

use App\Enums\Surface;
use App\Jobs\BuildDiscover;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Models\UserTitle;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('the homepage renders the discover page for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Discover'));
});

test('the homepage renders the discover page for signed-in users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Discover'));
});

test('the starter kit dashboard no longer exists', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertNotFound();
});

test('Discover shows the hero and numbered Trending, then Popular and Top rated, each Movie with its ratings and link', function () {
    Queue::fake([BuildDiscover::class]);
    config(['ratings.sources.imdb.enabled' => true]);
    $stored = Movie::factory()->create(['tmdb_id' => 2, 'runtime' => 101, 'imdb_id' => 'tt0000002']);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000001', 8.7, 2_000_000);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000002', 6.2, 900);
    fakeDiscoverSource([
        'trending' => [movieListings(1, 10)],
        'popular' => [movieListings(11, 20)],
        'top_rated' => [movieListings(21, 30)],
    ], [new MovieDetails(tmdbId: 1, runtime: 169, imdbId: 'tt0000001', genres: [['id' => 878, 'name' => 'Science Fiction']], trailer: ['key' => 'abcdefghijk', 'name' => 'Trailer 4', 'type' => 'Trailer'])]);
    app(DiscoverBuilder::class)->build(CatalogProfile::Background);

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Discover')
        ->where('hero.tmdbId', 1)
        ->where('hero.runtime', 169)
        ->where('hero.year', 2026)
        ->where('hero.genres', ['Science Fiction'])
        ->where('hero.trailer.key', 'abcdefghijk')
        ->where('hero.ratings', [
            ['source' => 'imdb', 'name' => 'IMDb', 'value' => 8.7, 'voteCount' => 2_000_000],
            ['source' => 'tmdb', 'name' => 'TMDB', 'value' => 7.5, 'voteCount' => 1000],
        ])
        ->where('hero.href', route('movies.entry', ['tmdbId' => 1, 'referrer' => 'discover']))
        ->where('sections.0.title', 'Trending this week')
        ->where('sections.0.numbered', true)
        ->where('sections.0.movies.0.tmdbId', 1)
        ->where('sections.0.movies.1.href', $stored->canonicalUrl(Surface::Discover))
        ->where('sections.0.movies.1.runtime', 101)
        ->where('sections.0.movies.1.ratings.0', ['source' => 'imdb', 'name' => 'IMDb', 'value' => 6.2, 'voteCount' => 900])
        ->where('sections.1.title', 'Popular')
        ->where('sections.1.numbered', false)
        ->where('sections.2.title', 'Top rated')
        ->where('sections.2.movies', fn ($movies) => count($movies) === 10)
        ->where('userMovies.21', ['watchlisted' => false, 'watched' => false, 'watchedDate' => null]));
    Queue::assertNotPushed(BuildDiscover::class);
});

test('Discover shows Coming soon with each Movie\'s US date after Popular, and the day\'s genre collections after Top rated', function () {
    Queue::fake([BuildDiscover::class]);
    $this->travelTo('2026-11-06 05:00:00');
    fakeDiscoverSource([
        'trending' => [movieListings(1, 10)],
        'popular' => [movieListings(11, 20)],
        'coming_soon' => [[movieListing(21)]],
        'top_rated' => [movieListings(31, 40)],
        'genre-35' => [movieListings(41, 50)],
        'genre-18' => [movieListings(51, 60)],
        'genre-878' => [movieListings(61, 70)],
    ], [new MovieDetails(tmdbId: 21, usReleaseDate: '2026-11-21')]);
    app(DiscoverBuilder::class)->build(CatalogProfile::Background);

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sections', fn ($sections) => collect($sections)->map(fn ($section): array => [$section['key'], $section['title']])->all() === [
            ['trending', 'Trending this week'],
            ['popular', 'Popular'],
            ['coming_soon', 'Coming soon'],
            ['top_rated', 'Top rated'],
            ['genre-35', 'Comedy'],
            ['genre-18', 'Drama'],
            ['genre-878', 'Science Fiction'],
        ])
        ->where('sections.2.movies.0.tmdbId', 21)
        ->where('sections.2.movies.0.opensOn', '2026-11-21')
        ->where('sections.1.movies.0.opensOn', null));
});

test('Discover carries the viewer\'s facts by catalog id and reloads only them after an action', function () {
    $facts = UserMovie::factory()->for(Movie::factory()->state(['tmdb_id' => 3]))->create();
    fakeDiscoverSource(['trending' => [movieListings(1, 10)]]);
    app(DiscoverBuilder::class)->build(CatalogProfile::Background);

    $this->actingAs($facts->user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('userMovies.3', ['watchlisted' => true, 'watched' => false, 'watchedDate' => null])
        ->where('userMovies.4', ['watchlisted' => false, 'watched' => false, 'watchedDate' => null])
        ->reloadOnly('userMovies', fn (Assert $reload) => $reload->missing('sections')->missing('hero')->has('userMovies.3')));
});

test('the first visitor builds Discover and queues the full build', function () {
    Queue::fake([BuildDiscover::class]);
    fakeDiscoverSource(['trending' => [movieListings(1, 10)]]);

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('sections', 1));

    Queue::assertPushed(BuildDiscover::class);
});

test('with no movie data at all, Discover still renders, with nothing to show', function () {
    Queue::fake([BuildDiscover::class]);
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('hero', null)
        ->where('sections', []));
    Queue::assertNotPushed(BuildDiscover::class);
});

test('Discover cards carry no User rating, even for a Movie the viewer rated', function () {
    Queue::fake([BuildDiscover::class]);
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['tmdb_id' => 2]);
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $movie->id, 'rating' => 8]);
    fakeDiscoverSource(['trending' => [movieListings(1, 10)], 'popular' => [movieListings(11, 20)], 'top_rated' => [movieListings(21, 30)]]);
    app(DiscoverBuilder::class)->build(CatalogProfile::Background);

    $response = $this->actingAs($user)->get(route('home'))->assertOk();

    expect(json_encode($response->viewData('page')['props']))->not->toContain('userRating');
});
