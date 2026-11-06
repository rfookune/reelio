import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { destroy, update } from '@/routes/movies/watchlist';
import type { UserMovieActionSource, UserMovies } from '@/types';

const TOAST_DURATION = 6000;
const FAILURE_MESSAGE = "Couldn't save that. Try again.";

/**
 * Puts a Movie on the Watchlist or takes it off, by catalog id.
 *
 * For a signed-in user the visit is `async`, so a second action never cancels one in flight. It updates the
 * page's `userMovies` entry optimistically, reloads only `userMovies`, and on any failure Inertia rolls the
 * entry back while an error toast explains. A guest's visit is sent plainly, so sign-in takes over the page.
 */
export function setWatchlist(
    tmdbId: number,
    watchlisted: boolean,
    source: UserMovieActionSource,
): void {
    const route = watchlisted ? update(tmdbId) : destroy(tmdbId);
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
                    [tmdbId]: {
                        ...(userMovies[tmdbId] ?? { watched: false }),
                        watchlisted,
                    },
                },
            };
        },
        onSuccess: (response) => {
            if (response.component !== component) {
                return;
            }

            toast.success(
                watchlisted
                    ? 'Added to your Watchlist'
                    : 'Removed from your Watchlist',
                { duration: TOAST_DURATION },
            );
        },
        onError: failed,
        onHttpException: failed,
        onNetworkError: failed,
    });
}
