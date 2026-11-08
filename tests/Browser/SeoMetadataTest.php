<?php

use App\Models\Movie;

/** What the page's `<head>` says, read from the document the way a crawler's renderer sees it. */
function headMetadata($page): array
{
    return $page->script(<<<'JS'
        (() => {
            const meta = (selector, attribute = 'content') => document.head.querySelector(selector)?.getAttribute(attribute) ?? null;
            const jsonLd = document.head.querySelector('script[type="application/ld+json"]');

            return {
                title: document.title,
                description: meta('meta[name="description"]'),
                canonical: meta('link[rel="canonical"]', 'href'),
                ogTitle: meta('meta[property="og:title"]'),
                ogType: meta('meta[property="og:type"]'),
                ogUrl: meta('meta[property="og:url"]'),
                ogImage: meta('meta[property="og:image"]'),
                ogDescription: meta('meta[property="og:description"]'),
                twitterCard: meta('meta[name="twitter:card"]'),
                twitterImage: meta('meta[name="twitter:image"]'),
                jsonLd: jsonLd === null ? null : JSON.parse(jsonLd.textContent),
                titleCount: document.head.querySelectorAll('title').length,
            };
        })()
        JS);
}

it('describes a Movie page with its title, overview, backdrop, canonical URL and JSON-LD', function () {
    $movie = Movie::factory()->create([
        'title' => 'Interstellar', 'slug' => 'interstellar', 'release_date' => '2014-11-05', 'runtime' => 169,
        'overview' => str_repeat('A team of explorers travels beyond the stars. ', 8),
        'backdrop_path' => '/backdrop.jpg', 'poster_path' => '/poster.jpg',
    ]);
    fakeCatalogKnowing(sourceDetailsFor($movie, directors: ['Christopher Nolan']));

    $page = visitWithCsp($movie->canonicalUrl());
    $head = headMetadata($page);

    expect($head['title'])->toBe('Interstellar (2014) · Reelio')->and($head['titleCount'])->toBe(1)
        ->and($head['ogTitle'])->toBe('Interstellar (2014)')
        ->and($head['canonical'])->toBe($movie->canonicalUrl())->and($head['ogUrl'])->toBe($movie->canonicalUrl())
        ->and($head['ogType'])->toBe('video.movie')
        ->and(mb_strlen($head['description']))->toBeLessThanOrEqual(155)->and($head['description'])->toEndWith('…')
        ->and($head['ogDescription'])->toBe($head['description'])
        ->and($head['ogImage'])->toEndWith('/w1280/backdrop.jpg')->and($head['twitterImage'])->toBe($head['ogImage'])
        ->and($head['twitterCard'])->toBe('summary_large_image')
        ->and($head['jsonLd'])->toMatchArray([
            '@type' => 'Movie', 'name' => 'Interstellar', 'datePublished' => '2014-11-05', 'duration' => 'PT2H49M',
            'director' => [['@type' => 'Person', 'name' => 'Christopher Nolan']], 'url' => $movie->canonicalUrl(),
        ])
        ->and($head['jsonLd'])->not->toHaveKey('aggregateRating');
    $page->assertNoJavaScriptErrors();
});

it('shares the poster when a Movie has no backdrop, and the static card when it has neither', function () {
    $poster = Movie::factory()->create(['title' => 'Poster Only', 'slug' => 'poster-only', 'backdrop_path' => null, 'poster_path' => '/poster.jpg']);
    $neither = Movie::factory()->create(['title' => 'Bare', 'slug' => 'bare', 'backdrop_path' => null, 'poster_path' => null, 'overview' => null]);
    fakeCatalogKnowing(sourceDetailsFor($poster), sourceDetailsFor($neither));

    expect(headMetadata(visitWithCsp($poster->canonicalUrl()))['ogImage'])->toEndWith('/w780/poster.jpg');

    $head = headMetadata(visitWithCsp($neither->canonicalUrl()));
    expect($head['ogImage'])->toEndWith('/og-card.png')->and($head['description'])->toBeString()->not->toBeEmpty();
});

it('describes Discover with its fixed description, the static card and its own URL as canonical', function () {
    fakeDiscoverSource(['trending' => [movieListings(1, 5)]]);

    $head = headMetadata(visitWithCsp(route('home')));

    expect($head['title'])->toBe('Discover · Reelio')
        ->and($head['description'])->toContain('Watchlist')
        ->and($head['canonical'])->toBe(route('home'))
        ->and($head['ogImage'])->toEndWith('/og-card.png')
        ->and($head['jsonLd'])->toBeNull();
});
