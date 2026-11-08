import { describe, expect, it } from 'vitest';
import {
    describeMovie,
    movieJsonLd,
    movieTitle,
    serializeJsonLd,
    shareImage,
} from '@/lib/seo';

describe('movieTitle', () => {
    it('puts the year in brackets after the title', () => {
        expect(movieTitle('Avengers: Doomsday', 2026)).toBe(
            'Avengers: Doomsday (2026)',
        );
    });

    it('drops the year, or falls back to "Movie", when they are unknown', () => {
        expect(movieTitle('Interstellar', null)).toBe('Interstellar');
        expect(movieTitle(null, 2014)).toBe('Movie (2014)');
        expect(movieTitle(null, null)).toBe('Movie');
    });
});

describe('describeMovie', () => {
    it('keeps an overview that already fits', () => {
        expect(describeMovie('A short one.', 'Fallback')).toBe('A short one.');
    });

    it('cuts a long overview at a word boundary, within 155 characters', () => {
        const overview = 'word '.repeat(60).trim();

        const description = describeMovie(overview, 'Fallback');

        expect(description.length).toBeLessThanOrEqual(155);
        expect(description.endsWith('…')).toBe(true);
        expect(
            description
                .slice(0, -1)
                .split(' ')
                .every((w) => w === 'word'),
        ).toBe(true);
    });

    it('never ends the cut on a dangling comma or colon', () => {
        const overview = `${'a'.repeat(150)}, and then more words follow here`;

        expect(describeMovie(overview, 'Fallback')).not.toMatch(/[,;:]…$/);
    });

    it('cuts a first word longer than the limit where it stands', () => {
        const description = describeMovie('a'.repeat(300), 'Fallback');

        expect(description).toBe(`${'a'.repeat(154)}…`);
    });

    it('collapses whitespace, and uses the fallback without an overview', () => {
        expect(describeMovie('Two\n\n  lines', 'Fallback')).toBe('Two lines');
        expect(describeMovie(null, 'Fallback')).toBe('Fallback');
        expect(describeMovie('   ', 'Fallback')).toBe('Fallback');
    });
});

describe('shareImage', () => {
    const base = 'https://image.tmdb.org/t/p';

    it('prefers the backdrop at w1280, then the poster, then the static card', () => {
        expect(
            shareImage(
                base,
                '/back.jpg',
                '/post.jpg',
                'https://x.test/card.png',
            ),
        ).toBe('https://image.tmdb.org/t/p/w1280/back.jpg');
        expect(
            shareImage(base, null, '/post.jpg', 'https://x.test/card.png'),
        ).toBe('https://image.tmdb.org/t/p/w780/post.jpg');
        expect(shareImage(base, null, null, 'https://x.test/card.png')).toBe(
            'https://x.test/card.png',
        );
    });

    it('ignores a path that is not a safe TMDB path', () => {
        expect(
            shareImage(base, '/../x', '/ok.jpg', 'https://x.test/card.png'),
        ).toBe('https://image.tmdb.org/t/p/w780/ok.jpg');
    });
});

describe('movieJsonLd', () => {
    const movie = {
        name: 'Interstellar',
        datePublished: '2014-11-05',
        directors: ['Christopher Nolan'],
        genres: ['Adventure', 'Science Fiction'],
        runtime: 169,
        image: 'https://image.tmdb.org/t/p/w1280/back.jpg',
        description: 'A journey beyond the stars.',
        url: 'https://reelio.test/movies/interstellar-1',
    };

    it('describes the Movie with the schema.org properties and no rating', () => {
        expect(movieJsonLd(movie)).toEqual({
            '@context': 'https://schema.org',
            '@type': 'Movie',
            name: 'Interstellar',
            datePublished: '2014-11-05',
            director: [{ '@type': 'Person', name: 'Christopher Nolan' }],
            genre: ['Adventure', 'Science Fiction'],
            duration: 'PT2H49M',
            image: 'https://image.tmdb.org/t/p/w1280/back.jpg',
            description: 'A journey beyond the stars.',
            url: 'https://reelio.test/movies/interstellar-1',
        });
        expect(JSON.stringify(movieJsonLd(movie))).not.toContain(
            'aggregateRating',
        );
    });

    it('leaves out what is unknown', () => {
        const sparse = movieJsonLd({
            ...movie,
            datePublished: null,
            directors: [],
            genres: [],
            runtime: null,
            description: null,
        });

        expect(Object.keys(sparse)).toEqual([
            '@context',
            '@type',
            'name',
            'image',
            'url',
        ]);
    });

    it('formats whole-hour and under-hour runtimes', () => {
        expect(movieJsonLd({ ...movie, runtime: 120 }).duration).toBe('PT2H');
        expect(movieJsonLd({ ...movie, runtime: 45 }).duration).toBe('PT45M');
    });
});

describe('serializeJsonLd', () => {
    it('cannot close the script tag it is written into', () => {
        const json = serializeJsonLd({
            name: '</script><script>alert(1)</script>',
        });

        expect(json).not.toContain('<');
        expect(JSON.parse(json).name).toBe(
            '</script><script>alert(1)</script>',
        );
    });
});
