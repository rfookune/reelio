<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ExternalRatings from '@/components/ExternalRatings.vue';
import type { ExternalRating } from '@/components/ExternalRatings.vue';
import BookmarkToggle from '@/components/shared/BookmarkToggle.vue';
import TmdbImage from '@/components/TmdbImage.vue';
import { formatDayMonth } from '@/lib/dates';
import { formatRuntime } from '@/lib/tmdb';
import type { UserMovieFacts } from '@/types';

/** One Movie on Discover, linked to its page (the entry route when it has no row yet). */
export type DiscoverMovie = {
    tmdbId: number;
    title: string | null;
    year: number | null;
    runtime: number | null;
    posterPath: string | null;
    ratings: ExternalRating[];
    href: string;
    /** Coming soon's US date (Y-m-d); null in other sections. */
    opensOn: string | null;
};

/**
 * A list row: rank (when numbered), small poster, title, year (or, in Coming soon, "Opens 21 Nov") / runtime, both
 * External ratings, bookmark.
 */
const props = defineProps<{
    imageBaseUrl: string;
    movie: DiscoverMovie;
    rank: number | null;
    facts: UserMovieFacts | undefined;
}>();

const title = computed(() => props.movie.title ?? 'Movie');
const meta = computed(() =>
    [
        props.movie.opensOn === null
            ? props.movie.year
            : `Opens ${formatDayMonth(props.movie.opensOn)}`,
        formatRuntime(props.movie.runtime),
    ]
        .filter((part) => part !== null)
        .join(' · '),
);
</script>

<template>
    <li
        data-slot="discover-row"
        :data-tmdb-id="movie.tmdbId"
        class="flex items-center gap-3 rounded-xl border border-line bg-surface p-2 pr-3"
    >
        <span
            v-if="rank !== null"
            data-slot="rank"
            class="w-7 shrink-0 text-center text-lg font-semibold text-muted tabular-nums"
        >
            {{ rank }}
        </span>
        <Link
            :href="movie.href"
            tabindex="-1"
            aria-hidden="true"
            class="w-14 shrink-0"
        >
            <TmdbImage
                :base-url="imageBaseUrl"
                :path="movie.posterPath"
                :alt="title"
                :widths="[92, 154]"
                sizes="56px"
                class="rounded-lg"
            />
        </Link>
        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <Link
                :href="movie.href"
                class="line-clamp-2 font-medium underline-offset-4 hover:underline"
            >
                {{ title }}
            </Link>
            <p v-if="meta" class="text-sm text-muted tabular-nums">
                {{ meta }}
            </p>
            <ExternalRatings :ratings="movie.ratings" compact />
        </div>
        <BookmarkToggle
            :tmdb-id="movie.tmdbId"
            :title="title"
            :facts="facts"
            source="discover"
        />
    </li>
</template>
