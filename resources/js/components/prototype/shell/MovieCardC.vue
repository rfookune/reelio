<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Card C: a list row. Small poster, title, year and
 * runtime, both External ratings inline, and the Watchlist action at the end
 * of the row. Doubles as the Watchlist page's "list" layout (FR-008).
 */
import { Bookmark, BookmarkCheck } from '@lucide/vue';
import ExternalRating from './ExternalRating.vue';
import type { MockMovie } from './mockMovies';
import PosterImage from './PosterImage.vue';

defineProps<{ movie: MockMovie; onWatchlist: boolean; rank?: number }>();
defineEmits<{ toggle: [] }>();
</script>

<template>
    <article
        class="group flex items-center gap-4 rounded-xl p-2 hover:bg-(--rp-surface)"
    >
        <span
            v-if="rank"
            class="w-6 shrink-0 text-right text-xl font-semibold text-(--rp-muted) tabular-nums"
            :aria-label="`Number ${rank}`"
            >{{ rank }}</span
        >
        <a
            href="#"
            tabindex="-1"
            aria-hidden="true"
            class="w-14 shrink-0 overflow-hidden rounded-md ring-1 ring-(--rp-line) sm:w-16"
        >
            <PosterImage :movie="movie" size="w185" compact />
        </a>
        <div class="min-w-0 flex-1">
            <a
                href="#"
                class="line-clamp-1 font-medium hover:underline"
                :title="movie.title"
                >{{ movie.title }}</a
            >
            <p class="mt-0.5 text-sm text-(--rp-muted) tabular-nums">
                {{ movie.year ?? 'TBA' }}
                <span class="mx-1" aria-hidden="true">/</span>
                {{ movie.runtime }}
            </p>
            <div class="mt-1.5 flex flex-wrap gap-3">
                <ExternalRating
                    v-if="movie.imdbRating !== null"
                    source="IMDb"
                    :value="movie.imdbRating"
                />
                <ExternalRating
                    v-if="movie.tmdbRating !== null"
                    source="TMDB"
                    :value="movie.tmdbRating"
                />
                <span
                    v-if="
                        movie.imdbRating === null && movie.tmdbRating === null
                    "
                    class="text-xs text-(--rp-muted)"
                    >Not yet rated</span
                >
            </div>
        </div>
        <button
            type="button"
            class="flex size-10 shrink-0 items-center justify-center rounded-full"
            :class="
                onWatchlist
                    ? 'text-(--rp-accent)'
                    : 'text-(--rp-muted) hover:bg-(--rp-raised) hover:text-(--rp-text)'
            "
            :aria-pressed="onWatchlist"
            :aria-label="
                onWatchlist
                    ? `Remove ${movie.title} from Watchlist`
                    : `Add ${movie.title} to Watchlist`
            "
            @click="$emit('toggle')"
        >
            <BookmarkCheck
                v-if="onWatchlist"
                class="size-5 fill-(--rp-accent)/20"
            />
            <Bookmark v-else class="size-5" />
        </button>
    </article>
</template>
