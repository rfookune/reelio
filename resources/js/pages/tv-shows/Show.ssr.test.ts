import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import type { ExternalRating } from '@/components/ExternalRatings.vue';
import type { CastMember } from '@/components/movie/CastGrid.vue';
import Show from '@/pages/tv-shows/Show.vue';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({
        props: { seo: { card: 'https://reelio.test/og-card.png' } },
    }),
    Head: { render: () => null },
}));

async function renderTvShow(
    ratings: ExternalRating[] = [],
    cast: CastMember[] = [],
    castCount = cast.length,
): Promise<string> {
    return renderToString(
        createSSRApp({
            render: () =>
                h(Show, {
                    watchlisted: false,
                    progress: {
                        state: 'Not started',
                        total: 0,
                        watched: 0,
                        defaultOpenSeasonId: null,
                        seasons: [],
                    },
                    canonicalUrl: 'https://reelio.test/tv-shows/chernobyl-1',
                    castUrl: 'https://reelio.test/tv-shows/chernobyl-1/casts',
                    imageBaseUrl: 'https://image.tmdb.org/t/p',
                    catalogStatus: 'fresh',
                    whereToWatch: {
                        country: 'US',
                        countryName: 'United States',
                        countryUnsupported: false,
                        countries: [{ code: 'US', name: 'United States' }],
                        groups: [],
                        updated: 'Updated 8 days ago',
                        outOfDate: true,
                    },
                    tvShow: {
                        id: 1,
                        tmdbId: 87108,
                        title: 'Chernobyl',
                        originalTitle: null,
                        originalLanguage: 'en',
                        overview: null,
                        tagline: null,
                        posterPath: null,
                        backdropPath: null,
                        firstAirDate: '2019-05-06',
                        lastAirDate: '2019-06-03',
                        status: 'Ended',
                        seasonCount: 1,
                        episodeCount: 5,
                        creators: [],
                        networks: [],
                        genres: [],
                        certification: null,
                        cast,
                        castCount,
                        trailer: null,
                        detailsUnavailable: false,
                        ratings,
                    },
                }),
        }),
    );
}

describe('TV Show ratings and Availability rendered on the server', () => {
    it('shows the TV watch link, country, freshness, JustWatch credit and TV-specific empty message', async () => {
        const html = await renderTvShow();
        expect(html).toContain('Where to watch');
        expect(html).toContain('United States');
        expect(html).toContain('JustWatch');
        expect(html).toContain('Updated 8 days ago');
        expect(html).toContain('may be out of date');
        expect(html).toContain('streaming information for this TV show');
        expect(html).toContain('/tv-shows/tmdb/87108/watch?country=US');
        expect(html).toContain('rel="nofollow noopener noreferrer"');
        expect(html).toContain('Not yet rated');
    });

    it('displays both scores with their vote counts and IMDb permission credit', async () => {
        const html = await renderTvShow([
            { source: 'imdb', name: 'IMDb', value: 9.3, voteCount: 900000 },
            { source: 'tmdb', name: 'TMDB', value: 8.5, voteCount: 100 },
        ]);
        expect(html).toContain('9.3');
        expect(html).toContain('900K');
        expect(html).toContain('8.5');
        expect(html).toContain('100');
        expect(html).toContain('TMDB');
        expect(html).toContain('Information courtesy of IMDb');
        expect(html).toContain('Used with permission.');
    });
});

describe('TV Show cast rendered on the server', () => {
    const member = (id: number, episodeCount: number): CastMember => ({
        id,
        name: `Actor ${id}`,
        character: `Role ${id}`,
        profilePath: null,
        episodeCount,
    });

    it('shows each role with the total Episode count and links the full cast only when more are billed', async () => {
        const twelve = Array.from({ length: 12 }, (_, i) => member(i + 1, 1));
        twelve[1] = member(2, 42);

        const exact = await renderTvShow([], twelve, 12);
        expect(exact).toContain('Role 1 · 1 episode<');
        expect(exact).toContain('Role 2 · 42 episodes');
        expect(exact).not.toContain('data-slot="full-cast-link"');

        const more = await renderTvShow([], twelve, 13);
        expect(more).toContain('data-slot="full-cast-link"');
        expect(more).toContain('Full cast (13)');
        expect(more).toContain(
            'href="https://reelio.test/tv-shows/chernobyl-1/casts"',
        );
    });
});
