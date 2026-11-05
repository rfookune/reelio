/**
 * PROTOTYPE, throwaway. In-memory state shared by every shell variant.
 * Nothing persists: reloading the page resets the Watchlist.
 */
import { reactive } from 'vue';
import { toast } from 'vue-sonner';
import type { MockMovie } from './mockMovies';
import { initialWatchlistIds } from './mockMovies';

export type Screen =
    | 'discover'
    | 'search'
    | 'watchlist'
    | 'watched'
    | 'profile';

export type SkeletonStyle = 'off' | 'pulse' | 'shimmer';

const state = reactive({
    watchlist: new Set<number>(initialWatchlistIds),
    screen: 'discover' as Screen,
});

function isOnWatchlist(movie: MockMovie): boolean {
    return state.watchlist.has(movie.id);
}

function toggleWatchlist(movie: MockMovie): void {
    const wasOnWatchlist = isOnWatchlist(movie);

    if (wasOnWatchlist) {
        state.watchlist.delete(movie.id);
    } else {
        state.watchlist.add(movie.id);
    }

    toast(
        wasOnWatchlist
            ? `Removed ${movie.title} from your Watchlist`
            : `Added ${movie.title} to your Watchlist`,
        {
            action: {
                label: 'Undo',
                onClick: () => {
                    if (wasOnWatchlist) {
                        state.watchlist.add(movie.id);
                    } else {
                        state.watchlist.delete(movie.id);
                    }
                },
            },
        },
    );
}

function go(screen: Screen): void {
    state.screen = screen;
    window.scrollTo({ top: 0 });
}

export function usePrototypeState() {
    return { state, isOnWatchlist, toggleWatchlist, go };
}
