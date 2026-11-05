<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. The detail page's Watchlist and Watched actions.
 *
 * - Watchlist toggle: `aria-pressed`, label flips "Add to Watchlist" / "On your Watchlist".
 * - Not Watched: "Mark as watched".
 * - Watched, not on Watchlist: "Watched on {date}" + Clear Watched (Watchlist interactions ticket).
 * - Watched AND on Watchlist (a rewatch queued): OPEN QUESTION. Shown here as
 *   Mark as watched (moves the date) plus a quiet "Last watched {date} · Clear" line.
 *
 * `layout`: hero (on a dark image), stack (full-width column), row (inline), bar (mobile sticky bar).
 */
import { Bookmark, BookmarkCheck, Check, CircleCheckBig, X } from '@lucide/vue';
import { useDetailState } from './useDetailState';

withDefaults(defineProps<{ layout?: 'hero' | 'stack' | 'row' | 'bar' }>(), {
    layout: 'row',
});

const { facts, act, formatDate, isOnWatchlist, isWatched } = useDetailState();
</script>

<template>
    <div
        :class="[
            layout === 'stack' && 'flex flex-col gap-2',
            layout === 'row' && 'flex flex-wrap items-center gap-3',
            layout === 'hero' && 'flex flex-wrap items-center gap-3',
            layout === 'bar' && 'flex items-center gap-2',
        ]"
    >
        <button
            type="button"
            class="flex h-11 items-center justify-center gap-2 rounded-lg px-5 text-sm font-semibold whitespace-nowrap transition-colors"
            :class="[
                layout === 'bar' && 'flex-1',
                layout === 'stack' && 'w-full',
                isOnWatchlist
                    ? layout === 'hero'
                        ? 'bg-white text-black'
                        : 'bg-(--rp-raised) text-(--rp-text) ring-1 ring-(--rp-line)'
                    : 'bg-(--rp-accent-fill) text-(--rp-on-accent)',
            ]"
            :aria-pressed="isOnWatchlist"
            :aria-label="
                isOnWatchlist ? 'Remove from Watchlist' : 'Add to Watchlist'
            "
            @click="act(isOnWatchlist ? 'remove' : 'add')"
        >
            <BookmarkCheck v-if="isOnWatchlist" class="size-4" />
            <Bookmark v-else class="size-4" />
            {{ isOnWatchlist ? 'On your Watchlist' : 'Add to Watchlist' }}
        </button>

        <!-- Watched and not on the Watchlist: the settled case. -->
        <div
            v-if="isWatched && !isOnWatchlist"
            class="flex h-11 items-center gap-1 rounded-lg pr-1 pl-3 text-sm"
            :class="[
                layout === 'hero'
                    ? 'bg-black/40 text-white ring-1 ring-white/25 backdrop-blur-sm'
                    : 'bg-(--rp-surface) text-(--rp-text) ring-1 ring-(--rp-line)',
                layout === 'stack' && 'w-full justify-between',
                layout === 'bar' && 'flex-1 justify-between',
            ]"
        >
            <span class="flex items-center gap-2 whitespace-nowrap">
                <CircleCheckBig class="size-4 text-(--rp-accent)" />
                <span
                    >Watched
                    <span class="tabular-nums">{{
                        formatDate(facts.watchedAt!)
                    }}</span></span
                >
            </span>
            <button
                type="button"
                class="ml-1 flex size-9 items-center justify-center rounded-md opacity-70 hover:bg-black/10 hover:opacity-100"
                aria-label="Clear Watched"
                title="Clear Watched"
                @click="act('clearWatched')"
            >
                <X class="size-4" />
            </button>
        </div>

        <button
            v-else
            type="button"
            class="flex h-11 items-center justify-center gap-2 rounded-lg px-5 text-sm font-semibold whitespace-nowrap"
            :class="[
                layout === 'hero'
                    ? 'bg-black/40 text-white ring-1 ring-white/30 backdrop-blur-sm hover:bg-black/55'
                    : 'bg-(--rp-surface) text-(--rp-text) ring-1 ring-(--rp-line) hover:bg-(--rp-raised)',
                layout === 'stack' && 'w-full',
                layout === 'bar' && 'flex-1',
            ]"
            @click="act('markWatched')"
        >
            <Check class="size-4" />
            Mark as watched
        </button>

        <!-- OPEN QUESTION: Watched AND on the Watchlist (rewatch queued). -->
        <p
            v-if="isWatched && isOnWatchlist && layout !== 'bar'"
            class="text-sm"
            :class="[
                layout === 'hero' ? 'text-white/75' : 'text-(--rp-muted)',
                layout === 'stack' && 'text-center',
            ]"
        >
            Last watched
            <span class="tabular-nums">{{ formatDate(facts.watchedAt!) }}</span>
            ·
            <button
                type="button"
                class="underline underline-offset-2 hover:text-(--rp-text)"
                @click="act('clearWatched')"
            >
                Clear
            </button>
        </p>
    </div>
</template>
