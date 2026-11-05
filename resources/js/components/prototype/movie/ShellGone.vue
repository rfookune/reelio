<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. The 410 page for a dropped Movie (a "shell" row).
 *
 * Every TMDB-derived field has been cleared (Movie identity ticket), so there is no title,
 * poster or year to show. OPEN QUESTION: what a signed-in user with a fact on this Movie
 * sees. Shown here: what they have (Watchlist since / Watched on) and the two clean-up actions,
 * so they can tidy their lists from the URL they followed.
 */
import { CircleCheckBig, Bookmark, FileQuestion } from '@lucide/vue';
import DetailShell from './DetailShell.vue';
import { useDetailState } from './useDetailState';

const { settings, facts, act, formatDate, isOnWatchlist, isWatched } =
    useDetailState();
</script>

<template>
    <DetailShell>
        <div
            class="mx-auto flex max-w-xl flex-col items-center px-4 py-20 text-center md:py-28"
        >
            <span
                class="flex size-14 items-center justify-center rounded-full bg-(--rp-raised) text-(--rp-muted)"
            >
                <FileQuestion class="size-7" stroke-width="1.5" />
            </span>
            <h1 class="mt-6 text-2xl font-semibold tracking-tight">
                This movie is no longer available
            </h1>
            <p class="mt-3 text-(--rp-muted)">
                Our data source, TMDB, has removed this movie, so we can't show
                its details any more.
            </p>

            <div
                v-if="settings.auth === 'user' && (isOnWatchlist || isWatched)"
                class="mt-8 w-full rounded-2xl bg-(--rp-surface) p-5 text-left ring-1 ring-(--rp-line)"
            >
                <p class="text-sm font-semibold">It's still in your lists</p>
                <ul class="mt-3 space-y-3 text-sm">
                    <li
                        v-if="isOnWatchlist"
                        class="flex items-center justify-between gap-3"
                    >
                        <span class="flex items-center gap-2">
                            <Bookmark class="size-4 text-(--rp-accent)" />
                            On your Watchlist
                        </span>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 font-medium ring-1 ring-(--rp-line) hover:bg-(--rp-raised)"
                            @click="act('remove')"
                        >
                            Remove
                        </button>
                    </li>
                    <li
                        v-if="isWatched"
                        class="flex items-center justify-between gap-3"
                    >
                        <span class="flex items-center gap-2">
                            <CircleCheckBig class="size-4 text-(--rp-accent)" />
                            Watched
                            <span class="tabular-nums">{{
                                formatDate(facts.watchedAt!)
                            }}</span>
                        </span>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 font-medium ring-1 ring-(--rp-line) hover:bg-(--rp-raised)"
                            @click="act('clearWatched')"
                        >
                            Clear
                        </button>
                    </li>
                </ul>
            </div>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a
                    href="#"
                    class="flex h-11 items-center rounded-lg bg-(--rp-accent-fill) px-5 text-sm font-semibold text-(--rp-on-accent)"
                    >Go to Discover</a
                >
                <a
                    v-if="settings.auth === 'user'"
                    href="#"
                    class="flex h-11 items-center rounded-lg px-5 text-sm font-semibold ring-1 ring-(--rp-line) hover:bg-(--rp-raised)"
                    >Your Watchlist</a
                >
            </div>
            <p class="mt-10 text-xs text-(--rp-muted)">
                HTTP 410 Gone · noindex
            </p>
        </div>
    </DetailShell>
</template>
