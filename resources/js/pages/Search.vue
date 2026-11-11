<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { LoaderCircle, RotateCw, Search as SearchIcon } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PosterCard from '@/components/shared/PosterCard.vue';
import type { PosterCardTitle } from '@/components/shared/PosterCard.vue';
import PosterCardSkeleton from '@/components/shared/PosterCardSkeleton.vue';
import { Button } from '@/components/ui/button';
import { announce } from '@/composables/useAnnouncer';
import { useLookedUpRatings } from '@/composables/useLookedUpRatings';
import { useTvShowWatchlistToggle } from '@/composables/useTvShowWatchlistToggle';
import type { TvShowWatchlistFacts } from '@/composables/useTvShowWatchlistToggle';
import { cardKey, filterByKind, kindCounts } from '@/lib/searchKinds';
import type { CardKind, KindFilter } from '@/lib/searchKinds';
import { search } from '@/routes';
import type { UserMovies } from '@/types';

/** A result card, and the page of the search it is on. */
type SearchResult = PosterCardTitle & { kind: CardKind; page: number };

const props = defineProps<{
    imageBaseUrl: string;
    /** The query searched, trimmed and cut; shorter than the minimum, it wasn't searched. */
    query: string;
    minimumQueryLength: number;
    maximumQueryLength: number;
    /** Whether the IMDb ratings of results are looked up; they aren't while IMDb is switched off. */
    looksUpImdbRatings: boolean;
    /** Whether TV Shows are searched beside Movies, so the results can be filtered by kind. */
    includesTvShows: boolean;
    /** The Browse tab shown while there is no query: Movies, or TV Shows when they are switched on. */
    tab: CardKind;
    results: { data: SearchResult[] };
    totalResults: number | null;
    /** How the Catalog answered the search, or Browse while there is no query. */
    status: 'fresh' | 'stale' | 'unavailable';
    userMovies: UserMovies;
    /** Whether the viewer has each TV Show of the results on their Watchlist, by catalog id. */
    userTvShows: TvShowWatchlistFacts;
}>();

const { toggle: toggleTvWatchlist } = useTvShowWatchlistToggle(() => 'search');

const { withLookedUpRatings, isLookingUp } = useLookedUpRatings(
    () => props.query,
    () => props.results.data,
    () => props.looksUpImdbRatings,
);

const BROWSE_TABS: { value: CardKind; label: string }[] = [
    { value: 'movie', label: 'Movies' },
    { value: 'tv', label: 'TV Shows' },
];
const KIND_CHIPS: { value: KindFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'movie', label: 'Movies' },
    { value: 'tv', label: 'TV Shows' },
];
const DEBOUNCE_MILLISECONDS = 300;
const EAGER_CARDS = 6;
const SHIMMER_CARDS = 12;
const CANT_SEARCH = "We can't search right now";
const CANT_LOAD_MORE = "We can't load more results right now";
const TOO_FAST = "You're going too fast. Try again in a moment.";
const GRID =
    'grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6';

/** The input's text. It is filled from the query once, so a slow answer never overwrites what is being typed. */
const text = ref(props.query);
const input = ref<HTMLInputElement | null>(null);
/** The query last asked for: the one on the page, or the one in flight. */
const requestedQuery = ref(props.query);
/** Typed since the last search was asked for. */
const typing = ref(false);
const pending = ref(false);
/** A tab was chosen and its first page hasn't arrived, so the other tab's list isn't shown meanwhile. */
const tabLoading = ref(false);
/** Why the latest query visit failed without a page: the 429 line or the error line. */
const failure = ref<string | null>(null);
/** Why the latest "Show more" failed without a page. */
const moreFailure = ref<string | null>(null);
const resultsList = ref<HTMLElement | null>(null);
let debounceTimer: ReturnType<typeof setTimeout> | undefined;
let latestVisit = 0;

/** The text as the server reads it, roughly: trimmed, cut to the maximum, and empty when too short to search. */
const wantedQuery = computed(() => {
    const trimmed = Array.from(text.value.trim())
        .slice(0, props.maximumQueryLength)
        .join('')
        .trim();

    return Array.from(trimmed).length >= props.minimumQueryLength
        ? trimmed
        : '';
});
/** Whether the query on the page is long enough to have been searched by Name; if not, the page is Browse. */
const isSearching = computed(
    () => Array.from(props.query).length >= props.minimumQueryLength,
);
/** The Browse tab chosen; it follows the page's, which Back and forward change. */
const browseTab = ref(props.tab);
const browseNoun = computed(() =>
    browseTab.value === 'tv' ? 'TV shows' : 'movies',
);
const cantBrowse = computed(
    () => `We can't load ${browseNoun.value} right now`,
);
/** The chip chosen; it filters the cards on the page, which the chips' counts are over. */
const kindFilter = ref<KindFilter>('all');
const counts = computed(() => kindCounts(props.results.data));
const shownResults = computed(() =>
    filterByKind(props.results.data, kindFilter.value),
);
const emptyKindLabel = computed(() =>
    kindFilter.value === 'tv' ? 'TV shows' : 'movies',
);
const settling = computed(() => typing.value || pending.value);
const hasResults = computed(() => props.results.data.length > 0);
const firstLoading = computed(
    () => settling.value && (!hasResults.value || tabLoading.value),
);
/** The line for the page failing as a whole: the search's, or Browse's when the query asked for is too short to search. */
const failureMessage = computed(() =>
    Array.from(requestedQuery.value).length >= props.minimumQueryLength
        ? CANT_SEARCH
        : cantBrowse.value,
);
/** The search failed as a whole: the visit itself, or the source on the first page. */
const pageError = computed(() => {
    if (settling.value) {
        return null;
    }

    if (failure.value !== null) {
        return failure.value;
    }

    return props.status === 'unavailable' && !hasResults.value
        ? failureMessage.value
        : null;
});
/** "Show more" failed: the visit itself, or the source on a later page, which the button then asks for again. */
const moreError = computed(
    () =>
        moreFailure.value ??
        (props.status === 'unavailable' && hasResults.value
            ? CANT_LOAD_MORE
            : null),
);
/** Browse answered with no Movies to list. */
const browseEmpty = computed(
    () =>
        !settling.value &&
        !isSearching.value &&
        failure.value === null &&
        (props.status === 'fresh' || props.status === 'stale') &&
        !hasResults.value,
);
const showsResults = computed(() => hasResults.value && failure.value === null);
const noResults = computed(
    () =>
        !settling.value &&
        isSearching.value &&
        failure.value === null &&
        (props.status === 'fresh' || props.status === 'stale') &&
        (props.totalResults === 0 ||
            // A search of every kind counts people too, which the page drops.
            (props.includesTvShows && !hasResults.value)),
);
function resultCount(total: number): string {
    return `${new Intl.NumberFormat('en-US').format(total)} ${total === 1 ? 'result' : 'results'}`;
}

/** Each "Show more" reloads `userMovies` and `status` with the page, and reports a failed visit inline. */
const showMoreParams = {
    only: ['userMovies', 'userTvShows', 'status'],
    onStart: () => {
        moreFailure.value = null;
    },
    onHttpException: (response: { status: number }) => {
        failMore(response.status === 429 ? TOO_FAST : CANT_LOAD_MORE);

        return false;
    },
    onNetworkError: () => {
        failMore(CANT_LOAD_MORE);

        return false;
    },
};

/**
 * Searches for the query, replacing the history entry, so Back leaves Search rather than stepping through
 * keystrokes. A visit cancels the one in flight, and the results and `userMovies` start afresh rather than
 * merging. Resetting makes it a partial reload, so it names every prop the query changes. The query last asked
 * for isn't asked again unless its answer was a failure, or `force` says to.
 */
function visit(query: string, force = false): void {
    clearTimeout(debounceTimer);
    typing.value = false;

    if (
        !force &&
        query === requestedQuery.value &&
        failure.value === null &&
        props.status !== 'unavailable'
    ) {
        return;
    }

    const visitNumber = ++latestVisit;
    requestedQuery.value = query;
    failure.value = null;
    moreFailure.value = null;

    const params =
        query !== ''
            ? { q: query }
            : browseTab.value === 'tv'
              ? { tab: browseTab.value }
              : {};

    router.get(search.url(), params, {
        replace: true,
        preserveState: true,
        preserveScroll: true,
        only: [
            'query',
            'tab',
            'results',
            'totalResults',
            'status',
            'userMovies',
            'userTvShows',
        ],
        reset: ['results', 'userMovies', 'userTvShows'],
        onStart: () => {
            pending.value = true;
        },
        onSuccess: (page) => {
            if (query !== '') {
                announceSettled(page.props as unknown as typeof props);
            }
        },
        onHttpException: (response) => {
            fail(response.status === 429 ? TOO_FAST : failureMessage.value);

            return false;
        },
        onNetworkError: () => {
            fail(failureMessage.value);

            return false;
        },
        onFinish: () => {
            if (visitNumber === latestVisit) {
                pending.value = false;
                tabLoading.value = false;
            }
        },
    });
}

/** Browses the other tab: its first page replaces the list, and the tab is kept in the URL. */
function chooseTab(tab: CardKind): void {
    if (tab === browseTab.value) {
        return;
    }

    browseTab.value = tab;
    tabLoading.value = true;
    visit('', true);
}

function fail(message: string): void {
    failure.value = message;
    announce(message);
}

function failMore(message: string): void {
    moreFailure.value = message;
    announce(message);
}

/** Asks again, from the input, which keeps focus while the error it was in goes away. */
function retry(): void {
    input.value?.focus();
    visit(wantedQuery.value, true);
}

/** Announces a settled first page: its count, or that the search failed. Loading isn't announced. */
function announceSettled(page: typeof props): void {
    announce(
        page.status === 'unavailable'
            ? failureMessage.value
            : `${resultCount(page.totalResults ?? 0)} for '${page.query}'`,
    );
}

watch(text, () => {
    typing.value = true;
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(
        () => visit(wantedQuery.value),
        DEBOUNCE_MILLISECONDS,
    );
});

watch(
    () => props.tab,
    (tab) => {
        // A search by Name carries no tab; the one chosen is kept for when Browse returns.
        if (!isSearching.value) {
            browseTab.value = tab;
        }
    },
);

watch(
    () => props.status,
    (status, previous) => {
        if (
            status === 'unavailable' &&
            previous !== 'unavailable' &&
            hasResults.value
        ) {
            announce(CANT_LOAD_MORE);
        }
    },
);

onBeforeUnmount(() => clearTimeout(debounceTimer));
</script>

<template>
    <div class="flex flex-col gap-8 py-8">
        <Head title="Search">
            <meta name="robots" content="noindex" />
        </Head>
        <h1 class="text-3xl font-semibold tracking-tight">Search</h1>

        <form role="search" @submit.prevent="visit(wantedQuery)">
            <label for="search-query" class="sr-only">{{
                includesTvShows
                    ? 'Search movies and TV shows by title'
                    : 'Search movies by title'
            }}</label>
            <div class="relative max-w-2xl">
                <SearchIcon
                    class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted"
                    aria-hidden="true"
                />
                <input
                    id="search-query"
                    ref="input"
                    v-model="text"
                    v-focus="query === ''"
                    :data-autofocus="query === '' ? '' : undefined"
                    type="search"
                    name="q"
                    autocomplete="off"
                    enterkeyhint="search"
                    :maxlength="maximumQueryLength"
                    placeholder="Search by title"
                    class="h-11 w-full rounded-xl border border-control bg-surface pr-4 pl-12 text-base outline-none focus-visible:border-accent focus-visible:ring-2 focus-visible:ring-accent/40 md:h-9"
                />
            </div>
        </form>

        <div
            v-if="includesTvShows && !isSearching"
            role="group"
            aria-label="Browse"
            class="flex flex-wrap gap-2"
        >
            <Button
                v-for="tabChoice in BROWSE_TABS"
                :key="tabChoice.value"
                type="button"
                size="sm"
                :variant="browseTab === tabChoice.value ? 'default' : 'outline'"
                :aria-pressed="browseTab === tabChoice.value"
                :data-browse-tab="tabChoice.value"
                @click="chooseTab(tabChoice.value)"
            >
                {{ tabChoice.label }}
            </Button>
        </div>

        <section
            v-if="pageError !== null"
            data-slot="search-unavailable"
            class="flex max-w-2xl flex-col items-start gap-3 rounded-xl border border-line bg-surface p-6"
        >
            <h2 class="text-lg font-semibold">{{ pageError }}</h2>
            <p
                v-if="pageError === CANT_SEARCH || pageError === cantBrowse"
                class="text-sm text-muted"
            >
                Please try again in a moment.
            </p>
            <Button type="button" :disabled="pending" @click="retry">
                <RotateCw aria-hidden="true" />
                Retry
            </Button>
        </section>

        <section
            v-if="noResults"
            data-slot="search-no-results"
            class="flex flex-col gap-2"
        >
            <h2 class="text-lg font-semibold">
                No {{ includesTvShows ? 'movies or TV shows' : 'movies' }} match
                "{{ query }}"
            </h2>
            <p class="text-sm text-muted">
                Check the spelling, or try fewer or different words
            </p>
        </section>

        <p
            v-if="browseEmpty"
            data-slot="search-browse-empty"
            class="text-sm text-muted"
        >
            No {{ browseNoun }} to show right now. Try a search by title.
        </p>

        <ul v-if="firstLoading" data-slot="search-loading" :class="GRID">
            <li v-for="index in SHIMMER_CARDS" :key="index">
                <PosterCardSkeleton />
            </li>
        </ul>

        <section
            v-else-if="showsResults"
            data-slot="search-results"
            aria-labelledby="search-results-heading"
            class="flex flex-col gap-6 transition-opacity"
            :class="{ 'opacity-50': settling }"
            :aria-busy="settling"
        >
            <h2
                id="search-results-heading"
                class="text-sm text-muted tabular-nums"
            >
                {{
                    isSearching
                        ? resultCount(totalResults ?? 0)
                        : `Popular ${browseNoun}`
                }}
            </h2>
            <div
                v-if="includesTvShows && isSearching"
                role="group"
                aria-label="Filter results by kind"
                class="flex flex-wrap gap-2"
            >
                <Button
                    v-for="chip in KIND_CHIPS"
                    :key="chip.value"
                    type="button"
                    size="sm"
                    :variant="kindFilter === chip.value ? 'default' : 'outline'"
                    :aria-pressed="kindFilter === chip.value"
                    :data-kind-chip="chip.value"
                    @click="kindFilter = chip.value"
                >
                    {{ chip.label }}
                    <span class="tabular-nums">{{ counts[chip.value] }}</span>
                </Button>
            </div>
            <InfiniteScroll
                data="results"
                manual
                :params="showMoreParams"
                :items-element="() => resultsList"
                class="flex flex-col gap-8"
            >
                <template #previous="{ loading, fetch, hasMore }">
                    <div v-if="hasMore" class="flex justify-center">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="loading || settling"
                            @click="fetch"
                        >
                            <LoaderCircle
                                v-if="loading"
                                class="animate-spin"
                                aria-hidden="true"
                            />
                            Show earlier results
                        </Button>
                    </div>
                </template>

                <p
                    v-if="shownResults.length === 0"
                    data-slot="search-kind-empty"
                    class="text-sm text-muted"
                >
                    No {{ emptyKindLabel }} in these results. Show more results
                    to look further.
                </p>
                <ul ref="resultsList" :class="GRID">
                    <li
                        v-for="(movie, index) in shownResults"
                        :key="cardKey(movie)"
                    >
                        <PosterCard
                            :image-base-url="imageBaseUrl"
                            :movie="withLookedUpRatings(movie)"
                            :facts="
                                movie.kind === 'tv'
                                    ? undefined
                                    : userMovies[movie.tmdbId]
                            "
                            :tv-watchlisted="
                                movie.kind === 'tv'
                                    ? (userTvShows[movie.tmdbId]?.watchlisted ??
                                      false)
                                    : undefined
                            "
                            source="search"
                            :eager="index < EAGER_CARDS"
                            :rating-pending="isLookingUp(movie)"
                            @toggle-tv-watchlist="
                                toggleTvWatchlist(movie.tmdbId, $event)
                            "
                        />
                    </li>
                </ul>

                <template #next="{ loading, fetch, hasMore }">
                    <ul
                        v-if="loading"
                        :class="GRID"
                        data-slot="search-more-loading"
                    >
                        <li v-for="index in EAGER_CARDS" :key="index">
                            <PosterCardSkeleton />
                        </li>
                    </ul>
                    <p
                        v-if="moreError !== null && !loading"
                        data-slot="search-more-error"
                        class="text-center text-sm text-muted"
                    >
                        {{ moreError }}
                    </p>
                    <div v-if="hasMore" class="flex justify-center">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="loading || settling"
                            @click="fetch"
                        >
                            <LoaderCircle
                                v-if="loading"
                                class="animate-spin"
                                aria-hidden="true"
                            />
                            Show more results
                        </Button>
                    </div>
                </template>
            </InfiniteScroll>
        </section>
    </div>
</template>
