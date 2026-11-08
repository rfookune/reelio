import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import type { Component } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import Show from '@/pages/movies/Show.vue';

/* The Inertia page and head manager belong to the app around the page, which the SSR server supplies. */
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({
        props: {
            name: 'Reelio',
            auth: { user: null },
            seo: {
                url: 'https://reelio.test/',
                card: 'https://reelio.test/og-card.png',
            },
        },
    }),
    Head: { render: () => null },
}));

/** Renders the Movie page the way the SSR server does, with the props the controller sends. */
async function renderMovie(): Promise<string> {
    const props = {
        canonicalUrl: 'https://reelio.test/movies/interstellar-1',
        castUrl: 'https://reelio.test/movies/interstellar-1/casts',
        imageBaseUrl: 'https://image.tmdb.org/t/p',
        whereToWatch: { groups: [], country: 'US', unavailable: false },
        userMovies: {},
        movie: {
            id: 1,
            tmdbId: 157336,
            title: 'Interstellar',
            originalTitle: null,
            originalLanguage: 'en',
            year: 2014,
            releaseDate: '2014-11-05',
            runtime: 169,
            tagline: null,
            overview: 'A journey beyond the stars.',
            posterPath: null,
            backdropPath: null,
            genres: ['Science Fiction'],
            directors: ['Christopher Nolan'],
            writers: ['Jonathan Nolan'],
            cast: [
                {
                    id: 1,
                    name: 'Matthew McConaughey',
                    character: 'Cooper',
                    profilePath: null,
                },
                {
                    id: 2,
                    name: 'Anne Hathaway',
                    character: 'Brand',
                    profilePath: null,
                },
            ],
            castCount: 2,
            certification: 'PG-13',
            usReleaseDate: '2014-11-05',
            ratings: [],
            trailer: null,
            detailsUnavailable: false,
        },
    };

    return renderToString(
        createSSRApp({ render: () => h(Show as Component, props) }),
    );
}

describe('the Movie page rendered on the server', () => {
    it('contains the cast and the Director', async () => {
        const html = await renderMovie();

        expect(html).toContain('Matthew McConaughey');
        expect(html).toContain('Anne Hathaway');
        expect(html).toContain('Christopher Nolan');
    });
});
