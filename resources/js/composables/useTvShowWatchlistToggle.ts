import { router, useHttp, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { toast } from 'vue-sonner';
import {
    update as addToWatchlist,
    destroy as removeFromWatchlist,
} from '@/actions/App/Http/Controllers/TvShowWatchlistController';
import undoRoute from '@/actions/App/Http/Controllers/TvShowUndoController';

/** The `userTvShows` page prop of a page with a Watchlist toggle on its TV Show cards, keyed by catalog id. */
export type TvShowWatchlistFacts = Record<string, { watchlisted: boolean }>;

/** The surfaces carrying the toggle, which the server records as the action's source. */
type Source = 'search' | 'watchlist' | 'watched';

type Result = { watchlisted: boolean; undoToken?: string; redirect?: string };

const FAILURE_MESSAGE = "Couldn't save that. Try again.";

/**
 * The Watchlist toggle on a card of a TV Show, by catalog id. The page's `userTvShows` entry changes at once, keeping
 * its other facts, and
 * rolls back, with a toast, if the server refuses; the success toast offers Undo. A guest is sent to sign in, and the
 * server applies the action afterwards. One action per TV Show runs at a time.
 */
export function useTvShowWatchlistToggle(source: () => Source) {
    const page = usePage();
    const busy = new Set<number>();
    const http = useHttp<{ source: string }, Result>({ source: source() });
    const undoHttp = useHttp<{ token: string; undo: string }, Result>({
        token: '',
        undo: '',
    });

    function show(tmdbId: number, watchlisted: boolean): void {
        router.replace({
            props: (current) => {
                const facts = (current.userTvShows ??
                    {}) as TvShowWatchlistFacts;

                return {
                    ...current,
                    userTvShows: {
                        ...facts,
                        [tmdbId]: { ...facts[tmdbId], watchlisted },
                    },
                };
            },
            preserveScroll: true,
            preserveState: true,
        });
    }

    function offerUndo(tmdbId: number, token: string, message: string): void {
        toast.success(message, {
            duration: 6000,
            action: { label: 'Undo', onClick: () => void undo(tmdbId, token) },
        });
    }

    async function undo(tmdbId: number, token: string): Promise<void> {
        if (busy.has(tmdbId)) {
            return;
        }

        busy.add(tmdbId);

        try {
            undoHttp.token = token;
            const result = await undoHttp.post(undoRoute.url({ tmdbId }));
            show(tmdbId, result.watchlisted);
            toast.success('Action undone');
        } catch {
            toast.error(
                undoHttp.errors.undo ?? "Couldn't undo that. Try again.",
            );
        } finally {
            busy.delete(tmdbId);
        }
    }

    async function toggle(tmdbId: number, watchlisted: boolean): Promise<void> {
        if (busy.has(tmdbId)) {
            return;
        }

        busy.add(tmdbId);
        const isGuest = page.props.auth.user === null;

        if (!isGuest) {
            show(tmdbId, watchlisted);
        }

        try {
            http.source = source();
            const args = { tmdbId };
            const result = watchlisted
                ? await http.put(addToWatchlist.url(args))
                : await http.delete(removeFromWatchlist.url(args));

            if (result.redirect) {
                router.visit(result.redirect);

                return;
            }

            show(tmdbId, result.watchlisted);

            if (result.undoToken) {
                offerUndo(
                    tmdbId,
                    result.undoToken,
                    watchlisted
                        ? 'Added to your Watchlist'
                        : 'Removed from your Watchlist',
                );
            }
        } catch {
            if (!isGuest) {
                show(tmdbId, !watchlisted);
            }

            toast.error(FAILURE_MESSAGE);
        } finally {
            busy.delete(tmdbId);
        }
    }

    /** A guest's pending action, applied at sign-in, comes back as a flash naming the TV Show it was for. */
    onMounted(() => {
        const flash = page.flash.tvWatchlistAction as
            | { tmdbId: number; undoToken: string; watchlisted: boolean }
            | undefined;

        if (flash) {
            offerUndo(
                flash.tmdbId,
                flash.undoToken,
                flash.watchlisted
                    ? 'Added to your Watchlist'
                    : 'Removed from your Watchlist',
            );
        }
    });

    return { toggle };
}
