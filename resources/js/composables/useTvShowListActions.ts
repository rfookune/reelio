import { router, useHttp, usePage } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    update,
    destroy,
} from '@/actions/App/Http/Controllers/TvShowWatchedController';
import { destroy as removeWatchlist } from '@/actions/App/Http/Controllers/TvShowWatchlistController';
import { errorPageTvShowData } from '@/lib/errorPages';
import undoRoute from '@/actions/App/Http/Controllers/TvShowUndoController';

type Result = { undoToken?: string; redirect?: string };

const FAILURE_MESSAGE = "Couldn't save that. Try again.";

/**
 * Marks or clears a whole TV Show from a Watchlist or Watched card, by catalog id, and offers one Undo. The page's
 * `userTvShows` is reloaded after each, so the card leaves the list or comes back as the server now has it.
 */
export function useTvShowListActions(
    source: () => 'watchlist' | 'watched' | 'tv-show' = () => 'tv-show',
) {
    const page = usePage();
    const busy = ref(false);
    const http = useHttp<
        { source: string; episode?: string; message?: string },
        Result
    >({ source: 'tv-show' });
    const undoHttp = useHttp<{ token: string; undo: string }, Result>({
        token: '',
        undo: '',
    });

    /** Reloads the facts, and puts the focus on the menu of the card they bring back, when one is named. */
    function reloadFacts(restoredTmdbId?: number): void {
        router.reload({
            only:
                page.component === 'Error'
                    ? ['data', 'exceptionClass']
                    : ['userTvShows'],
            onHttpException: (response) => {
                const result = response.data as {
                    component?: string;
                    props?: Record<string, unknown>;
                };
                if (
                    errorPageTvShowData(page.props) &&
                    [410, 503].includes(response.status) &&
                    result.component === 'Error' &&
                    errorPageTvShowData(result.props ?? {})?.tmdbId ===
                        errorPageTvShowData(page.props)?.tmdbId
                )
                    return;
                toast.error(FAILURE_MESSAGE);
                return false;
            },
            async: true,
            onFinish: () => {
                if (restoredTmdbId !== undefined) {
                    void nextTick(() => {
                        document
                            .querySelector<HTMLElement>(
                                `[data-card-key="tv:${restoredTmdbId}"] [data-slot="movie-menu"]`,
                            )
                            ?.focus();
                    });
                }
            },
        });
    }

    async function undo(tmdbId: number, token: string): Promise<void> {
        if (busy.value) {
            return;
        }

        busy.value = true;
        let undone = false;

        try {
            undoHttp.token = token;
            await undoHttp.post(undoRoute.url({ tmdbId }));
            undone = true;
            toast.success('Action undone');
        } catch {
            toast.error(
                undoHttp.errors.undo ?? "Couldn't undo that. Try again.",
            );
        } finally {
            busy.value = false;
            reloadFacts(undone ? tmdbId : undefined);
        }
    }

    async function change(
        tmdbId: number,
        operation: 'mark-watched' | 'clear-watched' | 'remove-watchlist',
        message: string,
    ): Promise<boolean> {
        if (busy.value) {
            return false;
        }

        busy.value = true;

        try {
            http.source = source();
            const route = { tmdbId };
            const result =
                operation === 'remove-watchlist'
                    ? await http.delete(removeWatchlist.url(route))
                    : operation === 'mark-watched'
                      ? await http.put(update.url(route))
                      : await http.delete(destroy.url(route));

            if (result.undoToken) {
                const token = result.undoToken;
                toast.success(message, {
                    action: {
                        label: 'Undo',
                        onClick: () => {
                            void undo(tmdbId, token);
                        },
                    },
                });
            }

            reloadFacts();

            return true;
        } catch {
            toast.error(
                http.errors.episode ?? http.errors.message ?? FAILURE_MESSAGE,
            );

            return false;
        } finally {
            busy.value = false;
        }
    }

    return {
        busy,
        markAsWatched: (tmdbId: number) =>
            change(tmdbId, 'mark-watched', 'TV show marked as watched'),
        removeWatchlist: (tmdbId: number) =>
            change(tmdbId, 'remove-watchlist', 'Removed from your Watchlist'),
        clearWatched: (tmdbId: number) =>
            change(tmdbId, 'clear-watched', 'TV show cleared'),
    };
}
