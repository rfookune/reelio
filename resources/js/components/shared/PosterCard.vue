<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { computed } from 'vue';
import ExternalRatings from '@/components/ExternalRatings.vue';
import type { ExternalRating } from '@/components/ExternalRatings.vue';
import BookmarkToggle from '@/components/shared/BookmarkToggle.vue';
import TmdbImage from '@/components/TmdbImage.vue';
import { tvBadgeLabel } from '@/lib/searchKinds';
import type { CardKind } from '@/lib/searchKinds';
import type { UserMovieActionSource, UserMovieFacts } from '@/types';

/**
 * One Movie or TV Show on a poster card, linked to its page (the entry route when it has no row yet). A TV Show
 * wears a badge on its poster and has no Movie Watchlist toggle.
 */
export type PosterCardTitle = {
    /** A card without a kind is a Movie. */
    kind?: CardKind;
    /** The catalog id for a Movie, and `tv:` and it for a TV Show, when a Movie and a TV Show may share a page. */
    key?: string;
    /** How many Seasons a TV Show has, when known. */
    seasonCount?: number | null;
    tmdbId: number;
    title: string | null;
    year: number | null;
    posterPath: string | null;
    /** The ratings in display order; the card shows the first. */
    ratings: ExternalRating[];
    href: string;
    /** The signed-in viewer's own rating, on their Watchlist and Watched cards only; null or absent when unrated. */
    userRating?: number | null;
};

/**
 * The poster card: the poster (or missing-poster tile) with the Watchlist toggle floating on it, the title clamped
 * to 2 lines, the year when known and one External rating. Poster and title share one link target, so the card is
 * one tab stop plus the toggle. On the viewer's own lists a card also shows "Your rating" when they have rated it.
 */
const props = withDefaults(
    defineProps<{
        imageBaseUrl: string;
        movie: PosterCardTitle;
        facts: UserMovieFacts | undefined;
        source: UserMovieActionSource;
        /** Load the poster at once, for cards above the fold. */
        eager?: boolean;
        showBookmark?: boolean;
        /** The card's ratings are still being looked up, so a placeholder shows where they go. */
        ratingPending?: boolean;
    }>(),
    { eager: false, showBookmark: true, ratingPending: false },
);

const isTvShow = computed(() => props.movie.kind === 'tv');
const title = computed(
    () => props.movie.title ?? (isTvShow.value ? 'TV show' : 'Movie'),
);
</script>

<template>
    <article
        data-slot="poster-card"
        :data-tmdb-id="movie.tmdbId"
        :data-card-key="movie.key ?? movie.tmdbId"
    >
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
            <span
                v-if="isTvShow"
                data-slot="tv-badge"
                class="absolute bottom-1.5 left-1.5 rounded-md bg-black/75 px-1.5 py-0.5 text-xs font-medium text-white"
            >
                {{ tvBadgeLabel(movie.seasonCount) }}
            </span>
            <div
                v-if="showBookmark && !isTvShow"
                class="absolute top-1.5 right-1.5"
            >
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
            <ExternalRatings
                :ratings="movie.ratings"
                variant="single"
                compact
                :pending="ratingPending"
            />
            <p
                v-if="movie.userRating != null"
                data-slot="user-rating"
                class="flex items-center gap-1 text-foreground"
            >
                <Star
                    class="size-3.5 fill-current text-accent"
                    aria-hidden="true"
                />
                <span>
                    Your rating
                    <span class="font-semibold tabular-nums">{{
                        movie.userRating
                    }}</span
                    ><span class="text-muted">/10</span>
                </span>
            </p>
            <slot name="date" />
        </div>
    </article>
</template>
