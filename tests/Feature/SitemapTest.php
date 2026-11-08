<?php

use App\Jobs\BuildSitemap;
use App\Models\Movie;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('sitemap');
    config(['seo.sitemap_disk' => 'sitemap']);
});

/**
 * The `<loc>` values in a sitemap document.
 *
 * @return list<string>
 */
function sitemapLocations(string $xml): array
{
    preg_match_all('#<loc>(.*?)</loc>#', $xml, $matches);

    return array_map(html_entity_decode(...), $matches[1]);
}

test('with the flag on, the sitemap index lists a sitemap that holds every Movie with details and its lastmod', function () {
    config(['seo.indexing' => true]);
    $movie = Movie::factory()->create(['synced_at' => '2026-11-01 12:00:00']);
    $removed = Movie::factory()->removed()->create();
    $cleared = Movie::factory()->capCleared()->create();

    BuildSitemap::dispatchSync();

    $index = $this->get(route('sitemap.index'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    expect(sitemapLocations($index->getContent()))->toBe([route('sitemap.show', ['page' => 1])]);

    $page = $this->get(route('sitemap.show', ['page' => 1]))->assertOk();
    expect(sitemapLocations($page->getContent()))->toBe([$movie->canonicalUrl()])
        ->and($page->getContent())->toContain('<lastmod>2026-11-01T12:00:00+00:00</lastmod>')
        ->and(sitemapLocations($page->getContent()))->not->toContain($cleared->canonicalUrl())
        ->not->toContain($removed->canonicalUrl());
});

test('the sitemap index starts a new sitemap each time a page fills up', function () {
    config(['seo.indexing' => true, 'seo.sitemap_page_size' => 2]);
    Movie::factory()->count(5)->create();

    BuildSitemap::dispatchSync();

    expect(sitemapLocations($this->get(route('sitemap.index'))->getContent()))->toHaveCount(3);
    expect(sitemapLocations($this->get(route('sitemap.show', ['page' => 3]))->getContent()))->toHaveCount(1);
    $this->get(route('sitemap.show', ['page' => 4]))->assertNotFound();
});

test('a rebuild drops Movies that lost their details and pages that are no longer needed', function () {
    config(['seo.indexing' => true, 'seo.sitemap_page_size' => 1]);
    $movies = Movie::factory()->count(2)->create();
    BuildSitemap::dispatchSync();

    $movies[1]->forceFill(['synced_at' => null])->save();
    BuildSitemap::dispatchSync();

    expect(sitemapLocations($this->get(route('sitemap.index'))->getContent()))->toHaveCount(1);
    $this->get(route('sitemap.show', ['page' => 2]))->assertNotFound();
});

test('with the flag off nothing is built and nothing is served', function () {
    config(['seo.indexing' => true]);
    Movie::factory()->create();
    BuildSitemap::dispatchSync();

    config(['seo.indexing' => false]);
    $this->get(route('sitemap.index'))->assertNotFound();
    $this->get(route('sitemap.show', ['page' => 1]))->assertNotFound();

    BuildSitemap::dispatchSync();
    expect(Storage::disk('sitemap')->allFiles())->toBeEmpty();

    config(['seo.indexing' => true]);
    $this->get(route('sitemap.index'))->assertNotFound();
});

test('without a build the index is not found even with the flag on', function () {
    config(['seo.indexing' => true]);

    $this->get(route('sitemap.index'))->assertNotFound();
});

test('robots.txt references the sitemap only when the flag is on', function () {
    config(['seo.indexing' => true]);
    expect(trim($this->get('/robots.txt')->getContent()))->toBe("User-agent: *\nDisallow:\n\nSitemap: ".route('sitemap.index'));

    config(['seo.indexing' => false]);
    expect($this->get('/robots.txt')->getContent())->not->toContain('Sitemap');
});

test('the build runs on the imports queue in the 05:00 UTC slot', function () {
    $this->travelTo('2026-11-06 05:00:00');
    Queue::fake();

    $this->artisan('schedule:run')->assertSuccessful();

    Queue::assertPushedOn('imports', BuildSitemap::class, fn (BuildSitemap $job) => $job->delay !== null);
});

test('a rebuild with no Movies left with details leaves an empty index and no pages', function () {
    config(['seo.indexing' => true]);
    $movie = Movie::factory()->create();
    BuildSitemap::dispatchSync();

    $movie->forceFill(['synced_at' => null])->save();
    BuildSitemap::dispatchSync();

    expect(sitemapLocations($this->get(route('sitemap.index'))->getContent()))->toBe([]);
    $this->get(route('sitemap.show', ['page' => 1]))->assertNotFound();
});
