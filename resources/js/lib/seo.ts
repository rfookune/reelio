import { isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';

/** The longest meta description, in characters. */
export const DESCRIPTION_LIMIT = 155;

/** The fixed description of the Discover page. */
export const DISCOVER_DESCRIPTION =
    'Discover movies worth watching, see where to stream them in your country, and keep a Watchlist of what to watch next.';

/** The title of a Movie page before the site name: "Title (year)". */
export function movieTitle(title: string | null, year: number | null): string {
    const name = title ?? 'Movie';

    return year === null ? name : `${name} (${year})`;
}

/**
 * A Movie's meta description: its overview, cut to about 155 characters at a word boundary with an ellipsis, or the
 * fallback line when there is no overview.
 */
export function describeMovie(
    overview: string | null,
    fallback: string,
): string {
    const text = (overview ?? '').replace(/\s+/g, ' ').trim();

    if (text === '') {
        return fallback;
    }

    if (text.length <= DESCRIPTION_LIMIT) {
        return text;
    }

    return `${cutAtWord(text, DESCRIPTION_LIMIT - 1).replace(/[\s,;:\-–—]+$/, '')}…`;
}

/** The text cut to at most `length` characters at a word boundary, or at `length` when its first word is longer. */
function cutAtWord(text: string, length: number): string {
    const room = text.slice(0, length);

    if (text[length] === ' ') {
        return room;
    }

    const boundary = room.lastIndexOf(' ');

    return boundary > 0 ? room.slice(0, boundary) : room;
}

/** The image for Open Graph and Twitter cards: the backdrop at w1280, else the poster, else the static card. */
export function shareImage(
    imageBaseUrl: string,
    backdropPath: string | null,
    posterPath: string | null,
    fallback: string,
): string {
    if (isSafeTmdbPath(backdropPath)) {
        return tmdbImageUrl(imageBaseUrl, 1280, backdropPath);
    }

    if (isSafeTmdbPath(posterPath)) {
        return tmdbImageUrl(imageBaseUrl, 780, posterPath);
    }

    return fallback;
}

export type MovieJsonLdInput = {
    name: string;
    datePublished: string | null;
    directors: string[];
    genres: string[];
    runtime: number | null;
    image: string;
    description: string | null;
    url: string;
};

/** schema.org `Movie`, leaving out what is unknown. It never carries an `aggregateRating`. */
export function movieJsonLd(movie: MovieJsonLdInput): Record<string, unknown> {
    return {
        '@context': 'https://schema.org',
        '@type': 'Movie',
        name: movie.name,
        ...(movie.datePublished === null
            ? {}
            : { datePublished: movie.datePublished }),
        ...(movie.directors.length === 0
            ? {}
            : {
                  director: movie.directors.map((name) => ({
                      '@type': 'Person',
                      name,
                  })),
              }),
        ...(movie.genres.length === 0 ? {} : { genre: movie.genres }),
        ...(movie.runtime === null || movie.runtime <= 0
            ? {}
            : { duration: isoDuration(movie.runtime) }),
        image: movie.image,
        ...(movie.description === null
            ? {}
            : { description: movie.description }),
        url: movie.url,
    };
}

function isoDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return `PT${hours > 0 ? `${hours}H` : ''}${rest > 0 ? `${rest}M` : ''}`;
}

/** JSON for a `<script type="application/ld+json">`, with `<` escaped so the data can't close the tag. */
export function serializeJsonLd(data: Record<string, unknown>): string {
    return JSON.stringify(data).replace(
        /[<\u2028\u2029]/g,
        (character) =>
            `\\u${character.charCodeAt(0).toString(16).padStart(4, '0')}`,
    );
}
