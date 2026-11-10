import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import PosterCard from '@/components/shared/PosterCard.vue';
import type { PosterCardTitle } from '@/components/shared/PosterCard.vue';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ props: { auth: { user: null } } }),
    Link: { render: () => null },
}));

const card: PosterCardTitle = {
    tmdbId: 1,
    title: 'Heat',
    year: 1995,
    posterPath: null,
    ratings: [],
    href: '/movies/heat/1',
};

function render(
    movie: PosterCardTitle,
    props: { showBookmark?: boolean; tvWatchlisted?: boolean } = {},
): Promise<string> {
    return renderToString(
        createSSRApp({
            render: () =>
                h(PosterCard, {
                    imageBaseUrl: 'https://image.test',
                    movie,
                    facts: undefined,
                    source: 'watchlist',
                    showBookmark: false,
                    ...props,
                }),
        }),
    );
}

describe('PosterCard', () => {
    it('shows "Your rating" when the card carries one', async () => {
        const html = await render({ ...card, userRating: 7 });

        expect(html).toContain('data-slot="user-rating"');
        expect(html).toContain('Your rating');
        expect(html).toContain('>7<');
    });

    it.each([null, undefined])(
        'shows nothing when unrated (%s)',
        async (userRating) => {
            const html = await render({ ...card, userRating });

            expect(html).not.toContain('user-rating');
            expect(html).not.toContain('Your rating');
        },
    );

    describe('Watchlist toggle on a TV Show', () => {
        const tvShow: PosterCardTitle = { ...card, kind: 'tv', title: 'Dark' };

        it('is offered once the card is given its Watchlist state', async () => {
            const off = await render(tvShow, {
                showBookmark: true,
                tvWatchlisted: false,
            });
            const on = await render(tvShow, {
                showBookmark: true,
                tvWatchlisted: true,
            });

            expect(off).toContain('aria-label="Add Dark to Watchlist"');
            expect(off).toContain('aria-pressed="false"');
            expect(on).toContain('aria-label="Remove Dark from Watchlist"');
            expect(on).toContain('aria-pressed="true"');
        });

        it('is absent without a Watchlist state, as on the lists', async () => {
            const html = await render(tvShow, { showBookmark: true });

            expect(html).not.toContain('bookmark-toggle');
        });
    });
});
