<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import PosterCard from '@/components/movie/PosterCard.vue';
import type { PosterCardMovie } from '@/components/movie/PosterCard.vue';
import PosterCardSkeleton from '@/components/movie/PosterCardSkeleton.vue';
import WatchlistCardMenu from '@/components/movie/WatchlistCardMenu.vue';
import { Button } from '@/components/ui/button';
import { useShortDate } from '@/composables/useShortDate';
import { watchlist } from '@/routes';
import type { UserMovies } from '@/types';

const props = defineProps<{
    imageBaseUrl: string;
    sort: string;
    movies: { data: PosterCardMovie[] };
    trending: PosterCardMovie[];
    userMovies: UserMovies;
}>();

const GRID =
    'grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6';
const sorts = [
    ['recently-added', 'Recently added'],
    ['oldest-added', 'Oldest added'],
    ['title', 'Title A–Z'],
    ['newest-release', 'Newest release'],
    ['oldest-release', 'Oldest release'],
];
const formatShortDate = useShortDate();
const pending = ref(false);
const failure = ref<string | null>(null);
const list = ref<HTMLElement | null>(null);
const emptyHeading = ref<HTMLElement | null>(null);
const heading = ref<HTMLElement | null>(null);
const count = computed(
    () =>
        Object.values(props.userMovies).filter((facts) => facts.watchlisted)
            .length,
);
const cards = computed(() =>
    props.movies.data.filter(
        (movie) => props.userMovies[movie.tmdbId]?.watchlisted,
    ),
);
let menuRemoval: number | null = null;

/** Keep hidden cards in the merged pages so failure and Undo restore them in their original position. */
watch(
    () => cards.value.map((movie) => movie.tmdbId),
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
            list.value
                ?.querySelector<HTMLElement>(
                    `[data-tmdb-id="${next}"] [data-slot="${slot}"]`,
                )
                ?.focus();
        }
    },
);

function changeSort(sort: string): void {
    router.get(watchlist.url(), sort === 'recently-added' ? {} : { sort }, {
        replace: true,
        preserveState: true,
        reset: ['movies'],
        only: ['sort', 'movies', 'trending', 'userMovies'],
        onStart: () => {
            pending.value = true;
            failure.value = null;
        },
        onFinish: () => {
            pending.value = false;
        },
        onNetworkError: () => {
            failure.value = "Couldn't load your Watchlist. Try again.";
            return false;
        },
    });
}

const showMoreParams = {
    only: ['userMovies'],
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
    <div class="flex flex-col gap-8 py-8">
        <Head title="Watchlist"><meta name="robots" content="noindex" /></Head>
        <nav
            aria-label="Your movies"
            class="flex gap-1 self-start rounded-lg border border-line bg-surface p-1 text-sm font-medium"
        >
            <Link
                :href="watchlist()"
                aria-current="page"
                class="rounded-md bg-raised px-4 py-2 text-foreground"
                >Watchlist</Link
            >
            <Link
                href="/watched"
                class="rounded-md px-4 py-2 text-muted hover:text-foreground"
                >Watched</Link
            >
        </nav>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1
                id="watchlist-heading"
                ref="heading"
                tabindex="-1"
                class="text-3xl font-semibold tracking-tight tabular-nums"
            >
                Watchlist · {{ count }}
            </h1>
            <div class="flex items-center gap-3">
                <label for="watchlist-sort" class="text-sm text-muted"
                    >Sort by</label
                >
                <select
                    id="watchlist-sort"
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
                id="watchlist-empty-heading"
                ref="emptyHeading"
                tabindex="-1"
                class="text-xl font-semibold"
            >
                Your Watchlist is empty
            </h2>
            <p class="text-sm text-muted">
                Tap the bookmark on any movie to save it here.
            </p>
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
            <ul ref="list" data-slot="watchlist-grid" :class="GRID">
                <li v-for="(movie, index) in cards" :key="movie.tmdbId">
                    <PosterCard
                        :image-base-url="imageBaseUrl"
                        :movie="movie"
                        :facts="userMovies[movie.tmdbId]"
                        source="watchlist"
                        :eager="index < 6"
                    >
                        <template #controls
                            ><WatchlistCardMenu
                                :tmdb-id="movie.tmdbId"
                                :title="movie.title"
                                @removing="menuRemoval = movie.tmdbId"
                        /></template>
                        <template #date
                            ><span
                                v-if="userMovies[movie.tmdbId]?.watchedDate"
                                class="text-muted tabular-nums"
                                >Last watched
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
            v-if="count === 0 && trending.length > 0"
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
                        source="watchlist"
                        :eager="index < 6"
                    />
                </li>
            </ul>
        </section>
    </div>
</template>
