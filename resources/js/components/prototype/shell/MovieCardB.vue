<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Card B: title and year sit on the poster; both External
 * ratings as chips underneath; the Watchlist action is a labelled full-width
 * button rather than an icon.
 */
import { Check, Plus } from '@lucide/vue';
import ExternalRating from './ExternalRating.vue';
import type { MockMovie } from './mockMovies';
import PosterImage from './PosterImage.vue';

defineProps<{ movie: MockMovie; onWatchlist: boolean }>();
defineEmits<{ toggle: [] }>();
</script>

<template>
    <article class="flex flex-col">
        <a
            href="#"
            class="relative block overflow-hidden rounded-xl ring-1 ring-(--rp-line) hover:ring-(--rp-muted)"
        >
            <PosterImage :movie="movie" />
            <div
                v-if="movie.posterPath"
                class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/60 to-transparent px-3 pt-10 pb-3 text-white"
            >
                <h3 class="line-clamp-2 text-sm leading-snug font-semibold">
                    {{ movie.title }}
                </h3>
                <p class="mt-0.5 text-xs text-white/75 tabular-nums">
                    {{ movie.year ?? 'TBA' }}
                </p>
            </div>
            <p
                v-else
                class="absolute inset-x-0 bottom-3 text-center text-xs text-(--rp-muted) tabular-nums"
            >
                {{ movie.year ?? 'TBA' }}
            </p>
        </a>
        <div class="mt-2 flex min-h-6 flex-wrap gap-1.5">
            <ExternalRating
                v-if="movie.imdbRating !== null"
                source="IMDb"
                :value="movie.imdbRating"
                look="chip"
            />
            <ExternalRating
                v-if="movie.tmdbRating !== null"
                source="TMDB"
                :value="movie.tmdbRating"
                look="chip"
            />
            <span
                v-if="movie.imdbRating === null && movie.tmdbRating === null"
                class="py-0.5 text-xs text-(--rp-muted)"
                >Not yet rated</span
            >
        </div>
        <button
            type="button"
            class="mt-2 flex h-9 w-full items-center justify-center gap-1.5 rounded-lg text-sm font-medium ring-1"
            :class="
                onWatchlist
                    ? 'bg-(--rp-raised) text-(--rp-text) ring-(--rp-line)'
                    : 'text-(--rp-accent) ring-(--rp-accent)/50 hover:bg-(--rp-accent)/10'
            "
            :aria-pressed="onWatchlist"
            :aria-label="
                onWatchlist
                    ? `Remove ${movie.title} from Watchlist`
                    : `Add ${movie.title} to Watchlist`
            "
            @click="$emit('toggle')"
        >
            <Check v-if="onWatchlist" class="size-4" />
            <Plus v-else class="size-4" />
            {{ onWatchlist ? 'On Watchlist' : 'Watchlist' }}
        </button>
    </article>
</template>
