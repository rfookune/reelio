<script setup lang="ts">
import DiscoverRow from '@/components/discover/DiscoverRow.vue';
import type { DiscoverMovie } from '@/components/discover/DiscoverRow.vue';
import type { UserMovies } from '@/types';

export type DiscoverSection = {
    /** Unique on the page, such as `genre-878`; the three genre collections share a kind. */
    key: string;
    kind: string;
    title: string;
    numbered: boolean;
    movies: DiscoverMovie[];
};

defineProps<{
    imageBaseUrl: string;
    section: DiscoverSection;
    userMovies: UserMovies;
}>();
</script>

<template>
    <section
        :data-section="section.key"
        :aria-labelledby="`section-${section.key}`"
    >
        <h2 :id="`section-${section.key}`" class="mb-4 text-xl font-semibold">
            {{ section.title }}
        </h2>
        <component
            :is="section.numbered ? 'ol' : 'ul'"
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
        >
            <DiscoverRow
                v-for="(movie, index) in section.movies"
                :key="movie.tmdbId"
                :image-base-url="imageBaseUrl"
                :movie="movie"
                :rank="section.numbered ? index + 1 : null"
                :facts="userMovies[movie.tmdbId]"
            />
        </component>
    </section>
</template>
