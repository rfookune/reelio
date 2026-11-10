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

function render(movie: PosterCardTitle): Promise<string> {
    return renderToString(
        createSSRApp({
            render: () =>
                h(PosterCard, {
                    imageBaseUrl: 'https://image.test',
                    movie,
                    facts: undefined,
                    source: 'watchlist',
                    showBookmark: false,
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
});
