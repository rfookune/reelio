import { router, useHttp, usePage } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    update,
    destroy,
} from '@/actions/App/Http/Controllers/EpisodeWatchedController';
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
    progress: WatchedProgress;
    undoToken?: string;
    redirect?: string;
};

export function useEpisodeProgress(
    initial: () => WatchedProgress,
    tvShow: () => { id: number; tmdbId: number },
) {
    const page = usePage();
    const progress = ref(initial());
    const busy = ref(false);
    const http = useHttp<Record<string, never>, Result>({});
    const undoHttp = useHttp<{ token: string; undo: string }, Result>({
        token: '',
        undo: '',
    });
    watch(initial, (value) => {
        progress.value = value;
    });

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
            progress.value = result.progress;
            toast.success('Action undone');
        } catch {
            toast.error(
                undoHttp.errors.undo ?? "Couldn't undo that. Try again.",
            );
            router.reload({ only: ['progress'] });
        } finally {
            busy.value = false;
        }
    }

    async function toggle(season: Season, episode: Episode) {
        if (busy.value || !episode.markable) return;
        busy.value = true;
        const before = progress.value;
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
            progress.value = result.progress;
            if (result.undoToken)
                offerUndo(
                    result.undoToken,
                    watched ? 'Episode marked as watched' : 'Episode unmarked',
                );
        } catch {
            progress.value = before;
            toast.error("Couldn't save that. Try again.");
        } finally {
            busy.value = false;
        }
    }

    onMounted(() => {
        const flash = page.flash.episodeAction as
            | { tmdbId: number; undoToken: string }
            | undefined;
        if (flash?.tmdbId === tvShow().tmdbId)
            offerUndo(flash.undoToken, 'Episode updated');
    });
    return { progress, busy, toggle };
}
