<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import MoviePicker from '@/components/movie/MoviePicker.vue';
import PosterCard from '@/components/movie/PosterCard.vue';
import PosterCardSkeleton from '@/components/movie/PosterCardSkeleton.vue';
import type { PosterCardMovie } from '@/components/movie/PosterCard.vue';
import UserMovieCardMenu from '@/components/movie/UserMovieCardMenu.vue';
import TvShowMarkDialog from '@/components/tv-show/TvShowMarkDialog.vue';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useShortDate } from '@/composables/useShortDate';
import { useTvShowListActions } from '@/composables/useTvShowListActions';
import { search, watched, watchlist } from '@/routes';
import type {
    ListFilter,
    UserMovieList,
    UserMovieListPageProps,
    UserTvShowFacts,
} from '@/types';

const props = defineProps<
    UserMovieListPageProps & { collection: UserMovieList }
>();
const isWatchlist = computed(() => props.collection === 'watchlist');
const title = computed(() => (isWatchlist.value ? 'Watchlist' : 'Watched'));
const fact = computed(() => (isWatchlist.value ? 'watchlisted' : 'watched'));
const defaultSort = computed(() =>
    isWatchlist.value ? 'recently-added' : 'recently-watched',
);
const pageRoute = computed(() => (isWatchlist.value ? watchlist : watched));

const GRID =
    'grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6';
const sorts = computed(() => [
    [
        defaultSort.value,
        isWatchlist.value ? 'Recently added' : 'Recently watched',
    ],
    [
        isWatchlist.value ? 'oldest-added' : 'oldest-watched',
        isWatchlist.value ? 'Oldest added' : 'Oldest watched',
    ],
    ['title', 'Title A–Z'],
    ['newest-release', 'Newest release'],
    ['oldest-release', 'Oldest release'],
]);
const formatShortDate = useShortDate({ localized: true });
const pending = ref(false);
const failure = ref<string | null>(null);
const list = ref<HTMLElement | null>(null);
const container = ref<HTMLElement | null>(null);
const emptyHeading = ref<HTMLElement | null>(null);
const heading = ref<HTMLElement | null>(null);
/** The whole list's size for each kind, from the facts, so it follows every action and includes Removed Movies. */
const counts = computed(() => {
    const movie = Object.values(props.userMovies).filter(
        (facts) => facts[fact.value],
    ).length;
    const tv = Object.values(props.userTvShows).filter(
        (facts) => facts[fact.value],
    ).length;

    return { all: movie + tv, movie, tv };
});
const count = computed(() => counts.value.all);
const FILTERS: { value: ListFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'movies', label: 'Movies' },
    { value: 'tv-shows', label: 'TV Shows' },
];
const filterCount = computed(
    () =>
        ({
            all: counts.value.all,
            movies: counts.value.movie,
            'tv-shows': counts.value.tv,
        })[props.filter],
);

/** A TV Show card's facts; every other card is a Movie. */
function tvFacts(card: PosterCardMovie): UserTvShowFacts | undefined {
    return card.kind === 'tv' ? props.userTvShows[card.tmdbId] : undefined;
}

function cardFacts(card: PosterCardMovie) {
    return card.kind === 'tv' ? tvFacts(card) : props.userMovies[card.tmdbId];
}

function cardKey(card: PosterCardMovie): string {
    return card.key ?? String(card.tmdbId);
}

const cards = computed(() =>
    props.movies.data.filter((card) => cardFacts(card)?.[fact.value]),
);
const { busy: tvBusy, markAsWatched, clearWatched } = useTvShowListActions();
const markingTvShow = ref<PosterCardMovie | null>(null);
const confirmingMark = computed({
    get: () => markingTvShow.value !== null,
    set: (open: boolean) => {
        if (!open) {
            markingTvShow.value = null;
        }
    },
});

/** Clears a TV Show's Watched Episodes; if that fails the card stays, so no focus is left waiting for it to go. */
async function clearTvShow(card: PosterCardMovie): Promise<void> {
    if (!(await clearWatched(card.tmdbId))) {
        menuRemoval = null;
    }
}

async function confirmMark(): Promise<void> {
    const card = markingTvShow.value;

    if (card === null) {
        return;
    }

    menuRemoval = cardKey(card);
    const marked = await markAsWatched(card.tmdbId);

    if (!marked) {
        menuRemoval = null;
    }

    markingTvShow.value = null;
}
const unavailableCards = computed(() =>
    props.unavailableMovies.filter(
        (movie) => props.userMovies[movie.tmdbId]?.[fact.value],
    ),
);
/** Watchlist Movies the picker can choose, whatever the filter: every one but those in the "No longer available" group. */
const pickerCandidates = computed(() =>
    isWatchlist.value ? counts.value.movie - unavailableCards.value.length : 0,
);
let menuRemoval: string | null = null;

/** Keep hidden cards in the merged pages so failure and Undo restore them in their original position. */
watch(
    () => [...unavailableCards.value, ...cards.value].map(cardKey),
    async (current, previous) => {
        const control = document.activeElement as HTMLElement | null;
        const focusedMovie = control?.closest<HTMLElement>('[data-card-key]');
        const tmdbId = menuRemoval ?? focusedMovie?.dataset.cardKey ?? null;
        if (
            tmdbId === null ||
            current.includes(tmdbId) ||
            !previous.includes(tmdbId)
        ) {
            return;
        }
        const slot =
            menuRemoval === null ? control?.dataset.slot : 'movie-menu';
        menuRemoval = null;
        const position = previous.indexOf(tmdbId);
        const next =
            previous.slice(position + 1).find((id) => current.includes(id)) ??
            previous
                .slice(0, position)
                .reverse()
                .find((id) => current.includes(id));
        await nextTick();
        if (next === undefined) {
            (emptyHeading.value ?? heading.value)?.focus();
        } else {
            const card = container.value?.querySelector<HTMLElement>(
                `[data-card-key="${next}"]`,
            );
            (
                card?.querySelector<HTMLElement>(`[data-slot="${slot}"]`) ??
                card?.querySelector<HTMLElement>('[data-slot="movie-menu"]') ??
                heading.value
            )?.focus();
        }
    },
);

/** Shows the list as the sort and filter name it, replacing the history entry and starting its cards afresh. */
function visitList(sort: string, filter: ListFilter): void {
    router.get(
        pageRoute.value.url(),
        {
            ...(sort === defaultSort.value ? {} : { sort }),
            ...(filter === 'all' ? {} : { filter }),
        },
        {
            replace: true,
            preserveState: true,
            reset: ['movies'],
            only: [
                'sort',
                'filter',
                'movies',
                'unavailableMovies',
                'trending',
                'userMovies',
                'userTvShows',
            ],
            onStart: () => {
                pending.value = true;
                failure.value = null;
            },
            onFinish: () => {
                pending.value = false;
            },
            onNetworkError: () => {
                failure.value = `Couldn't load your ${title.value}. Try again.`;
                return false;
            },
        },
    );
}

function changeSort(sort: string): void {
    visitList(sort, props.filter);
}

function changeFilter(filter: ListFilter): void {
    if (filter !== props.filter) {
        visitList(props.sort, filter);
    }
}

const showMoreParams = {
    only: ['userMovies', 'unavailableMovies'],
    onStart: () => {
        failure.value = null;
    },
    onNetworkError: () => {
        failure.value = "Couldn't load more movies. Try again.";
        return false;
    },
    onHttpException: () => {
        failure.value = "Couldn't load more movies. Try again.";
        return false;
    },
};
</script>

<template>
    <div ref="container" class="flex flex-col gap-8 py-8">
        <Head :title="title"><meta name="robots" content="noindex" /></Head>
        <nav
            aria-label="Your movies"
            class="flex gap-1 self-start rounded-lg border border-line bg-surface p-1 text-sm font-medium"
        >
            <Link
                :href="watchlist()"
                :aria-current="isWatchlist ? 'page' : undefined"
                class="rounded-md px-4 py-2"
                :class="
                    isWatchlist
                        ? 'bg-raised text-foreground'
                        : 'text-muted hover:text-foreground'
                "
                >Watchlist</Link
            >
            <Link
                :href="watched()"
                :aria-current="!isWatchlist ? 'page' : undefined"
                class="rounded-md px-4 py-2"
                :class="
                    !isWatchlist
                        ? 'bg-raised text-foreground'
                        : 'text-muted hover:text-foreground'
                "
                >Watched</Link
            >
        </nav>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1
                :id="`${collection}-heading`"
                ref="heading"
                tabindex="-1"
                class="text-3xl font-semibold tracking-tight tabular-nums"
            >
                {{ title }} · {{ count }}
            </h1>
            <div class="flex flex-wrap items-center gap-3">
                <MoviePicker
                    v-if="isWatchlist"
                    :image-base-url="imageBaseUrl"
                    :available="pickerCandidates > 0"
                    @trigger-removed="(emptyHeading ?? heading)?.focus()"
                />
                <label :for="`${collection}-sort`" class="text-sm text-muted"
                    >Sort by</label
                >
                <NativeSelect
                    :id="`${collection}-sort`"
                    :value="sort"
                    touch
                    class="w-auto"
                    @change="
                        changeSort(($event.target as HTMLSelectElement).value)
                    "
                >
                    <option
                        v-for="[value, label] in sorts"
                        :key="value"
                        :value="value"
                    >
                        {{ label }}
                    </option>
                </NativeSelect>
            </div>
        </div>
        <div
            v-if="includesTvShows"
            role="group"
            aria-label="Filter by kind"
            class="flex flex-wrap gap-2"
        >
            <Button
                v-for="chip in FILTERS"
                :key="chip.value"
                type="button"
                size="sm"
                :variant="filter === chip.value ? 'default' : 'outline'"
                :aria-pressed="filter === chip.value"
                :data-filter-chip="chip.value"
                :disabled="pending"
                @click="changeFilter(chip.value)"
            >
                {{ chip.label }}
                <span class="tabular-nums">{{
                    {
                        all: counts.all,
                        movies: counts.movie,
                        'tv-shows': counts.tv,
                    }[chip.value]
                }}</span>
            </Button>
        </div>
        <section v-if="count === 0" class="flex flex-col gap-2">
            <h2
                :id="`${collection}-empty-heading`"
                ref="emptyHeading"
                tabindex="-1"
                class="text-xl font-semibold"
            >
                {{
                    isWatchlist
                        ? 'Your Watchlist is empty'
                        : 'Nothing marked as watched yet'
                }}
            </h2>
            <p class="text-sm text-muted">
                {{
                    isWatchlist
                        ? 'Tap the bookmark on any movie to save it here.'
                        : 'Mark a movie as watched from its page or your Watchlist.'
                }}
            </p>
            <Link
                v-if="!isWatchlist"
                :href="watchlist()"
                class="self-start text-sm underline underline-offset-4"
                >Your Watchlist</Link
            >
        </section>
        <section
            v-if="count > 0 && filterCount === 0"
            data-slot="list-filter-empty"
            class="flex flex-col gap-2"
        >
            <h2 class="text-xl font-semibold">
                No {{ filter === 'tv-shows' ? 'TV shows' : 'movies' }} on your
                {{ title }}
            </h2>
            <Link
                :href="search()"
                class="self-start text-sm underline underline-offset-4"
                >Search for
                {{ filter === 'tv-shows' ? 'a TV show' : 'a movie' }}</Link
            >
        </section>
        <section
            v-if="unavailableCards.length > 0"
            aria-labelledby="unavailable-heading"
            class="flex flex-col gap-4"
        >
            <h2 id="unavailable-heading" class="text-xl font-semibold">
                No longer available
            </h2>
            <p class="text-sm text-muted">
                TMDB has removed these movies, so we can't show their details.
            </p>
            <ul :class="GRID">
                <li v-for="movie in unavailableCards" :key="movie.tmdbId">
                    <PosterCard
                        :image-base-url="imageBaseUrl"
                        :movie="movie"
                        :facts="userMovies[movie.tmdbId]"
                        :source="collection"
                        :show-bookmark="false"
                    >
                        <template #controls>
                            <UserMovieCardMenu
                                :tmdb-id="movie.tmdbId"
                                :title="movie.title"
                                :collection="collection"
                                unavailable
                                @removing="menuRemoval = String(movie.tmdbId)"
                            />
                        </template>
                        <template #date>
                            <span class="text-muted tabular-nums"
                                >{{ isWatchlist ? 'Added' : 'Watched' }}
                                {{ formatShortDate(movie.date) }}</span
                            >
                        </template>
                    </PosterCard>
                </li>
            </ul>
        </section>
        <InfiniteScroll
            v-if="movies.data.length > 0"
            data="movies"
            manual
            :params="showMoreParams"
            :items-element="() => list"
            class="flex flex-col gap-8 transition-opacity"
            :class="{ 'opacity-50': pending }"
            :aria-busy="pending"
        >
            <template #previous="{ loading, fetch, hasMore }">
                <div v-if="hasMore" class="flex justify-center">
                    <Button
                        variant="outline"
                        :disabled="loading || pending"
                        @click="fetch"
                        >Show earlier movies</Button
                    >
                </div>
            </template>
            <ul ref="list" :data-slot="`${collection}-grid`" :class="GRID">
                <li v-for="(movie, index) in cards" :key="cardKey(movie)">
                    <PosterCard
                        :image-base-url="imageBaseUrl"
                        :movie="movie"
                        :facts="
                            movie.kind === 'tv'
                                ? undefined
                                : userMovies[movie.tmdbId]
                        "
                        :source="collection"
                        :eager="index < 6"
                    >
                        <template #controls>
                            <div
                                v-if="tvFacts(movie)"
                                data-slot="tv-progress"
                                aria-hidden="true"
                                class="absolute inset-x-0 bottom-0 h-1 bg-black/50"
                            >
                                <div
                                    class="h-full bg-accent"
                                    :style="{
                                        width: `${tvFacts(movie)!.total === 0 ? 0 : Math.round((tvFacts(movie)!.watchedCount / tvFacts(movie)!.total) * 100)}%`,
                                    }"
                                />
                            </div>
                            <UserMovieCardMenu
                                :tmdb-id="movie.tmdbId"
                                :title="movie.title"
                                :collection="collection"
                                :tv-show="movie.kind === 'tv'"
                                @removing="menuRemoval = cardKey(movie)"
                                @mark-tv-show="markingTvShow = movie"
                                @clear-tv-show="clearTvShow(movie)"
                            />
                        </template>
                        <template #date>
                            <span
                                v-if="tvFacts(movie)"
                                data-slot="tv-state"
                                class="text-muted tabular-nums"
                                >{{ tvFacts(movie)!.state }} ·
                                {{ tvFacts(movie)!.watchedCount }}/{{
                                    tvFacts(movie)!.total
                                }}</span
                            >
                            <span
                                v-if="cardFacts(movie)?.watchedDate"
                                class="text-muted tabular-nums"
                                >{{
                                    isWatchlist || cardFacts(movie)?.watchlisted
                                        ? 'Last watched'
                                        : 'Watched'
                                }}
                                {{
                                    formatShortDate(
                                        cardFacts(movie)!.watchedDate!,
                                    )
                                }}</span
                            >
                        </template>
                    </PosterCard>
                </li>
            </ul>
            <template #next="{ loading, fetch, hasMore }">
                <ul v-if="loading" :class="GRID" aria-hidden="true">
                    <li v-for="index in 6" :key="index">
                        <PosterCardSkeleton />
                    </li>
                </ul>
                <div v-if="hasMore" class="flex justify-center">
                    <Button
                        variant="outline"
                        :disabled="loading || pending"
                        @click="fetch"
                        ><LoaderCircle
                            v-if="loading"
                            class="animate-spin"
                            aria-hidden="true"
                        />Show more</Button
                    >
                </div>
            </template>
        </InfiniteScroll>
        <p v-if="failure" role="alert" class="text-sm text-muted">
            {{ failure }}
        </p>
        <section
            v-if="isWatchlist && count === 0 && trending.length > 0"
            aria-labelledby="watchlist-trending-heading"
            class="flex flex-col gap-6"
        >
            <h2 id="watchlist-trending-heading" class="text-xl font-semibold">
                Trending this week
            </h2>
            <ul :class="GRID">
                <li v-for="(movie, index) in trending" :key="movie.tmdbId">
                    <PosterCard
                        :image-base-url="imageBaseUrl"
                        :movie="movie"
                        :facts="userMovies[movie.tmdbId]"
                        :source="collection"
                        :eager="index < 6"
                    />
                </li>
            </ul>
        </section>
        <TvShowMarkDialog
            v-model:open="confirmingMark"
            :total="markingTvShow ? (tvFacts(markingTvShow)?.total ?? 0) : 0"
            :busy="tvBusy"
            @confirm="confirmMark"
        />
    </div>
</template>
