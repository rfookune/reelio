<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import MoviePicker from '@/components/movie/MoviePicker.vue';
import PosterCard from '@/components/movie/PosterCard.vue';
import PosterCardSkeleton from '@/components/movie/PosterCardSkeleton.vue';
import UserMovieCardMenu from '@/components/movie/UserMovieCardMenu.vue';
import { Button } from '@/components/ui/button';
import { useShortDate } from '@/composables/useShortDate';
import { watched, watchlist } from '@/routes';
import type { UserMovieList, UserMovieListPageProps } from '@/types';

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
const count = computed(
    () =>
        Object.values(props.userMovies).filter((facts) => facts[fact.value])
            .length,
);
const cards = computed(() =>
    props.movies.data.filter(
        (movie) => props.userMovies[movie.tmdbId]?.[fact.value],
    ),
);
const unavailableCards = computed(() =>
    props.unavailableMovies.filter(
        (movie) => props.userMovies[movie.tmdbId]?.[fact.value],
    ),
);
/** Watchlist Movies the picker can choose: every one but those in the "No longer available" group. */
const pickerCandidates = computed(() =>
    isWatchlist.value ? count.value - unavailableCards.value.length : 0,
);
let menuRemoval: number | null = null;

/** Keep hidden cards in the merged pages so failure and Undo restore them in their original position. */
watch(
    () =>
        [...unavailableCards.value, ...cards.value].map(
            (movie) => movie.tmdbId,
        ),
    async (current, previous) => {
        const control = document.activeElement as HTMLElement | null;
        const focusedMovie = control?.closest<HTMLElement>('[data-tmdb-id]');
        const tmdbId =
            menuRemoval ??
            (focusedMovie ? Number(focusedMovie.dataset.tmdbId) : null);
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
                `[data-tmdb-id="${next}"]`,
            );
            (
                card?.querySelector<HTMLElement>(`[data-slot="${slot}"]`) ??
                card?.querySelector<HTMLElement>('[data-slot="movie-menu"]') ??
                heading.value
            )?.focus();
        }
    },
);

function changeSort(sort: string): void {
    router.get(
        pageRoute.value.url(),
        sort === defaultSort.value ? {} : { sort },
        {
            replace: true,
            preserveState: true,
            reset: ['movies'],
            only: [
                'sort',
                'movies',
                'unavailableMovies',
                'trending',
                'userMovies',
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
                <select
                    :id="`${collection}-sort`"
                    :value="sort"
                    class="h-11 rounded-lg border border-control bg-surface px-3 text-sm"
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
                </select>
            </div>
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
                                @removing="menuRemoval = movie.tmdbId"
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
                <li v-for="(movie, index) in cards" :key="movie.tmdbId">
                    <PosterCard
                        :image-base-url="imageBaseUrl"
                        :movie="movie"
                        :facts="userMovies[movie.tmdbId]"
                        :source="collection"
                        :eager="index < 6"
                    >
                        <template #controls
                            ><UserMovieCardMenu
                                :tmdb-id="movie.tmdbId"
                                :title="movie.title"
                                :collection="collection"
                                @removing="menuRemoval = movie.tmdbId"
                        /></template>
                        <template #date
                            ><span
                                v-if="userMovies[movie.tmdbId]?.watchedDate"
                                class="text-muted tabular-nums"
                                >{{
                                    isWatchlist ||
                                    userMovies[movie.tmdbId]?.watchlisted
                                        ? 'Last watched'
                                        : 'Watched'
                                }}
                                {{
                                    formatShortDate(
                                        userMovies[movie.tmdbId].watchedDate!,
                                    )
                                }}</span
                            ></template
                        >
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
    </div>
</template>
