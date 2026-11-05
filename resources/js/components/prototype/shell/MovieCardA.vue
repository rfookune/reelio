<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Card A: poster-first. Poster and title share one link
 * target (one tab stop); the Watchlist toggle floats on the poster so it works
 * on touch without hover. One External rating: IMDb, falling back to TMDB.
 */
import { Bookmark, BookmarkCheck } from '@lucide/vue';
import ExternalRating from './ExternalRating.vue';
import type { MockMovie } from './mockMovies';
import PosterImage from './PosterImage.vue';

defineProps<{ movie: MockMovie; onWatchlist: boolean }>();
defineEmits<{ toggle: [] }>();
</script>

<template>
    <article class="group">
        <div
            class="relative overflow-hidden rounded-lg ring-1 ring-(--rp-line) group-hover:ring-(--rp-muted)"
        >
            <a href="#" tabindex="-1" aria-hidden="true">
                <PosterImage :movie="movie" />
            </a>
            <button
                type="button"
                class="absolute top-2 right-2 flex size-9 items-center justify-center rounded-full backdrop-blur-sm"
                :class="
                    onWatchlist
                        ? 'bg-(--rp-accent-fill) text-(--rp-on-accent)'
                        : 'bg-black/55 text-white hover:bg-black/75'
                "
                :aria-pressed="onWatchlist"
                :aria-label="
                    onWatchlist
                        ? `Remove ${movie.title} from Watchlist`
                        : `Add ${movie.title} to Watchlist`
                "
                @click="$emit('toggle')"
            >
                <BookmarkCheck v-if="onWatchlist" class="size-4.5" />
                <Bookmark v-else class="size-4.5" />
            </button>
        </div>
        <a
            href="#"
            class="mt-2.5 line-clamp-2 text-sm leading-snug font-medium hover:underline"
            :title="movie.title"
            >{{ movie.title }}</a
        >
        <div class="mt-1 flex items-center gap-2.5">
            <span class="text-xs text-(--rp-muted) tabular-nums">{{
                movie.year ?? 'TBA'
            }}</span>
            <ExternalRating
                v-if="movie.imdbRating !== null"
                source="IMDb"
                :value="movie.imdbRating"
            />
            <ExternalRating
                v-else-if="movie.tmdbRating !== null"
                source="TMDB"
                :value="movie.tmdbRating"
            />
            <span v-else class="text-xs text-(--rp-muted)">Not yet rated</span>
        </div>
    </article>
</template>
