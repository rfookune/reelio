import type { UserMovies } from '@/types';

type MovieRemovedData = {
    tmdbId: number;
    userMovies: UserMovies;
};

/** The Removed Movie controls belong only to this exception, regardless of status or other error payloads. */
export function removedMovieData(
    props: Record<string, unknown>,
): MovieRemovedData | undefined {
    return props.exceptionClass === 'App\\Http\\Exceptions\\MovieRemoved'
        ? (props.data as MovieRemovedData | undefined)
        : undefined;
}
