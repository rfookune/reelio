import type { Errors, Page, UrlMethodPair } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { errorPageMovieData } from '@/lib/errorPages';
import { watched as watchedPage, watchlist as watchlistPage } from '@/routes';
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
const TOO_FAST = "You're going too fast. Try again in a moment.";
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
 * Sends a visit that changes one Movie's facts, by catalog id, and reloads `userMovies` (inside `data` on errors).
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
    const page = usePage();
    const pageMovie =
        page.component === 'Error' ? errorPageMovieData(page.props) : undefined;
    const factProps = pageMovie ? ['data', 'exceptionClass'] : ['userMovies'];
    const failed = (errors?: Errors, status?: number): false => {
        toast.error(
            status === 429 ? TOO_FAST : (errors?.undo ?? FAILURE_MESSAGE),
            {
                duration: TOAST_DURATION,
            },
        );

        if (errors?.undo) {
            router.reload({ only: factProps, async: true });
        }

        return false;
    };

    router.visit(route, {
        data,
        async: true,
        only:
            page.component === 'Watchlist'
                ? [...factProps, 'trending']
                : factProps,
        preserveScroll: true,
        preserveState: true,
        optimistic: (props) => {
            const errorData = errorPageMovieData(props);
            const userMovies =
                errorData?.userMovies ??
                ((props.userMovies ?? {}) as UserMovies);
            const updated = {
                ...userMovies,
                [tmdbId]: produce(userMovies[tmdbId] ?? NO_FACTS),
            };

            return errorData
                ? { data: { ...errorData, userMovies: updated } }
                : { userMovies: updated };
        },
        onSuccess: (response) => onSuccess?.(response.component),
        onError: (errors) => failed(errors),
        onHttpException: (response) => {
            const page = response.data as Partial<Page>;
            // The action returns to the same Movie's error page, whose successful render still has its status: 410
            // for a Removed Movie, 503 for a Movie without details. A 503 about no Movie carries no Movie data.
            if (
                (response.status === 410 || response.status === 503) &&
                response.headers['x-inertia'] &&
                pageMovie?.tmdbId === tmdbId &&
                page.component === 'Error' &&
                errorPageMovieData(page.props ?? {})?.tmdbId ===
                    pageMovie.tmdbId
            ) {
                return;
            }

            return failed(undefined, response.status);
        },
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
        onSuccess: (component) => {
            const titles = usePage().props.titles as
                | { data: { kind?: 'movie' | 'tv'; tmdbId: number }[] }
                | undefined;
            const unavailableMovies = usePage().props.unavailableMovies as
                | { tmdbId: number }[]
                | undefined;
            if (
                ((component === 'Watchlist' && restored.watchlisted) ||
                    (component === 'Watched' && restored.watched)) &&
                !titles?.data.some(
                    (card) => card.kind !== 'tv' && card.tmdbId === tmdbId,
                ) &&
                !unavailableMovies?.some((movie) => movie.tmdbId === tmdbId)
            ) {
                revealUserMovieCard(tmdbId, restoreFocus);
            }
        },
    });

    restoreFocus();
}

/** Reload the cursor around a saved or restored Movie when the current merged pages don't hold its card. */
function revealUserMovieCard(tmdbId: number, restoreFocus: () => void): void {
    const isWatched = usePage().component === 'Watched';
    const route = isWatched ? watchedPage : watchlistPage;
    const defaultSort = isWatched ? 'recently-watched' : 'recently-added';
    const sort = usePage().props.sort as string;
    const filter = usePage().props.filter as string;
    router.get(
        route.url(),
        {
            restore: tmdbId,
            ...(sort === defaultSort ? {} : { sort }),
            ...(filter === 'all' ? {} : { filter }),
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            reset: ['titles'],
            only: [
                'titles',
                'filter',
                'unavailableMovies',
                'userMovies',
                'trending',
            ],
            onSuccess: restoreFocus,
        },
    );
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
        router.visit(route, {
            data: { source },
            onHttpException: (response) => {
                if (response.status === 429) {
                    toast.error(TOO_FAST, { duration: TOAST_DURATION });

                    return false;
                }
            },
        });

        return;
    }

    const component = page.component;
    const previous =
        (errorPageMovieData(page.props)?.userMovies ??
            (page.props.userMovies as UserMovies | undefined))?.[tmdbId] ??
        NO_FACTS;
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

            if (component === 'Watchlist' && action === 'add_to_watchlist') {
                revealUserMovieCard(tmdbId, restoreFocus);
            }
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
