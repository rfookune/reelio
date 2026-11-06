import type { Errors, UrlMethodPair } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { undo as undoRoute } from '@/routes/movies';
import * as watched from '@/routes/movies/watched';
import * as watchlist from '@/routes/movies/watchlist';
import type {
    AppliedUserMovieAction,
    UserMovieAction,
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
const SUCCESS_MESSAGES: Record<UserMovieAction, string> = {
    add_to_watchlist: 'Added to your Watchlist',
    remove_from_watchlist: 'Removed from your Watchlist',
    mark_as_watched: 'Marked as watched',
    clear_watched: 'Cleared from Watched',
};

/**
 * Sends a visit that changes one Movie's facts, by catalog id, and reloads only `userMovies`.
 *
 * The visit is `async`, so a second one never cancels one in flight. The page's `userMovies` entry shows `produce`'s
 * facts until the server answers, and on any failure Inertia rolls the entry back while an error toast explains: Undo's
 * own message when it was refused, else the generic line. Undo is refused because the facts changed elsewhere, so the
 * rolled-back entry may be stale; `userMovies` is reloaded to show what the server has.
 */
function visitUserMovies({
    tmdbId,
    route,
    data,
    produce,
    onSuccess,
}: {
    tmdbId: number;
    route: UrlMethodPair;
    data: Record<string, string>;
    produce: (facts: UserMovieFacts) => UserMovieFacts;
    onSuccess?: (component: string) => void;
}): void {
    const failed = (errors?: Errors): false => {
        toast.error(errors?.undo ?? FAILURE_MESSAGE, {
            duration: TOAST_DURATION,
        });

        if (errors?.undo) {
            router.reload({ only: ['userMovies'], async: true });
        }

        return false;
    };

    router.visit(route, {
        data,
        async: true,
        only: ['userMovies'],
        preserveScroll: true,
        preserveState: true,
        optimistic: (props) => {
            const userMovies = (props.userMovies ?? {}) as UserMovies;

            return {
                userMovies: {
                    ...userMovies,
                    [tmdbId]: produce(userMovies[tmdbId] ?? NO_FACTS),
                },
            };
        },
        onSuccess: (response) => onSuccess?.(response.component),
        onError: failed,
        onHttpException: () => failed(),
        onNetworkError: () => failed(),
    });
}

/** Whether focus is nowhere useful: on the page body, or still in the toaster whose toast is closing. */
function focusIsLost(): boolean {
    const focused = document.activeElement;

    return (
        focused === null ||
        focused === document.body ||
        focused.closest('[data-sonner-toaster]') !== null
    );
}

/** How long, in frames, focus waits for Undo's restored control to render before settling for another. */
const FOCUS_WAIT_FRAMES = 60;

/**
 * Returns a function that puts focus back on the control an action was taken from, once Undo has restored it.
 * The action may have replaced the control, or the whole card, so the control is found again by its `data-slot`
 * inside the Movie's `data-tmdb-id` container: the same container if it is still on the page, else the first one
 * for the Movie. Undo's optimistic state renders a frame or so later, so it waits for the control; if it never
 * appears and focus has been lost meanwhile, focus goes to the container's first button.
 */
function focusRestorer(
    tmdbId: number,
    control: HTMLElement | null,
): () => void {
    const slot = control?.dataset.slot;
    const scope = control?.closest<HTMLElement>('[data-tmdb-id]') ?? null;
    const container = (): HTMLElement | null =>
        scope?.isConnected
            ? scope
            : document.querySelector<HTMLElement>(`[data-tmdb-id="${tmdbId}"]`);

    return () => {
        const attempt = (framesLeft: number): void => {
            const restored = slot
                ? container()?.querySelector<HTMLElement>(
                      `[data-slot="${slot}"]`,
                  )
                : null;

            if (restored) {
                restored.focus();
            } else if (framesLeft > 0) {
                requestAnimationFrame(() => attempt(framesLeft - 1));
            } else if (focusIsLost()) {
                container()?.querySelector<HTMLElement>('button')?.focus();
            }
        };

        attempt(FOCUS_WAIT_FRAMES);
    };
}

/**
 * Undoes the latest action from its toast. The entry shows the facts from before the action until the server
 * answers; the server restores its own record of them, including the dates, and refuses with "That can't be
 * undone any more" if anything changed since.
 */
function undo(
    tmdbId: number,
    action: UserMovieAction,
    restored: UserMovieFacts,
    restoreFocus: () => void,
): void {
    visitUserMovies({
        tmdbId,
        route: undoRoute(tmdbId),
        data: { action },
        produce: () => restored,
    });

    restoreFocus();
}

/** Shows an action's success toast, whose Undo restores `previous`, the facts the Movie had before it. */
function showSuccessToast(
    tmdbId: number,
    action: UserMovieAction,
    previous: UserMovieFacts,
    restoreFocus: () => void,
): void {
    toast.success(SUCCESS_MESSAGES[action], {
        duration: TOAST_DURATION,
        action: {
            label: 'Undo',
            onClick: () => undo(tmdbId, action, previous, restoreFocus),
        },
    });
}

/**
 * Sends one Watchlist or Watched action for a Movie, by catalog id.
 *
 * For a signed-in user the visit is optimistic (see `visitUserMovies`), and its success toast offers Undo, which
 * restores the facts the Movie had and returns focus to `control`, the control pressed. A guest's visit is sent
 * plainly, so sign-in takes over the page; the server holds the action and applies it once they sign in (see
 * `initializeAppliedActionToast`). The request never carries dates: the server sets them.
 */
function sendUserMovieAction({
    tmdbId,
    action,
    route,
    source,
    control,
    produce,
}: {
    tmdbId: number;
    action: UserMovieAction;
    route: UrlMethodPair;
    source: UserMovieActionSource;
    control: HTMLElement | null;
    /** The Movie's facts once the action succeeds, shown until the server answers. */
    produce: (facts: UserMovieFacts) => UserMovieFacts;
}): void {
    const page = usePage();

    if (page.props.auth.user === null) {
        router.visit(route, { data: { source } });

        return;
    }

    const component = page.component;
    const previous =
        (page.props.userMovies as UserMovies | undefined)?.[tmdbId] ?? NO_FACTS;
    const restoreFocus = focusRestorer(tmdbId, control);

    visitUserMovies({
        tmdbId,
        route,
        data: { source },
        produce,
        onSuccess: (responseComponent) => {
            if (responseComponent !== component) {
                return;
            }

            showSuccessToast(tmdbId, action, previous, restoreFocus);
        },
    });
}

/** Puts a Movie on the Watchlist or takes it off. */
export function setWatchlist(
    tmdbId: number,
    watchlisted: boolean,
    source: UserMovieActionSource,
    control: HTMLElement | null,
): void {
    sendUserMovieAction({
        tmdbId,
        action: watchlisted ? 'add_to_watchlist' : 'remove_from_watchlist',
        route: watchlisted
            ? watchlist.update(tmdbId)
            : watchlist.destroy(tmdbId),
        source,
        control,
        produce: (facts) => ({ ...facts, watchlisted }),
    });
}

/** Marks a Movie as watched today, which takes it off the Watchlist. */
export function markAsWatched(
    tmdbId: number,
    source: UserMovieActionSource,
    control: HTMLElement | null,
): void {
    sendUserMovieAction({
        tmdbId,
        action: 'mark_as_watched',
        route: watched.update(tmdbId),
        source,
        control,
        produce: () => ({
            watchlisted: false,
            watched: true,
            watchedDate: new Date().toISOString(),
        }),
    });
}

/** Clears a Movie's Watched record, leaving the Watchlist alone. */
export function clearWatched(
    tmdbId: number,
    source: UserMovieActionSource,
    control: HTMLElement | null,
): void {
    sendUserMovieAction({
        tmdbId,
        action: 'clear_watched',
        route: watched.destroy(tmdbId),
        source,
        control,
        produce: (facts) => ({ ...facts, watched: false, watchedDate: null }),
    });
}

/**
 * Shows the success toast, with Undo, for a guest's pending action once sign-in or registration has applied it and
 * returned them to the page they were on. No control was pressed on this page, so Undo's focus goes to the Movie's
 * first control, if focus is lost meanwhile.
 */
export function initializeAppliedActionToast(): void {
    router.on('flash', (event) => {
        const applied = (event as CustomEvent).detail?.flash
            ?.userMovieAction as AppliedUserMovieAction | undefined;

        if (!applied) {
            return;
        }

        showSuccessToast(
            applied.tmdbId,
            applied.action,
            applied.previous,
            focusRestorer(applied.tmdbId, null),
        );
    });
}
