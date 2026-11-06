import type { UrlMethodPair } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import * as watched from '@/routes/movies/watched';
import * as watchlist from '@/routes/movies/watchlist';
import type {
    UserMovieActionSource,
    UserMovieFacts,
    UserMovies,
} from '@/types';

const TOAST_DURATION = 6000;
const FAILURE_MESSAGE = "Couldn't save that. Try again.";
const NO_FACTS: UserMovieFacts = {
    watchlisted: false,
    watched: false,
    watchedDate: null,
};

/**
 * Sends one Watchlist or Watched action for a Movie, by catalog id.
 *
 * For a signed-in user the visit is `async`, so a second action never cancels one in flight. It updates the
 * page's `userMovies` entry optimistically with what the action will produce, reloads only `userMovies`, and on
 * any failure Inertia rolls the entry back while an error toast explains. A guest's visit is sent plainly, so
 * sign-in takes over the page. The request never carries dates: the server sets them.
 */
function sendUserMovieAction({
    tmdbId,
    route,
    source,
    produce,
    successMessage,
}: {
    tmdbId: number;
    route: UrlMethodPair;
    source: UserMovieActionSource;
    /** The Movie's facts once the action succeeds, shown until the server answers. */
    produce: (facts: UserMovieFacts) => UserMovieFacts;
    successMessage: string;
}): void {
    const page = usePage();

    if (page.props.auth.user === null) {
        router.visit(route, { data: { source } });

        return;
    }

    const component = page.component;
    const failed = (): false => {
        toast.error(FAILURE_MESSAGE, { duration: TOAST_DURATION });

        return false;
    };

    router.visit(route, {
        data: { source },
        async: true,
        only: ['userMovies'],
        preserveScroll: true,
        preserveState: true,
        optimistic: (props) => {
            const userMovies = props.userMovies as UserMovies;

            return {
                userMovies: {
                    ...userMovies,
                    [tmdbId]: produce(userMovies[tmdbId] ?? NO_FACTS),
                },
            };
        },
        onSuccess: (response) => {
            if (response.component === component) {
                toast.success(successMessage, { duration: TOAST_DURATION });
            }
        },
        onError: failed,
        onHttpException: failed,
        onNetworkError: failed,
    });
}

/** Puts a Movie on the Watchlist or takes it off. */
export function setWatchlist(
    tmdbId: number,
    watchlisted: boolean,
    source: UserMovieActionSource,
): void {
    sendUserMovieAction({
        tmdbId,
        route: watchlisted
            ? watchlist.update(tmdbId)
            : watchlist.destroy(tmdbId),
        source,
        produce: (facts) => ({ ...facts, watchlisted }),
        successMessage: watchlisted
            ? 'Added to your Watchlist'
            : 'Removed from your Watchlist',
    });
}

/** Marks a Movie as watched today, which takes it off the Watchlist. */
export function markAsWatched(
    tmdbId: number,
    source: UserMovieActionSource,
): void {
    sendUserMovieAction({
        tmdbId,
        route: watched.update(tmdbId),
        source,
        produce: () => ({
            watchlisted: false,
            watched: true,
            watchedDate: new Date().toISOString(),
        }),
        successMessage: 'Marked as watched',
    });
}

/** Clears a Movie's Watched record, leaving the Watchlist alone. */
export function clearWatched(
    tmdbId: number,
    source: UserMovieActionSource,
): void {
    sendUserMovieAction({
        tmdbId,
        route: watched.destroy(tmdbId),
        source,
        produce: (facts) => ({ ...facts, watched: false, watchedDate: null }),
        successMessage: 'Cleared from Watched',
    });
}
