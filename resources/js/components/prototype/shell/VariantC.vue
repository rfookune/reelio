<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Variant C: "Backdrop hero + list rows".
 *
 * The top bar floats over a full-bleed backdrop of the top trending movie; its
 * light spills down into the page. Sections below are list rows (ranked only
 * for Trending, which is an ordered list), not poster grids.
 */
import { Bookmark, BookmarkCheck, Play } from '@lucide/vue';
import { computed } from 'vue';
import ExternalRating from './ExternalRating.vue';
import MobileBottomNav from './MobileBottomNav.vue';
import MovieCardC from './MovieCardC.vue';
import type { MockMovie } from './mockMovies';
import { allMovies, backdropUrl, featured, sections } from './mockMovies';
import ScreenStub from './ScreenStub.vue';
import { desktopNav } from './shared';
import SkeletonBlock from './SkeletonBlock.vue';
import TmdbAttribution from './TmdbAttribution.vue';
import { usePrototypeState } from './usePrototypeState';
import WatchlistTabs from './WatchlistTabs.vue';
import Wordmark from './Wordmark.vue';

defineProps<{ loading: boolean }>();

const { state, isOnWatchlist, toggleWatchlist, go } = usePrototypeState();

const topNav = desktopNav.filter((item) => item.screen !== 'profile');
const heroImage = computed(() => backdropUrl(featured));
const listSections = sections.filter((section) => section.key !== 'trending');
const trending = sections.find((section) => section.key === 'trending')!;

function savedMovies(): MockMovie[] {
    return allMovies.filter((movie) => state.watchlist.has(movie.id));
}
</script>

<template>
    <div class="relative min-h-svh bg-(--rp-bg) text-(--rp-text)">
        <!-- Backdrop glow: the hero's light bleeding into the page. -->
        <div
            v-if="state.screen === 'discover' && heroImage"
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 h-[44rem] overflow-hidden"
        >
            <img
                :src="heroImage"
                alt=""
                class="size-full scale-110 object-cover opacity-40 blur-3xl"
            />
            <div
                class="absolute inset-0 bg-gradient-to-b from-transparent via-(--rp-bg)/70 to-(--rp-bg)"
            />
        </div>

        <header class="relative z-30">
            <div
                class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 md:px-8"
            >
                <button type="button" @click="go('discover')">
                    <Wordmark />
                </button>
                <nav
                    aria-label="Primary"
                    class="mx-auto hidden rounded-full bg-(--rp-surface)/70 p-1 ring-1 ring-(--rp-line) backdrop-blur-md md:flex"
                >
                    <button
                        v-for="item in topNav"
                        :key="item.screen"
                        type="button"
                        class="rounded-full px-4 py-1.5 text-sm font-medium"
                        :class="
                            state.screen === item.screen
                                ? 'bg-(--rp-text) text-(--rp-bg)'
                                : 'text-(--rp-muted) hover:text-(--rp-text)'
                        "
                        :aria-current="
                            state.screen === item.screen ? 'page' : undefined
                        "
                        @click="go(item.screen)"
                    >
                        {{ item.label }}
                    </button>
                </nav>
                <button
                    type="button"
                    class="ml-auto hidden size-9 items-center justify-center rounded-full bg-(--rp-raised) text-sm font-semibold ring-1 ring-(--rp-line) md:ml-0 md:flex"
                    aria-label="Profile"
                    @click="go('profile')"
                >
                    JD
                </button>
            </div>
        </header>

        <main class="relative mx-auto max-w-7xl px-4 md:px-8">
            <template v-if="state.screen === 'discover'">
                <section
                    class="relative mt-2 overflow-hidden rounded-2xl ring-1 ring-(--rp-line)"
                    aria-labelledby="hero-title"
                >
                    <SkeletonBlock
                        v-if="loading"
                        class="aspect-[4/5] w-full sm:aspect-[16/9] lg:aspect-[21/9]"
                    />
                    <template v-else>
                        <img
                            v-if="heroImage"
                            :src="heroImage"
                            :alt="`Still from ${featured.title}`"
                            class="aspect-[4/5] w-full object-cover sm:aspect-[16/9] lg:aspect-[21/9]"
                        />
                        <div
                            class="absolute inset-0 flex flex-col justify-end bg-gradient-to-t from-black/90 via-black/40 to-transparent p-5 text-white sm:p-8 lg:bg-gradient-to-r lg:from-black/85 lg:via-black/40 lg:p-12"
                        >
                            <p class="text-sm font-medium text-white/80">
                                Trending this week
                            </p>
                            <h1
                                id="hero-title"
                                class="mt-2 max-w-2xl text-4xl leading-none font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl"
                            >
                                {{ featured.title }}
                            </h1>
                            <div
                                class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-white/80"
                            >
                                <span class="tabular-nums">{{
                                    featured.year
                                }}</span>
                                <span>{{ featured.runtime }}</span>
                                <span>{{ featured.genres.join(', ') }}</span>
                                <span
                                    class="flex gap-3 rounded-md bg-black/40 px-2 py-1 backdrop-blur-sm [--rp-imdb:#f5c518] [--rp-muted:rgb(255_255_255/0.8)] [--rp-text:white] [--rp-tmdb:#5fd4f5]"
                                >
                                    <ExternalRating
                                        source="IMDb"
                                        :value="featured.imdbRating!"
                                    />
                                    <ExternalRating
                                        source="TMDB"
                                        :value="featured.tmdbRating!"
                                    />
                                </span>
                            </div>
                            <p
                                class="mt-4 hidden max-w-xl text-base leading-relaxed text-white/85 sm:block"
                            >
                                {{ featured.overview }}
                            </p>
                            <div class="mt-6 flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    class="flex h-11 items-center gap-2 rounded-lg px-5 text-sm font-semibold"
                                    :class="
                                        isOnWatchlist(featured)
                                            ? 'bg-white text-black'
                                            : 'bg-(--rp-accent-fill) text-(--rp-on-accent)'
                                    "
                                    :aria-pressed="isOnWatchlist(featured)"
                                    @click="toggleWatchlist(featured)"
                                >
                                    <BookmarkCheck
                                        v-if="isOnWatchlist(featured)"
                                        class="size-4"
                                    />
                                    <Bookmark v-else class="size-4" />
                                    {{
                                        isOnWatchlist(featured)
                                            ? 'On your Watchlist'
                                            : 'Add to Watchlist'
                                    }}
                                </button>
                                <a
                                    href="#"
                                    class="flex h-11 items-center gap-2 rounded-lg bg-white/15 px-5 text-sm font-semibold text-white backdrop-blur-sm hover:bg-white/25"
                                >
                                    <Play class="size-4" />
                                    Watch trailer
                                </a>
                            </div>
                        </div>
                    </template>
                </section>

                <section class="mt-12">
                    <h2 class="mb-3 text-xl font-semibold tracking-tight">
                        {{ trending.title }}
                    </h2>
                    <ol class="grid gap-x-8 gap-y-1 md:grid-cols-2">
                        <template v-if="loading">
                            <li
                                v-for="n in 8"
                                :key="n"
                                class="flex items-center gap-4 p-2"
                            >
                                <SkeletonBlock
                                    class="aspect-[2/3] w-14 rounded-md sm:w-16"
                                />
                                <div class="flex-1">
                                    <SkeletonBlock class="h-4 w-2/3 rounded" />
                                    <SkeletonBlock
                                        class="mt-2 h-3 w-1/3 rounded"
                                    />
                                    <SkeletonBlock
                                        class="mt-2 h-3 w-1/2 rounded"
                                    />
                                </div>
                            </li>
                        </template>
                        <template v-else>
                            <li
                                v-for="(movie, index) in trending.movies"
                                :key="movie.id"
                            >
                                <MovieCardC
                                    :movie="movie"
                                    :rank="index + 1"
                                    :on-watchlist="isOnWatchlist(movie)"
                                    @toggle="toggleWatchlist(movie)"
                                />
                            </li>
                        </template>
                    </ol>
                </section>

                <section
                    v-for="section in listSections"
                    :key="section.key"
                    class="mt-12"
                >
                    <div class="mb-3 flex items-baseline justify-between">
                        <h2 class="text-xl font-semibold tracking-tight">
                            {{ section.title }}
                        </h2>
                        <a
                            href="#"
                            class="text-sm font-medium text-(--rp-accent) hover:underline"
                            >See all</a
                        >
                    </div>
                    <ul
                        class="grid gap-x-8 gap-y-1 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <template v-if="loading">
                            <li
                                v-for="n in 6"
                                :key="n"
                                class="flex items-center gap-4 p-2"
                            >
                                <SkeletonBlock
                                    class="aspect-[2/3] w-14 rounded-md sm:w-16"
                                />
                                <div class="flex-1">
                                    <SkeletonBlock class="h-4 w-2/3 rounded" />
                                    <SkeletonBlock
                                        class="mt-2 h-3 w-1/3 rounded"
                                    />
                                </div>
                            </li>
                        </template>
                        <template v-else>
                            <li
                                v-for="movie in section.movies.slice(0, 6)"
                                :key="movie.id"
                            >
                                <MovieCardC
                                    :movie="movie"
                                    :on-watchlist="isOnWatchlist(movie)"
                                    @toggle="toggleWatchlist(movie)"
                                />
                            </li>
                        </template>
                    </ul>
                </section>
            </template>

            <template v-else-if="state.screen === 'watchlist'">
                <div
                    class="mt-8 flex flex-wrap items-center justify-between gap-4"
                >
                    <h1 class="text-3xl font-semibold tracking-tight">
                        Your Watchlist
                    </h1>
                    <WatchlistTabs />
                </div>
                <p
                    v-if="savedMovies().length === 0"
                    class="mt-16 text-center text-(--rp-muted)"
                >
                    Your Watchlist is empty. Save movies from Discover and
                    they’ll show up here.
                </p>
                <ul v-else class="mt-6 grid gap-x-8 gap-y-1 md:grid-cols-2">
                    <li v-for="movie in savedMovies()" :key="movie.id">
                        <MovieCardC
                            :movie="movie"
                            :on-watchlist="isOnWatchlist(movie)"
                            @toggle="toggleWatchlist(movie)"
                        />
                    </li>
                </ul>
            </template>

            <template v-else>
                <div
                    v-if="state.screen === 'watched'"
                    class="mt-8 flex justify-center"
                >
                    <WatchlistTabs />
                </div>
                <ScreenStub />
            </template>
        </main>

        <TmdbAttribution />
        <MobileBottomNav />
    </div>
</template>
