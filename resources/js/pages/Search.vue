<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    LoaderCircle,
    RotateCw,
    Search as SearchIcon,
    SlidersHorizontal,
    X,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import PosterCard from '@/components/shared/PosterCard.vue';
import type { PosterCardTitle } from '@/components/shared/PosterCard.vue';
import PosterCardSkeleton from '@/components/shared/PosterCardSkeleton.vue';
import StreamingServiceCheckbox from '@/components/shared/StreamingServiceCheckbox.vue';
import { Button } from '@/components/ui/button';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
} from '@/components/ui/input-group';
import { NativeSelect } from '@/components/ui/native-select';
import { Checkbox } from '@/components/ui/checkbox';
import { announce } from '@/composables/useAnnouncer';
import { useLookedUpRatings } from '@/composables/useLookedUpRatings';
import { useTvShowWatchlistToggle } from '@/composables/useTvShowWatchlistToggle';
import type { TvShowWatchlistFacts } from '@/composables/useTvShowWatchlistToggle';
import { cardKey } from '@/lib/searchKinds';
import { rankServices } from '@/lib/streamingServiceSearch';
import type { CardKind } from '@/lib/searchKinds';
import { search } from '@/routes';
import type { UserMovies } from '@/types';

type SortValue = 'top_rated' | 'popular';
type RuntimeBandValue = 'under_90' | '90_to_120' | 'over_120';

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
    /** The Browse tab shown while there is no query: Movies or TV Shows. */
    tab: CardKind;
    /** The genres the tab offers; none while searching by Name or while the Catalog can't list them. */
    genres: { id: number; name: string }[];
    /** The genre Browse is narrowed to, or null. */
    genre: number | null;
    /** Whether Browse is limited to Titles with a Highly rated TMDB score. */
    highlyRated: boolean;
    /** Whether the viewer is signed in, so Browse can leave out the Titles they have seen. */
    canHideSeen: boolean;
    /** Whether the viewer chose to show the Titles they have seen. */
    showSeen: boolean;
    /** The era Browse is narrowed to, or null. */
    era: string | null;
    /** The Movie runtime band Browse is narrowed to, or null. */
    runtimeBand: RuntimeBandValue | null;
    /** The curated original languages, each by its native name; the same on both tabs. */
    languages: { code: string; name: string }[];
    /** The original language code Browse is narrowed to, or null. */
    language: string | null;
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
                  era: '',
                  runtime: '',
                  language: '',
                  show_seen: '',
              }
            : {
                  q: '',
                  tab: props.tab,
                  genre: props.genre === null ? '' : String(props.genre),
                  services: props.selectedStreamingServices.join(','),
                  sort: props.explicitSort ?? '',
                  highly_rated: props.highlyRated ? '1' : '',
                  era: props.era ?? '',
                  runtime: props.runtimeBand ?? '',
                  language: props.language ?? '',
                  show_seen: props.showSeen ? '1' : '',
              },
    () => props.results.data,
    () => props.looksUpImdbRatings,
);

const BROWSE_TABS: { value: CardKind; label: string }[] = [
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
/** The way back from the advanced filters, which takes focus when they open. */
const backButton = ref<{ $el: HTMLElement } | null>(null);
/** The search field's component; its root element is the input. */
const input = ref<{ $el: HTMLInputElement } | null>(null);
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
/** Whether the Titles seen are shown; it follows the page's while Browse shows. */
const showSeenChoice = ref(props.showSeen);
/** The era chosen; it follows the page's while Browse shows. */
const eraChoice = ref<string | null>(props.era);
/** The Movie runtime band chosen; it follows the page's while Browse shows. */
const runtimeBandChoice = ref<RuntimeBandValue | null>(props.runtimeBand);
/** The original language chosen; it follows the page's while Browse shows. */
const languageChoice = ref<string | null>(props.language);
/** The Streaming services chosen, by id; they follow the page's while Browse shows. */
const serviceChoices = ref<number[]>([...props.selectedStreamingServices]);
const serviceSearch = ref('');

/** The services listed: the recommended ones, with any chosen beyond them, or the best matches while searching. */
const listedServices = computed(() => {
    const query = serviceSearch.value.trim();

    if (query === '') {
        const recommended = props.streamingServices.slice(
            0,
            SHOWN_STREAMING_SERVICES,
        );
        const chosenBeyond = props.streamingServices
            .slice(SHOWN_STREAMING_SERVICES)
            .filter((service) => serviceChoices.value.includes(service.id));

        return [...recommended, ...chosenBeyond];
    }

    return rankServices(props.streamingServices, query);
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
        (showSeenChoice.value ? 1 : 0) +
        (eraChoice.value === null ? 0 : 1) +
        (runtimeBandChoice.value === null ? 0 : 1) +
        (languageChoice.value === null ? 0 : 1) +
        (serviceChoices.value.length === 0 ? 0 : 1),
);
const cantBrowse = computed(
    () => `We can't load ${browseNoun.value} right now`,
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
/** Whether the advanced filters stand in for the search box; a page that lands with filters in force starts there. */
const advancedOpen = ref(activeFilterCount.value > 0 && !isSearching.value);
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
            !hasResults.value),
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
    const focusedBefore = document.activeElement;
    requestedQuery.value = query;
    failure.value = null;
    moreFailure.value = null;

    if (query !== '') {
        genreChoice.value = null;
        highlyRatedChoice.value = false;
        showSeenChoice.value = false;
        eraChoice.value = null;
        runtimeBandChoice.value = null;
        languageChoice.value = null;
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
        show_seen?: '1';
        era?: string;
        runtime?: RuntimeBandValue;
        language?: string;
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
        ...(query === '' && showSeenChoice.value ? { show_seen: '1' } : {}),
        ...(query === '' && eraChoice.value !== null
            ? { era: eraChoice.value }
            : {}),
        ...(query === '' && runtimeBandChoice.value !== null
            ? { runtime: runtimeBandChoice.value }
            : {}),
        ...(query === '' && languageChoice.value !== null
            ? { language: languageChoice.value }
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
            'canHideSeen',
            'showSeen',
            'era',
            'runtimeBand',
            'language',
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

            keepFocus(focusedBefore);
        },
    });
}

/**
 * Puts focus back on the control that had it when a visit began, as the page moves focus to its start after each
 * visit, which would take a keyboard user off a filter they have just changed. A control that left the page, like
 * the button that swaps the search box for the filters, isn't restored: the page's start, marked `data-autofocus`, is
 * where focus belongs then.
 */
function keepFocus(element: Element | null): void {
    void nextTick(() => {
        if (element instanceof HTMLElement && element.isConnected) {
            element.focus({ preventScroll: true });
        }
    });
}

/** The kind picked in the dropdown; it browses that tab. */
function onKindChange(event: Event): void {
    chooseTab((event.target as HTMLSelectElement).value as CardKind);
}

/** Browses the other tab: its first page replaces the list, and the tab is kept in the URL. */
function chooseTab(tab: CardKind): void {
    if (tab === browseTab.value) {
        return;
    }

    browseTab.value = tab;
    if (tab === 'tv') {
        runtimeBandChoice.value = null;
    }
    replacingList.value = true;
    visit('', true, false);
}

/** Browses with the genre chosen, or none: it replaces a typed Name, and its first page replaces the list. */
function chooseGenre(genre: number | null): void {
    genreChoice.value = genre;
    clearFilterVisit();
}

/** Browses with the era chosen, or none. */
function onEraChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    eraChoice.value = value === '' ? null : value;
    clearFilterVisit();
}

/** Browses with the Movie runtime band chosen, switching to Movies when needed. */
function onRuntimeBandChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    runtimeBandChoice.value = value === '' ? null : (value as RuntimeBandValue);

    if (runtimeBandChoice.value !== null && browseTab.value === 'tv') {
        browseTab.value = 'movie';
        replacingList.value = true;
    }

    clearFilterVisit();
}

/** The original language picked in the select, `Any language` being none. */
function onLanguageChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    languageChoice.value = value === '' ? null : value;
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

/** Swaps the search box for the advanced filters; whatever name was typed is dropped, and with it its results. */
function openAdvanced(): void {
    text.value = '';
    advancedOpen.value = true;

    if (props.query !== '') {
        clearFilterVisit();
    }

    // The button that was pressed leaves the page, so focus goes to the first thing of the panel.
    nextTick(() => backButton.value?.$el.focus());
}

/** Goes back to the search box with every filter, the sort and the tab as they start, browsing again if any was set. */
function backToSearch(): void {
    const changed =
        activeFilterCount.value > 0 ||
        sortChoice.value !== null ||
        browseTab.value !== 'movie';
    advancedOpen.value = false;
    resetFilters();
    sortChoice.value = null;
    browseTab.value = 'movie';

    if (changed) {
        clearFilterVisit();
    }
}

/** Puts every filter back to none, leaving the sort and the tab as they are. */
function resetFilters(): void {
    genreChoice.value = null;
    highlyRatedChoice.value = false;
    showSeenChoice.value = false;
    eraChoice.value = null;
    runtimeBandChoice.value = null;
    languageChoice.value = null;
    serviceChoices.value = [];
}

/** Drops every filter and browses again; the sort chosen stays. */
function clearFilters(): void {
    resetFilters();
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
    const language = page.languages.find(
        (option) => option.code === page.language,
    );

    announce(
        page.status === 'unavailable'
            ? failureMessage.value
            : `Showing ${order?.noun ?? 'popular'} ${page.tab === 'tv' ? 'TV shows' : 'movies'}${genre === undefined ? '' : ` in ${genre.name}`}${language === undefined ? '' : ` originally in ${language.name}`}${services === 0 ? '' : ` on ${services} streaming ${services === 1 ? 'service' : 'services'}`}`,
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
    input.value?.$el.focus();
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
    () => props.showSeen,
    (showSeen) => {
        if (!isSearching.value) {
            showSeenChoice.value = showSeen;
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
    () => props.era,
    (era) => {
        if (!isSearching.value) {
            eraChoice.value = era;
        }
    },
);

watch(
    () => props.language,
    (language) => {
        if (!isSearching.value) {
            languageChoice.value = language;
        }
    },
);

watch(
    () => props.runtimeBand,
    (runtimeBand) => {
        if (!isSearching.value) {
            runtimeBandChoice.value = runtimeBand;
        }
    },
);

watch(
    () => props.selectedStreamingServices,
    (services) => {
        if (!isSearching.value) {
            serviceChoices.value = [...services];
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
        <h1 class="sr-only">Search</h1>

        <template v-if="!advancedOpen">
            <form role="search" @submit.prevent="visit(wantedQuery)">
                <label for="search-query" class="sr-only"
                    >Search movies and TV shows by title</label
                >
                <InputGroup
                    class="h-14 w-full border-control bg-surface [--input-group-inset:0.5rem] [--input-group-radius:var(--radius-2xl)]"
                >
                    <InputGroupAddon class="pl-5">
                        <SearchIcon class="size-6" aria-hidden="true" />
                    </InputGroupAddon>
                    <InputGroupInput
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
                        class="h-full text-lg md:text-lg"
                    />
                    <InputGroupAddon align="inline-end" class="pr-4">
                        <InputGroupButton
                            type="button"
                            data-slot="advanced-toggle"
                            variant="outline"
                            size="sm"
                            class="h-10 px-4 text-base"
                            @click="openAdvanced"
                        >
                            <SlidersHorizontal aria-hidden="true" />
                            Advanced
                        </InputGroupButton>
                    </InputGroupAddon>
                </InputGroup>
                <p
                    data-slot="search-scope-note"
                    class="mt-2 text-sm text-muted"
                >
                    Searching by name covers movies and TV shows together. Use
                    Advanced to pick one and narrow it with filters.
                </p>
            </form>
        </template>

        <section
            v-else
            data-slot="advanced-filters"
            aria-labelledby="advanced-filters-title"
            class="flex flex-col gap-6"
        >
            <div class="flex flex-col gap-4">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="-ml-3 self-start"
                    ref="backButton"
                    data-autofocus
                    data-slot="back-to-search"
                    @click="backToSearch"
                >
                    <ArrowLeft aria-hidden="true" />
                    Back to search
                </Button>
                <div
                    class="flex flex-wrap items-start justify-between gap-x-8 gap-y-4"
                >
                    <div class="flex max-w-2xl flex-col gap-2">
                        <h2
                            id="advanced-filters-title"
                            class="text-3xl font-semibold tracking-tight"
                        >
                            Advanced filters
                        </h2>
                        <p class="text-muted">
                            Browse movies or TV shows one kind at a time and
                            narrow them down. Searching by name is paused here,
                            since a name can't be combined with filters. Go back
                            to search to look up a title.
                        </p>
                    </div>
                    <div
                        data-slot="advanced-header-controls"
                        class="flex flex-wrap items-end gap-4"
                    >
                        <div class="w-40">
                            <NativeSelect
                                id="browse-kind"
                                touch
                                aria-label="Browse"
                                :value="browseTab"
                                @change="onKindChange"
                            >
                                <option
                                    v-for="tabChoice in BROWSE_TABS"
                                    :key="tabChoice.value"
                                    :value="tabChoice.value"
                                    :selected="tabChoice.value === browseTab"
                                >
                                    {{ tabChoice.label }}
                                </option>
                            </NativeSelect>
                        </div>
                        <div class="w-48">
                            <NativeSelect
                                id="browse-sort"
                                touch
                                aria-label="Sort by"
                                :value="shownSort"
                                @change="onSortChange"
                            >
                                <option
                                    v-for="option in SORT_OPTIONS"
                                    :key="option.value"
                                    :value="option.value"
                                    :selected="option.value === shownSort"
                                >
                                    Sort: {{ option.label }}
                                </option>
                            </NativeSelect>
                        </div>
                    </div>
                </div>
            </div>

            <div
                role="group"
                aria-label="Filters"
                data-slot="search-filters"
                class="flex flex-col gap-6 rounded-xl border border-line bg-surface p-5"
            >
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-if="genres.length > 0"
                        class="flex w-full flex-col gap-1.5"
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
                    <div class="flex w-full flex-col gap-1.5">
                        <label for="browse-era" class="text-sm text-muted"
                            >Era</label
                        >
                        <NativeSelect
                            id="browse-era"
                            touch
                            :value="eraChoice ?? ''"
                            @change="onEraChange"
                        >
                            <option value="">Any era</option>
                            <option value="2020s">2020s</option>
                            <option value="2010s">2010s</option>
                            <option value="2000s">2000s</option>
                            <option value="1990s">1990s</option>
                            <option value="older">Older</option>
                        </NativeSelect>
                    </div>
                    <div class="flex w-full flex-col gap-1.5">
                        <label for="browse-runtime" class="text-sm text-muted"
                            >Runtime</label
                        >
                        <NativeSelect
                            id="browse-runtime"
                            touch
                            :value="runtimeBandChoice ?? ''"
                            @change="onRuntimeBandChange"
                        >
                            <option value="">Any runtime</option>
                            <option value="under_90">Under 90 minutes</option>
                            <option value="90_to_120">90–120 minutes</option>
                            <option value="over_120">Over 120 minutes</option>
                        </NativeSelect>
                    </div>
                    <div class="flex w-full flex-col gap-1.5">
                        <label for="browse-language" class="text-sm text-muted"
                            >Original language</label
                        >
                        <NativeSelect
                            id="browse-language"
                            touch
                            :value="languageChoice ?? ''"
                            @change="onLanguageChange"
                        >
                            <option value="">Any language</option>
                            <option
                                v-for="option in languages"
                                :key="option.code"
                                :value="option.code"
                                :lang="option.code"
                                :selected="option.code === languageChoice"
                            >
                                {{ option.name }}
                            </option>
                        </NativeSelect>
                    </div>
                </div>
                <p
                    v-if="runtimeBandChoice !== null"
                    data-slot="search-movies-only"
                    role="status"
                    class="text-sm text-muted"
                >
                    Movies only
                </p>
                <div
                    v-if="streamingServices.length > 0"
                    class="flex flex-col gap-3"
                >
                    <h3 class="text-base font-semibold">Where to watch</h3>

                    <fieldset
                        data-slot="search-streaming-services"
                        class="flex w-full min-w-0 flex-col gap-3"
                    >
                        <legend class="sr-only">Streaming service</legend>
                        <p
                            v-if="atServiceLimit"
                            data-slot="search-service-limit"
                            class="text-sm text-muted"
                        >
                            You can choose up to {{ maximumStreamingServices }}
                            streaming services; clear one to choose another.
                        </p>
                        <ul class="flex flex-wrap gap-2">
                            <!-- The search of the services is a compact field in the row, always there. -->
                            <li class="contents">
                                <InputGroup class="h-[34px] w-64 max-w-full">
                                    <InputGroupAddon>
                                        <SearchIcon
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        v-model="serviceSearch"
                                        type="text"
                                        role="searchbox"
                                        autocomplete="off"
                                        :placeholder="`Search all ${streamingServices.length} services`"
                                        aria-label="Search streaming services"
                                        @keydown.esc.prevent="
                                            serviceSearch = ''
                                        "
                                    />
                                    <InputGroupAddon
                                        v-if="serviceSearch !== ''"
                                        align="inline-end"
                                    >
                                        <InputGroupButton
                                            type="button"
                                            size="icon-xs"
                                            aria-label="Clear search"
                                            @click="serviceSearch = ''"
                                        >
                                            <X aria-hidden="true" />
                                        </InputGroupButton>
                                    </InputGroupAddon>
                                </InputGroup>
                            </li>

                            <li
                                v-for="service in listedServices"
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
                            v-if="serviceSearch.trim() !== ''"
                            role="status"
                            :class="
                                listedServices.length === 0
                                    ? 'text-sm text-muted'
                                    : 'sr-only'
                            "
                        >
                            {{
                                listedServices.length === 0
                                    ? 'No streaming service matches'
                                    : `${listedServices.length} streaming services found`
                            }}
                        </p>
                    </fieldset>
                </div>
                <div class="flex flex-col gap-3">
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
                    <label
                        v-if="canHideSeen"
                        class="flex items-center gap-2 text-sm text-muted"
                    >
                        <Checkbox
                            id="browse-show-seen"
                            :model-value="showSeenChoice"
                            @update:model-value="
                                (checked) => {
                                    showSeenChoice = checked === true;
                                    clearFilterVisit();
                                }
                            "
                        />
                        Show seen
                    </label>
                </div>
                <div
                    data-slot="search-filter-footer"
                    class="flex min-h-8 flex-wrap items-center justify-between gap-3 border-t border-line pt-4"
                >
                    <p
                        v-if="activeFilterCount > 0 && !replacingList"
                        data-slot="search-filter-count"
                        class="text-sm text-muted tabular-nums"
                    >
                        {{ activeFilterCount }}
                        {{ activeFilterCount === 1 ? 'filter' : 'filters' }}
                        active
                    </p>
                    <p
                        v-else-if="activeFilterCount === 0"
                        class="text-sm text-muted"
                    >
                        No filters active
                    </p>
                    <Button
                        v-if="activeFilterCount > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="ml-auto"
                        data-slot="search-clear-filters"
                        @click="clearFilters"
                    >
                        Clear filters
                    </Button>
                </div>
            </div>
        </section>

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
                No movies or TV shows match "{{ query }}"
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
                class="text-2xl font-bold tracking-tight tabular-nums"
            >
                {{
                    isSearching
                        ? resultCount(totalResults ?? 0)
                        : `${shownSortLabel} ${browseNoun}`
                }}
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
