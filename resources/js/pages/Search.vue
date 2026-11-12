<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { LoaderCircle, RotateCw, Search as SearchIcon } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PosterCard from '@/components/shared/PosterCard.vue';
import type { PosterCardTitle } from '@/components/shared/PosterCard.vue';
import PosterCardSkeleton from '@/components/shared/PosterCardSkeleton.vue';
import StreamingServiceCheckbox from '@/components/shared/StreamingServiceCheckbox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Checkbox } from '@/components/ui/checkbox';
import { announce } from '@/composables/useAnnouncer';
import { useLookedUpRatings } from '@/composables/useLookedUpRatings';
import { useTvShowWatchlistToggle } from '@/composables/useTvShowWatchlistToggle';
import type { TvShowWatchlistFacts } from '@/composables/useTvShowWatchlistToggle';
import { cardKey, filterByKind, kindCounts } from '@/lib/searchKinds';
import type { CardKind, KindFilter } from '@/lib/searchKinds';
import { search } from '@/routes';
import type { UserMovies } from '@/types';

type SortValue = 'top_rated' | 'popular';

/** How many Streaming services show before "More". */
const SHOWN_STREAMING_SERVICES = 12;

const SORT_OPTIONS: { value: SortValue; label: string; noun: string }[] = [
    { value: 'top_rated', label: 'Top rated', noun: 'top rated' },
    { value: 'popular', label: 'Popular', noun: 'popular' },
];

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
    /** The genres the tab offers; none while searching by Name or while the Catalog can't list them. */
    genres: { id: number; name: string }[];
    /** The genre Browse is narrowed to, or null. */
    genre: number | null;
    /** Whether Browse is limited to Titles with a Highly rated TMDB score. */
    highlyRated: boolean;
    /** The Streaming services the tab offers in the visitor's country, most prominent first. */
    streamingServices: { id: number; name: string }[];
    /** The ids of the Streaming services Browse is narrowed to, any of them. */
    selectedStreamingServices: number[];
    /** The most Streaming services that can be chosen at once. */
    maximumStreamingServices: number;
    /** The order Browse is in: the one chosen, else the default of the filters. */
    sort: SortValue;
    /** The order the URL chose, or null when it chose none. */
    explicitSort: SortValue | null;
    results: { data: SearchResult[] };
    totalResults: number | null;
    /** How the Catalog answered the search, or Browse while there is no query. */
    status: 'fresh' | 'stale' | 'unavailable';
    userMovies: UserMovies;
    /** Whether the viewer has each TV Show of the results on their Watchlist, by catalog id. */
    userTvShows: TvShowWatchlistFacts;
}>();

const { toggle: toggleTvWatchlist } = useTvShowWatchlistToggle(() => 'search');

/** Whether the page shows a search by Name, its query being long enough to search; if not, it is Browse. */
function searchesByName(): boolean {
    return Array.from(props.query).length >= props.minimumQueryLength;
}

const { withLookedUpRatings, isLookingUp } = useLookedUpRatings(
    () =>
        searchesByName()
            ? {
                  q: props.query,
                  tab: '',
                  genre: '',
                  services: '',
                  sort: '',
                  highly_rated: '',
              }
            : {
                  q: '',
                  tab: props.tab,
                  genre: props.genre === null ? '' : String(props.genre),
                  services: props.selectedStreamingServices.join(','),
                  sort: props.explicitSort ?? '',
                  highly_rated: props.highlyRated ? '1' : '',
              },
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
/** A tab or filter was chosen and its first page hasn't arrived, so the list it replaces isn't shown meanwhile. */
const replacingList = ref(false);
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
const isSearching = computed(searchesByName);
/** The Browse tab chosen; it follows the page's, which Back and forward change. */
const browseTab = ref(props.tab);
const browseNoun = computed(() =>
    browseTab.value === 'tv' ? 'TV shows' : 'movies',
);
/** The genre chosen; it follows the page's while Browse shows, which Back and forward change. */
const genreChoice = ref<number | null>(props.genre);
/** The Highly rated toggle, kept with Browse while filters change. */
const highlyRatedChoice = ref(props.highlyRated);
/** The Streaming services chosen, by id; they follow the page's while Browse shows. */
const serviceChoices = ref<number[]>([...props.selectedStreamingServices]);
const topServices = computed(() =>
    props.streamingServices.slice(0, SHOWN_STREAMING_SERVICES),
);
const moreServices = computed(() =>
    props.streamingServices.slice(SHOWN_STREAMING_SERVICES),
);
/** Whether "More" is open: it opens when a service behind it is chosen. */
const moreServicesOpen = ref(
    moreServices.value.some((service) =>
        serviceChoices.value.includes(service.id),
    ),
);
const serviceSearch = ref('');
const foundMoreServices = computed(() => {
    const wanted = serviceSearch.value.trim().toLowerCase();

    return moreServices.value.filter((service) =>
        service.name.toLowerCase().includes(wanted),
    );
});
const atServiceLimit = computed(
    () => serviceChoices.value.length >= props.maximumStreamingServices,
);
/** The sort chosen, null for the default of the filters; it follows the page's while Browse shows. */
const sortChoice = ref<SortValue | null>(props.explicitSort);
/** The sort the select shows: the one chosen, else the page's default. */
const shownSort = computed(() => sortChoice.value ?? props.sort);
const shownSortLabel = computed(
    () =>
        SORT_OPTIONS.find((option) => option.value === shownSort.value)
            ?.label ?? 'Popular',
);
const activeFilterCount = computed(
    () =>
        (genreChoice.value === null ? 0 : 1) +
        (highlyRatedChoice.value ? 1 : 0) +
        (serviceChoices.value.length === 0 ? 0 : 1),
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
    () => settling.value && (!hasResults.value || replacingList.value),
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
 * for isn't asked again unless its answer was a failure, or `force` says to. A tab or filter chosen adds a history
 * entry instead (`replace` off), so Back restores the previous ones.
 */
function visit(query: string, force = false, replace = true): void {
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

    if (query !== '') {
        genreChoice.value = null;
        highlyRatedChoice.value = false;
        serviceChoices.value = [];
    }

    // The tab goes along with a Name too, which ignores it, so the genres offered stay those of the tab.
    const params: {
        q?: string;
        tab?: CardKind;
        genre?: number;
        services?: string;
        sort?: SortValue;
        highly_rated?: '1';
    } = {
        ...(query === '' ? {} : { q: query }),
        ...(browseTab.value === 'tv' ? { tab: browseTab.value } : {}),
        ...(query === '' && genreChoice.value !== null
            ? { genre: genreChoice.value }
            : {}),
        ...(query === '' && serviceChoices.value.length > 0
            ? { services: sortedServiceChoices().join(',') }
            : {}),
        ...(query === '' && sortChoice.value !== null
            ? { sort: sortChoice.value }
            : {}),
        ...(query === '' && highlyRatedChoice.value
            ? { highly_rated: '1' }
            : {}),
    };

    router.get(search.url(), params, {
        replace,
        preserveState: true,
        preserveScroll: true,
        only: [
            'query',
            'tab',
            'genres',
            'genre',
            'highlyRated',
            'streamingServices',
            'selectedStreamingServices',
            'maximumStreamingServices',
            'sort',
            'explicitSort',
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
            } else {
                announceBrowsed(page.props as unknown as typeof props);
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
                replacingList.value = false;
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
    replacingList.value = true;
    visit('', true, false);
}

/** Browses with the genre chosen, or none: it replaces a typed Name, and its first page replaces the list. */
function chooseGenre(genre: number | null): void {
    genreChoice.value = genre;
    clearFilterVisit();
}

/** The genre picked in the select, `Any genre` being none. */
function onGenreChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    chooseGenre(value === '' ? null : Number(value));
}

/** The chosen services in id order, as the URL keeps them. */
function sortedServiceChoices(): number[] {
    return [...serviceChoices.value].sort((a, b) => a - b);
}

/** Browses with the service added or dropped: it replaces a typed Name, and its first page replaces the list. */
function toggleService(id: number, chosen: boolean): void {
    serviceChoices.value = chosen
        ? [...serviceChoices.value.filter((choice) => choice !== id), id]
        : serviceChoices.value.filter((choice) => choice !== id);
    clearFilterVisit();
}

/** The sort picked in the select; like a filter, it leaves a search by Name. */
function onSortChange(event: Event): void {
    sortChoice.value = (event.target as HTMLSelectElement).value as SortValue;
    clearFilterVisit();
}

/** Drops every filter and browses again; the sort chosen stays. */
function clearFilters(): void {
    genreChoice.value = null;
    highlyRatedChoice.value = false;
    serviceChoices.value = [];
    clearFilterVisit();
}

function clearFilterVisit(): void {
    text.value = '';
    replacingList.value = true;
    visit('', true, false);
}

/** Announces a settled Browse page: what is shown, or that it failed. */
function announceBrowsed(page: typeof props): void {
    const genre = page.genres.find((option) => option.id === page.genre);
    const order = SORT_OPTIONS.find((option) => option.value === page.sort);
    const services = page.selectedStreamingServices.length;

    announce(
        page.status === 'unavailable'
            ? failureMessage.value
            : `Showing ${order?.noun ?? 'popular'} ${page.tab === 'tv' ? 'TV shows' : 'movies'}${genre === undefined ? '' : ` in ${genre.name}`}${services === 0 ? '' : ` on ${services} streaming ${services === 1 ? 'service' : 'services'}`}`,
    );
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
    clearTimeout(debounceTimer);

    // Text that asks for nothing new, as when a filter empties the input, isn't waited on.
    if (
        wantedQuery.value === requestedQuery.value &&
        failure.value === null &&
        props.status !== 'unavailable'
    ) {
        typing.value = false;

        return;
    }

    typing.value = true;
    debounceTimer = setTimeout(
        () => visit(wantedQuery.value),
        DEBOUNCE_MILLISECONDS,
    );
});

watch(
    () => props.genre,
    (genre) => {
        if (!isSearching.value) {
            genreChoice.value = genre;
        }
    },
);

watch(
    () => props.highlyRated,
    (highlyRated) => {
        if (!isSearching.value) {
            highlyRatedChoice.value = highlyRated;
        }
    },
);

watch(
    () => props.selectedStreamingServices,
    (services) => {
        if (!isSearching.value) {
            serviceChoices.value = [...services];

            if (
                moreServices.value.some((service) =>
                    services.includes(service.id),
                )
            ) {
                moreServicesOpen.value = true;
            }
        }
    },
);

watch(
    () => props.explicitSort,
    (sort) => {
        if (!isSearching.value) {
            sortChoice.value = sort;
        }
    },
);

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

        <div
            role="group"
            aria-label="Filters"
            data-slot="search-filters"
            class="flex flex-wrap items-end gap-x-4 gap-y-3"
        >
            <div
                v-if="genres.length > 0"
                class="flex w-full flex-col gap-1.5 sm:w-56"
            >
                <label for="browse-genre" class="text-sm text-muted"
                    >Genre</label
                >
                <NativeSelect
                    id="browse-genre"
                    touch
                    :value="genreChoice ?? ''"
                    @change="onGenreChange"
                >
                    <option value="">Any genre</option>
                    <option
                        v-for="option in genres"
                        :key="option.id"
                        :value="option.id"
                        :selected="option.id === genreChoice"
                    >
                        {{ option.name }}
                    </option>
                </NativeSelect>
            </div>
            <label class="flex items-center gap-2 text-sm text-muted">
                <Checkbox
                    id="browse-highly-rated"
                    :model-value="highlyRatedChoice"
                    @update:model-value="
                        (checked) => {
                            highlyRatedChoice = checked === true;
                            clearFilterVisit();
                        }
                    "
                />
                Highly rated (TMDB score 7+)
            </label>
            <fieldset
                v-if="streamingServices.length > 0"
                data-slot="search-streaming-services"
                class="flex w-full min-w-0 flex-col gap-2"
            >
                <legend class="text-sm text-muted">Streaming service</legend>
                <p
                    v-if="atServiceLimit"
                    data-slot="search-service-limit"
                    class="text-sm text-muted"
                >
                    You can choose up to {{ maximumStreamingServices }}
                    streaming services; clear one to choose another.
                </p>
                <ul class="flex flex-wrap gap-2">
                    <li
                        v-for="service in topServices"
                        :key="service.id"
                        class="contents"
                    >
                        <StreamingServiceCheckbox
                            :name="service.name"
                            :checked="serviceChoices.includes(service.id)"
                            :disabled="
                                atServiceLimit &&
                                !serviceChoices.includes(service.id)
                            "
                            @change="
                                (chosen) => toggleService(service.id, chosen)
                            "
                        />
                    </li>
                </ul>
                <div v-if="moreServices.length > 0" class="flex flex-col gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="self-start"
                        data-slot="search-more-services"
                        :aria-expanded="moreServicesOpen"
                        aria-controls="more-streaming-services"
                        @click="moreServicesOpen = !moreServicesOpen"
                    >
                        {{ moreServicesOpen ? 'Fewer' : 'More' }}
                    </Button>
                    <div
                        v-show="moreServicesOpen"
                        id="more-streaming-services"
                        class="flex flex-col gap-2"
                    >
                        <label
                            for="browse-service-search"
                            class="text-sm text-muted"
                            >Find a streaming service</label
                        >
                        <Input
                            id="browse-service-search"
                            v-model="serviceSearch"
                            type="search"
                            autocomplete="off"
                            class="sm:w-64"
                        />
                        <ul class="flex flex-wrap gap-2">
                            <li
                                v-for="service in foundMoreServices"
                                :key="service.id"
                                class="contents"
                            >
                                <StreamingServiceCheckbox
                                    :name="service.name"
                                    :checked="
                                        serviceChoices.includes(service.id)
                                    "
                                    :disabled="
                                        atServiceLimit &&
                                        !serviceChoices.includes(service.id)
                                    "
                                    @change="
                                        (chosen) =>
                                            toggleService(service.id, chosen)
                                    "
                                />
                            </li>
                        </ul>
                        <p
                            v-if="foundMoreServices.length === 0"
                            class="text-sm text-muted"
                        >
                            No streaming service matches
                        </p>
                    </div>
                </div>
            </fieldset>
            <div class="flex w-full flex-col gap-1.5 sm:w-44">
                <label for="browse-sort" class="text-sm text-muted"
                    >Sort by</label
                >
                <NativeSelect
                    id="browse-sort"
                    touch
                    :value="shownSort"
                    @change="onSortChange"
                >
                    <option
                        v-for="option in SORT_OPTIONS"
                        :key="option.value"
                        :value="option.value"
                        :selected="option.value === shownSort"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>
            </div>
            <p
                v-if="activeFilterCount > 0 && !replacingList"
                data-slot="search-filter-count"
                class="text-sm text-muted tabular-nums"
            >
                {{ activeFilterCount }}
                {{ activeFilterCount === 1 ? 'filter' : 'filters' }} active
            </p>
            <Button
                v-if="activeFilterCount > 0"
                type="button"
                variant="ghost"
                size="sm"
                data-slot="search-clear-filters"
                @click="clearFilters"
            >
                Clear filters
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

        <section
            v-if="browseEmpty && activeFilterCount > 0"
            data-slot="search-no-matches"
            class="flex flex-col items-start gap-2"
        >
            <h2 class="text-lg font-semibold">
                No {{ browseNoun }} match these filters
            </h2>
            <p class="text-sm text-muted">
                Try a different genre, or clear the filters
            </p>
            <Button type="button" variant="outline" @click="clearFilters">
                Clear filters
            </Button>
        </section>

        <p
            v-else-if="browseEmpty"
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
                        : `${shownSortLabel} ${browseNoun}`
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
