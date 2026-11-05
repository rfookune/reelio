/**
 * PROTOTYPE, throwaway. In-memory state for the Movie detail page prototype.
 *
 * `settings` mirrors the URL toggles (the page writes it); `facts` is the user's
 * Watchlist / Watched state for this one Movie, reset whenever `?facts=` changes.
 * Actions follow the "Watchlist interactions" ticket: idempotent sets, a toast with
 * Undo restoring the exact earlier state, guests routed through a simulated sign-in.
 */
import { computed, reactive } from 'vue';
import { toast } from 'vue-sonner';
import type { CastMember, CountryAvailability, MockDetail } from './mockDetail';
import { interstellar } from './mockDetail';

export type SourceState =
    | 'ok'
    | 'loading'
    | 'stale'
    | 'unavailable'
    | 'sparse'
    | 'shell';
export type FactsPreset = 'none' | 'watchlist' | 'watched' | 'both';
export type TrailerMode = 'facade' | 'embed' | 'disabled';
export type CountryPreset = 'US' | 'CA' | 'unsupported';

export const toggles = {
    state: ['ok', 'loading', 'stale', 'unavailable', 'sparse', 'shell'],
    facts: ['none', 'watchlist', 'watched', 'both'],
    auth: ['user', 'guest'],
    trailer: ['facade', 'embed', 'disabled'],
    country: ['US', 'CA', 'unsupported'],
    imdb: ['on', 'off'],
    theme: ['dark', 'light'],
} as const;

export type ToggleKey = keyof typeof toggles;

const settings = reactive({
    state: 'ok' as SourceState,
    facts: 'none' as FactsPreset,
    auth: 'user' as 'user' | 'guest',
    trailer: 'facade' as TrailerMode,
    country: 'US' as CountryPreset,
    imdb: 'on' as 'on' | 'off',
    theme: 'dark' as 'dark' | 'light',
});

type Facts = { watchlistedAt: string | null; watchedAt: string | null };

const facts = reactive<Facts>({ watchlistedAt: null, watchedAt: null });

const EARLIER_WATCH = '2026-09-12';
const TODAY = '2026-11-04';

export function resetFacts(preset: FactsPreset): void {
    facts.watchlistedAt =
        preset === 'watchlist' || preset === 'both' ? '2026-10-20' : null;
    facts.watchedAt =
        preset === 'watched' || preset === 'both' ? EARLIER_WATCH : null;
}

export type ExternalRatingView = {
    source: 'IMDb' | 'TMDB';
    value: number;
    votes: number;
};

/** What the server would send for this page, shaped by the `state` toggle. */
export type DetailView = {
    title: string;
    originalTitle: string | null;
    tagline: string | null;
    overview: string;
    year: number;
    releaseLabel: string;
    runtimeLabel: string;
    genres: string[];
    posterUrl: string | null;
    backdropUrl: string | null;
    ratings: ExternalRatingView[];
    /** `undefined` = the source is unavailable (cache-only data). */
    certification: string | null | undefined;
    directors: string[] | undefined;
    writers: string[] | undefined;
    cast: CastMember[] | undefined;
    trailer: MockDetail['trailer'] | null | undefined;
    availability: {
        countryCode: string;
        countryName: string;
        unsupportedDetected: string | null;
        data: CountryAvailability;
        updatedAgo: string;
        stale: boolean;
    };
};

const IMAGE_BASE = 'https://image.tmdb.org/t/p';

export const countryNames: Record<string, string> = {
    US: 'the United States',
    CA: 'Canada',
    GB: 'the United Kingdom',
    AU: 'Australia',
    NZ: 'New Zealand',
    IE: 'Ireland',
    DE: 'Germany',
    FR: 'France',
    ES: 'Spain',
    IT: 'Italy',
};

const empty: CountryAvailability = {
    link: 'https://www.themoviedb.org/movie/157336-interstellar/watch?locale=US',
    stream: [],
    free: [],
    rent: [],
    buy: [],
};

const view = computed<DetailView>(() => {
    const movie = interstellar;
    const sparse = settings.state === 'sparse';
    const sourceDown = settings.state === 'unavailable';
    const countryCode = settings.country === 'CA' ? 'CA' : 'US';

    const ratings: ExternalRatingView[] = [];

    if (!sparse && settings.imdb === 'on') {
        ratings.push({
            source: 'IMDb',
            value: movie.imdbRating,
            votes: movie.imdbVotes,
        });
    }

    if (!sparse) {
        ratings.push({
            source: 'TMDB',
            value: movie.tmdbRating,
            votes: movie.tmdbVotes,
        });
    }

    return {
        title: movie.title,
        originalTitle: sparse ? 'Interstellaire' : null,
        tagline: sparse ? null : movie.tagline,
        overview: movie.overview,
        year: 2014,
        releaseLabel: '7 November 2014',
        runtimeLabel: '2h 49m',
        genres: movie.genres,
        posterUrl: movie.posterPath
            ? `${IMAGE_BASE}/w500${movie.posterPath}`
            : null,
        backdropUrl:
            !sparse && movie.backdropPath
                ? `${IMAGE_BASE}/w1280${movie.backdropPath}`
                : null,
        ratings,
        certification: sourceDown
            ? undefined
            : sparse
              ? null
              : movie.certification,
        directors: sourceDown ? undefined : movie.directors,
        writers: sourceDown ? undefined : sparse ? [] : movie.writers,
        cast: sourceDown
            ? undefined
            : sparse
              ? movie.cast.slice(0, 3)
              : movie.cast,
        trailer: sourceDown ? undefined : sparse ? null : movie.trailer,
        availability: {
            countryCode,
            countryName: countryNames[countryCode],
            unsupportedDetected:
                settings.country === 'unsupported' ? 'Brazil' : null,
            data: sparse ? empty : (movie.availability[countryCode] ?? empty),
            updatedAgo:
                settings.state === 'stale' ? '9 days ago' : '2 days ago',
            stale: settings.state === 'stale',
        },
    };
});

function formatDate(iso: string): string {
    return new Date(`${iso}T12:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

type Action = 'add' | 'remove' | 'markWatched' | 'clearWatched';

const messages: Record<Action, string> = {
    add: 'Added to your Watchlist',
    remove: 'Removed from your Watchlist',
    markWatched: 'Marked as watched',
    clearWatched: 'Cleared from Watched',
};

function apply(action: Action): void {
    const before: Facts = { ...facts };

    switch (action) {
        case 'add':
            facts.watchlistedAt ??= TODAY;
            break;
        case 'remove':
            facts.watchlistedAt = null;
            break;
        case 'markWatched':
            facts.watchedAt = TODAY;
            facts.watchlistedAt = null;
            break;
        case 'clearWatched':
            facts.watchedAt = null;
            break;
    }

    const after: Facts = { ...facts };

    toast(messages[action], {
        duration: 6000,
        action: {
            label: 'Undo',
            onClick: () => {
                if (
                    facts.watchlistedAt !== after.watchlistedAt ||
                    facts.watchedAt !== after.watchedAt
                ) {
                    toast.error("That can't be undone any more");

                    return;
                }

                Object.assign(facts, before);
            },
        },
    });
}

function act(action: Action): void {
    if (settings.auth === 'guest') {
        toast('Guest: this would send you to sign in', {
            description:
                'The action is held in the session, applied after login or registration, and you land back on this page.',
            duration: 8000,
            action: {
                label: 'Simulate sign-in',
                onClick: () => {
                    settings.auth = 'user';
                    apply(action);
                },
            },
        });

        return;
    }

    apply(action);
}

export function useDetailState() {
    return {
        settings,
        facts,
        view,
        act,
        formatDate,
        isOnWatchlist: computed(() => facts.watchlistedAt !== null),
        isWatched: computed(() => facts.watchedAt !== null),
    };
}
