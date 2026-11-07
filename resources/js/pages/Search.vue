<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { LoaderCircle, RotateCw, Search as SearchIcon } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PosterCard from '@/components/movie/PosterCard.vue';
import type { PosterCardMovie } from '@/components/movie/PosterCard.vue';
import PosterCardSkeleton from '@/components/movie/PosterCardSkeleton.vue';
import { Button } from '@/components/ui/button';
import { announce } from '@/composables/useAnnouncer';
import { search } from '@/routes';
import type { UserMovies } from '@/types';

const props = defineProps<{
    imageBaseUrl: string;
    /** The query searched, trimmed and cut; shorter than the minimum, it wasn't searched. */
    query: string;
    minimumQueryLength: number;
    maximumQueryLength: number;
    results: { data: PosterCardMovie[] };
    totalResults: number | null;
    /** How the Catalog answered the search; null with no search. */
    status: 'fresh' | 'stale' | 'unavailable' | null;
    trending: PosterCardMovie[];
    userMovies: UserMovies;
}>();

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
const settling = computed(() => typing.value || pending.value);
const hasResults = computed(() => props.results.data.length > 0);
const firstLoading = computed(
    () => settling.value && wantedQuery.value !== '' && !hasResults.value,
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
        ? CANT_SEARCH
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
const showsResults = computed(() => hasResults.value && failure.value === null);
const noResults = computed(
    () =>
        !settling.value &&
        failure.value === null &&
        (props.status === 'fresh' || props.status === 'stale') &&
        props.totalResults === 0,
);
const showsTrending = computed(
    () =>
        !firstLoading.value &&
        !showsResults.value &&
        failure.value === null &&
        props.trending.length > 0,
);

function resultCount(total: number): string {
    return `${new Intl.NumberFormat('en-US').format(total)} ${total === 1 ? 'result' : 'results'}`;
}

/** Each "Show more" reloads `userMovies` and `status` with the page, and reports a failed visit inline. */
const showMoreParams = {
    only: ['userMovies', 'status'],
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

    router.get(search.url(), query === '' ? {} : { q: query }, {
        replace: true,
        preserveState: true,
        preserveScroll: true,
        only: [
            'query',
            'results',
            'totalResults',
            'status',
            'trending',
            'userMovies',
        ],
        reset: ['results', 'userMovies'],
        onStart: () => {
            pending.value = true;
        },
        onSuccess: (page) => {
            if (query !== '') {
                announceSettled(page.props as unknown as typeof props);
            }
        },
        onHttpException: (response) => {
            fail(response.status === 429 ? TOO_FAST : CANT_SEARCH);

            return false;
        },
        onNetworkError: () => {
            fail(CANT_SEARCH);

            return false;
        },
        onFinish: () => {
            if (visitNumber === latestVisit) {
                pending.value = false;
            }
        },
    });
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
            ? CANT_SEARCH
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
            <label for="search-query" class="sr-only"
                >Search movies by title</label
            >
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
                    class="h-12 w-full rounded-xl border border-control bg-surface pr-4 pl-12 text-base outline-none focus-visible:border-accent focus-visible:ring-2 focus-visible:ring-accent/40"
                />
            </div>
        </form>

        <section
            v-if="pageError !== null"
            data-slot="search-unavailable"
            class="flex max-w-2xl flex-col items-start gap-3 rounded-xl border border-line bg-surface p-6"
        >
            <h2 class="text-lg font-semibold">{{ pageError }}</h2>
            <p v-if="pageError === CANT_SEARCH" class="text-sm text-muted">
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
            <h2 class="text-lg font-semibold">No movies match "{{ query }}"</h2>
            <p class="text-sm text-muted">
                Check the spelling, or try fewer or different words
            </p>
        </section>

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
                {{ resultCount(totalResults ?? 0) }}
            </h2>
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

                <ul ref="resultsList" :class="GRID">
                    <li
                        v-for="(movie, index) in results.data"
                        :key="movie.tmdbId"
                    >
                        <PosterCard
                            :image-base-url="imageBaseUrl"
                            :movie="movie"
                            :facts="userMovies[movie.tmdbId]"
                            source="search"
                            :eager="index < EAGER_CARDS"
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

        <section
            v-if="showsTrending"
            data-slot="search-trending"
            aria-labelledby="search-trending-heading"
            class="flex flex-col gap-6"
        >
            <h2 id="search-trending-heading" class="text-xl font-semibold">
                Trending this week
            </h2>
            <ul :class="GRID">
                <li v-for="(movie, index) in trending" :key="movie.tmdbId">
                    <PosterCard
                        :image-base-url="imageBaseUrl"
                        :movie="movie"
                        :facts="userMovies[movie.tmdbId]"
                        source="search"
                        :eager="index < EAGER_CARDS"
                    />
                </li>
            </ul>
        </section>
    </div>
</template>
