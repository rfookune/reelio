import type { UserMovies } from '@/types';

type ErrorPageMovieData = {
    tmdbId: number;
    userMovies: UserMovies;
};

/** The error pages that are about one stored Movie and carry the viewer's facts about it. */
const MOVIE_ERROR_PAGES = [
    'App\\Http\\Exceptions\\MovieRemoved',
    'App\\Http\\Exceptions\\DetailsUnavailable',
];

/**
 * The Movie an error page is about, for its list controls: a Removed Movie's 410, or the 503 of a Movie with no
 * details while its refresh fails. Only these exceptions carry it, regardless of status or other error payloads; a
 * 503 about no stored Movie carries none.
 */
export function errorPageMovieData(
    props: Record<string, unknown>,
): ErrorPageMovieData | undefined {
    return MOVIE_ERROR_PAGES.includes(String(props.exceptionClass)) &&
        props.data
        ? (props.data as ErrorPageMovieData)
        : undefined;
}
