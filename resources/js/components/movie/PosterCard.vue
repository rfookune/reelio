<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ExternalRatings from '@/components/ExternalRatings.vue';
import type { ExternalRating } from '@/components/ExternalRatings.vue';
import BookmarkToggle from '@/components/movie/BookmarkToggle.vue';
import TmdbImage from '@/components/TmdbImage.vue';
import type { UserMovieActionSource, UserMovieFacts } from '@/types';

/** One Movie on a poster card, linked to its page (the entry route when it has no row yet). */
export type PosterCardMovie = {
    tmdbId: number;
    title: string | null;
    year: number | null;
    posterPath: string | null;
    /** The ratings in display order; the card shows the first. */
    ratings: ExternalRating[];
    href: string;
};

/**
 * The poster card: the poster (or missing-poster tile) with the Watchlist toggle floating on it, the title clamped
 * to 2 lines, the year when known and one External rating. Poster and title share one link target, so the card is
 * one tab stop plus the toggle.
 */
const props = withDefaults(
    defineProps<{
        imageBaseUrl: string;
        movie: PosterCardMovie;
        facts: UserMovieFacts | undefined;
        source: UserMovieActionSource;
        /** Load the poster at once, for cards above the fold. */
        eager?: boolean;
    }>(),
    { eager: false },
);

const title = computed(() => props.movie.title ?? 'Movie');
</script>

<template>
    <article data-slot="poster-card" :data-tmdb-id="movie.tmdbId">
        <div class="relative overflow-hidden rounded-lg ring-1 ring-line">
            <Link :href="movie.href" tabindex="-1" aria-hidden="true">
                <TmdbImage
                    :base-url="imageBaseUrl"
                    :path="movie.posterPath"
                    alt=""
                    :placeholder="title"
                    :widths="[185, 342, 500]"
                    sizes="(min-width: 1280px) 16vw, (min-width: 768px) 25vw, 50vw"
                    :loading="eager ? 'eager' : 'lazy'"
                />
            </Link>
            <div class="absolute top-1.5 right-1.5">
                <BookmarkToggle
                    :tmdb-id="movie.tmdbId"
                    :title="title"
                    :facts="facts"
                    :source="source"
                    variant="floating"
                />
            </div>
            <slot name="controls" />
        </div>
        <Link
            :href="movie.href"
            class="mt-2.5 line-clamp-2 text-sm leading-snug font-medium underline-offset-4 hover:underline"
        >
            {{ title }}
        </Link>
        <div class="mt-1 flex flex-col gap-1 text-xs">
            <span v-if="movie.year !== null" class="text-muted tabular-nums">
                {{ movie.year }}
            </span>
            <ExternalRatings :ratings="movie.ratings" variant="single" />
            <slot name="date" />
        </div>
    </article>
</template>
