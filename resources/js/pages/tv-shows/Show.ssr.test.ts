import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import type { ExternalRating } from '@/components/ExternalRatings.vue';
import type { CastMember } from '@/components/casts/CastGrid.vue';
import type { WatchedProgress } from '@/composables/useTvShowActions';
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
    progress: WatchedProgress = {
        state: 'Not started',
        total: 0,
        watched: 0,
        defaultOpenSeasonId: null,
        seasons: [],
    },
): Promise<string> {
    return renderToString(
        createSSRApp({
            render: () =>
                h(Show, {
                    watchlisted: false,
                    progress,
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

describe('TV Show progress, Seasons and Episodes rendered on the server', () => {
    const episode = (id: number, number: number, watched: boolean) => ({
        id,
        number,
        name: `Title ${number}`,
        overview: 'A spoiler.',
        airDate: '2019-05-06',
        runtime: 60,
        markable: true,
        watchedDate: watched ? '2026-01-01' : null,
    });
    const inProgress: WatchedProgress = {
        state: 'In progress',
        total: 3,
        watched: 1,
        defaultOpenSeasonId: 1,
        seasons: [
            {
                id: 1,
                number: 1,
                name: null,
                total: 3,
                watched: 1,
                episodes: [
                    episode(1, 1, true),
                    episode(2, 2, false),
                    episode(3, 3, false),
                ],
            },
        ],
    };
    const toggle = (html: string, label: string): string =>
        html.match(
            new RegExp(
                `<button[^>]*aria-label="${label}[^"]*"[^>]*>(.*?)</button>`,
            ),
        )?.[1] ?? '';

    it('leads with the next Episode to watch and its one-tap Mark watched', async () => {
        const html = await renderTvShow([], [], 0, inProgress);
        expect(html).toContain('Up next');
        expect(html).toContain('S1·E2');
        expect(html).toContain('Title 2');
        expect(html).toContain('data-slot="up-next-mark"');
        expect(html).toContain('1 of 3 Episodes watched');
    });

    it('has no Up next once the TV Show is Completed', async () => {
        const html = await renderTvShow([], [], 0, {
            ...inProgress,
            state: 'Completed',
            watched: 3,
            seasons: inProgress.seasons.map((season) => ({
                ...season,
                watched: 3,
                episodes: season.episodes.map((e) => ({
                    ...e,
                    watchedDate: '2026-01-01',
                })),
            })),
        });
        expect(html).not.toContain('Up next');
        expect(html).toContain('data-slot="completed-chip"');
    });

    it('shows each Season header with a horizontal progress bar and the bulk actions beside its toggle', async () => {
        const html = await renderTvShow([], [], 0, inProgress);
        expect(html).toMatch(/<progress[^>]*aria-label="Season 1 watched"/);
        expect(html).not.toContain('<circle');
        const seasonToggle = html.match(
            /<button[^>]*data-slot="season-toggle"[^>]*>(.*?)<\/button>/,
        );
        expect(seasonToggle?.[0]).toContain('aria-expanded="true"');
        expect(seasonToggle?.[1]).toContain('Season 1');
        expect(seasonToggle?.[1]).not.toContain('data-slot="season-mark"');
        expect(html).toMatch(
            /data-slot="season-mark"[^>]*aria-label="Mark watched, Season 1"[^>]*>(?:<!--\[-->)?\s*Mark watched\s*(?:<!--\]-->)?</,
        );
        expect(html).toMatch(
            /data-slot="season-clear"[^>]*aria-label="Clear all, Season 1"[^>]*>(?:<!--\[-->)?\s*Clear all\s*(?:<!--\]-->)?</,
        );
    });

    it('shows an upcoming Season with no Episodes as a plain card with no toggle', async () => {
        const html = await renderTvShow([], [], 0, {
            ...inProgress,
            seasons: [
                ...inProgress.seasons,
                {
                    id: 2,
                    number: 2,
                    name: null,
                    total: 0,
                    watched: 0,
                    episodes: [],
                },
            ],
        });
        expect(html).toContain('Season 2');
        expect(html).toContain('Upcoming');
        expect(html.match(/data-slot="season-toggle"/g)).toHaveLength(1);
        expect(html.match(/data-slot="season-mark"/g)).toHaveLength(1);
    });

    it('shows the Episode number when unwatched and a check when watched', async () => {
        const html = await renderTvShow([], [], 0, inProgress);
        const watched = toggle(html, 'Episode 1:');
        const unwatched = toggle(html, 'Episode 2:');
        expect(watched).toContain('<svg');
        expect(watched).not.toContain('>1<');
        expect(unwatched).toContain('>2<');
        expect(unwatched).not.toContain('<svg');
        expect(html).toContain('aria-pressed="true"');
    });

    it('shows Episode overviews as plain text with no disclosure', async () => {
        const html = await renderTvShow([], [], 0, inProgress);
        expect(html).toContain('A spoiler.');
        expect(html).not.toContain('Episode overview');
        expect(html).not.toContain('<details');
    });
});
