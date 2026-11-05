<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Variant A: "Top bar + poster shelves".
 *
 * Desktop: a slim top bar (Discover, Search, Watchlist, Watched; Profile as the
 * avatar). Discover is horizontal poster shelves. The card is poster-first:
 * the Watchlist action floats on the poster, one External rating underneath
 * (IMDb, falling back to TMDB).
 */
import MobileBottomNav from './MobileBottomNav.vue';
import MovieCardA from './MovieCardA.vue';
import type { MockMovie } from './mockMovies';
import { allMovies, sections } from './mockMovies';
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

function savedMovies(): MockMovie[] {
    return allMovies.filter((movie) => state.watchlist.has(movie.id));
}
</script>

<template>
    <div class="min-h-svh bg-(--rp-bg) text-(--rp-text)">
        <header
            class="sticky top-0 z-30 border-b border-(--rp-line) bg-(--rp-bg)/85 backdrop-blur-md"
        >
            <div
                class="mx-auto flex h-14 max-w-7xl items-center gap-8 px-4 md:h-16 md:px-8"
            >
                <button type="button" @click="go('discover')">
                    <Wordmark />
                </button>
                <nav aria-label="Primary" class="hidden h-full md:flex">
                    <button
                        v-for="item in topNav"
                        :key="item.screen"
                        type="button"
                        class="relative flex h-full items-center px-3 text-sm font-medium"
                        :class="
                            state.screen === item.screen
                                ? 'text-(--rp-text) after:absolute after:inset-x-3 after:-bottom-px after:h-0.5 after:rounded-full after:bg-(--rp-accent)'
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
                    class="ml-auto hidden size-9 items-center justify-center rounded-full bg-(--rp-raised) text-sm font-semibold ring-1 ring-(--rp-line) md:flex"
                    :class="
                        state.screen === 'profile'
                            ? 'ring-2 ring-(--rp-accent)'
                            : ''
                    "
                    aria-label="Profile"
                    @click="go('profile')"
                >
                    JD
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 md:px-8">
            <template v-if="state.screen === 'discover'">
                <section
                    v-for="section in sections"
                    :key="section.key"
                    class="mt-8 md:mt-10"
                >
                    <div class="mb-3 flex items-baseline justify-between">
                        <h2
                            class="text-lg font-semibold tracking-tight md:text-xl"
                        >
                            {{ section.title }}
                        </h2>
                        <a
                            href="#"
                            class="text-sm font-medium text-(--rp-accent) hover:underline"
                            >See all</a
                        >
                    </div>
                    <ul
                        class="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-4 overflow-x-auto px-4 pb-2 md:-mx-8 md:scroll-px-8 md:px-8 [&::-webkit-scrollbar]:hidden"
                    >
                        <template v-if="loading">
                            <li
                                v-for="n in 8"
                                :key="n"
                                class="w-[8.75rem] shrink-0 sm:w-40 lg:w-44"
                            >
                                <SkeletonBlock
                                    class="aspect-[2/3] w-full rounded-lg"
                                />
                                <SkeletonBlock
                                    class="mt-3 h-3.5 w-4/5 rounded"
                                />
                                <SkeletonBlock class="mt-2 h-3 w-1/2 rounded" />
                            </li>
                        </template>
                        <template v-else>
                            <li
                                v-for="movie in section.movies"
                                :key="movie.id"
                                class="w-[8.75rem] shrink-0 snap-start sm:w-40 lg:w-44"
                            >
                                <MovieCardA
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
                    <h1
                        class="text-2xl font-semibold tracking-tight md:text-3xl"
                    >
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
                <ul
                    v-else
                    class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
                >
                    <li v-for="movie in savedMovies()" :key="movie.id">
                        <MovieCardA
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
