import { router, useHttp, usePage } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    update,
    destroy,
} from '@/actions/App/Http/Controllers/EpisodeWatchedController';
import {
    update as addToWatchlist,
    destroy as removeFromWatchlist,
} from '@/actions/App/Http/Controllers/TvShowWatchlistController';
import {
    updateSeason,
    destroySeason,
    updateTvShow,
    destroyTvShow,
} from '@/actions/App/Http/Controllers/BulkWatchedController';
import undoRoute from '@/actions/App/Http/Controllers/TvShowUndoController';

export type Episode = {
    id: number;
    number: number | null;
    name: string | null;
    overview: string | null;
    airDate: string | null;
    runtime: number | null;
    markable: boolean;
    watchedDate: string | null;
};
export type Season = {
    id: number;
    number: number | null;
    name: string | null;
    total: number;
    watched: number;
    episodes: Episode[];
};
export type WatchedProgress = {
    state: 'Not started' | 'In progress' | 'Completed';
    total: number;
    watched: number;
    defaultOpenSeasonId: number | null;
    seasons: Season[];
};
type Result = {
    progress: WatchedProgress | null;
    watchlisted: boolean;
    undoToken?: string;
    redirect?: string;
};

export function useTvShowActions(
    initial: () => WatchedProgress,
    initiallyWatchlisted: () => boolean,
    tvShow: () => { id: number; tmdbId: number },
) {
    const page = usePage();
    const progress = ref(initial());
    const busy = ref(false);
    const watchlisted = ref(initiallyWatchlisted());
    const watchlistHttp = useHttp<{ source: string }, Result>({
        source: 'tv-show',
    });
    const http = useHttp<Record<string, never>, Result>({});
    const undoHttp = useHttp<{ token: string; undo: string }, Result>({
        token: '',
        undo: '',
    });
    watch(initial, (value) => {
        progress.value = value;
    });

    watch(initiallyWatchlisted, (value) => {
        watchlisted.value = value;
    });

    function apply(result: Result) {
        if (result.progress) progress.value = result.progress;
        watchlisted.value = result.watchlisted;
        router.replace({
            props: (current) => ({
                ...current,
                watchlisted: result.watchlisted,
                ...(result.progress ? { progress: result.progress } : {}),
            }),
            preserveScroll: true,
            preserveState: true,
        });
    }

    function offerUndo(token: string, message: string) {
        toast.success(message, {
            action: {
                label: 'Undo',
                onClick: () => {
                    void undo(token);
                },
            },
        });
    }

    async function undo(token: string) {
        if (busy.value) return;
        busy.value = true;
        try {
            undoHttp.token = token;
            const result = await undoHttp.post(
                undoRoute.url({ tmdbId: tvShow().tmdbId }),
            );
            apply(result);
            toast.success('Action undone');
        } catch {
            toast.error(
                undoHttp.errors.undo ?? "Couldn't undo that. Try again.",
            );
            router.reload({ only: ['progress', 'watchlisted'] });
        } finally {
            busy.value = false;
        }
    }

    async function toggle(season: Season, episode: Episode) {
        if (busy.value || !episode.markable) return;
        busy.value = true;
        const before = progress.value;
        const previouslyWatchlisted = watchlisted.value;
        const watched = episode.watchedDate === null;
        if (page.props.auth.user) {
            const seasons = before.seasons
                .map((item) => ({
                    ...item,
                    episodes: item.episodes.map((row) =>
                        row.id === episode.id
                            ? {
                                  ...row,
                                  watchedDate: watched
                                      ? new Date().toISOString()
                                      : null,
                              }
                            : row,
                    ),
                }))
                .map((item) => ({
                    ...item,
                    watched: item.episodes.filter(
                        (row) => row.markable && row.watchedDate !== null,
                    ).length,
                }));
            const count = seasons
                .filter((item) => (item.number ?? 0) > 0)
                .reduce((sum, item) => sum + item.watched, 0);
            progress.value = {
                ...before,
                seasons,
                watched: count,
                state:
                    count === 0
                        ? 'Not started'
                        : count === before.total
                          ? 'Completed'
                          : 'In progress',
            };
        }
        if (
            page.props.auth.user &&
            before.state !== 'Completed' &&
            progress.value.state === 'Completed'
        ) {
            watchlisted.value = false;
        }
        try {
            const args = {
                tvShow: tvShow().id,
                season: season.id,
                episode: episode.id,
            };
            const result = watched
                ? await http.put(update.url(args))
                : await http.delete(destroy.url(args));
            if (result.redirect) {
                router.visit(result.redirect);
                return;
            }
            apply(result);
            if (result.undoToken)
                offerUndo(
                    result.undoToken,
                    watched ? 'Episode marked as watched' : 'Episode unmarked',
                );
        } catch {
            progress.value = before;
            watchlisted.value = previouslyWatchlisted;
            toast.error("Couldn't save that. Try again.");
        } finally {
            busy.value = false;
        }
    }

    async function bulk(
        send: () => Promise<Result>,
        message: string,
    ): Promise<boolean> {
        if (busy.value) return false;
        busy.value = true;
        try {
            const result = await send();
            if (result.redirect) {
                router.visit(result.redirect);
                return false;
            }
            apply(result);
            if (result.undoToken) offerUndo(result.undoToken, message);
            return true;
        } catch {
            toast.error(
                http.errors.episode ??
                    http.errors.message ??
                    "Couldn't save that. Try again.",
            );
            return false;
        } finally {
            busy.value = false;
        }
    }

    function markSeason(season: Season) {
        return bulk(
            () =>
                http.put(
                    updateSeason.url({
                        tvShow: tvShow().id,
                        season: season.id,
                    }),
                ),
            'Season marked as watched',
        );
    }

    function clearSeason(season: Season) {
        return bulk(
            () =>
                http.delete(
                    destroySeason.url({
                        tvShow: tvShow().id,
                        season: season.id,
                    }),
                ),
            'Season cleared',
        );
    }

    function markTvShow() {
        return bulk(
            () => http.put(updateTvShow.url({ tmdbId: tvShow().tmdbId })),
            'TV show marked as watched',
        );
    }

    function clearTvShow() {
        return bulk(
            () => http.delete(destroyTvShow.url({ tmdbId: tvShow().tmdbId })),
            'TV show cleared',
        );
    }

    async function toggleWatchlist() {
        if (busy.value) return;
        busy.value = true;
        const before = watchlisted.value;
        const adding = !before;
        if (page.props.auth.user) watchlisted.value = adding;
        try {
            const args = { tmdbId: tvShow().tmdbId };
            const result = adding
                ? await watchlistHttp.put(addToWatchlist.url(args))
                : await watchlistHttp.delete(removeFromWatchlist.url(args));
            if (result.redirect) {
                router.visit(result.redirect);
                return;
            }
            apply(result);
            if (result.undoToken)
                offerUndo(
                    result.undoToken,
                    adding
                        ? 'Added to your Watchlist'
                        : 'Removed from your Watchlist',
                );
        } catch {
            watchlisted.value = before;
            toast.error("Couldn't save that. Try again.");
        } finally {
            busy.value = false;
        }
    }

    onMounted(() => {
        const watchlistFlash = page.flash.tvWatchlistAction as
            | { tmdbId: number; undoToken: string; watchlisted: boolean }
            | undefined;
        if (watchlistFlash?.tmdbId === tvShow().tmdbId)
            offerUndo(
                watchlistFlash.undoToken,
                watchlistFlash.watchlisted
                    ? 'Added to your Watchlist'
                    : 'Removed from your Watchlist',
            );
        const flash = page.flash.episodeAction as
            | { tmdbId: number; undoToken: string; message?: string }
            | undefined;
        if (flash?.tmdbId === tvShow().tmdbId)
            offerUndo(flash.undoToken, flash.message ?? 'Episode updated');
    });
    return {
        progress,
        watchlisted,
        busy,
        toggle,
        toggleWatchlist,
        markSeason,
        clearSeason,
        markTvShow,
        clearTvShow,
    };
}
