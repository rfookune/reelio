<script setup lang="ts">
import type { CastMember } from '@/components/movie/CastGrid.vue';
import FullCast from '@/components/movie/FullCast.vue';
import type { CrewMember } from '@/lib/crew';

defineProps<{
    canonicalUrl: string;
    movieUrl: string;
    imageBaseUrl: string;
    movie: {
        title: string | null;
        year: number | null;
        cast: CastMember[];
        crew: CrewMember[];
        detailsUnavailable: boolean;
    };
}>();
</script>

<template>
    <FullCast
        :canonical-url="canonicalUrl"
        :title-url="movieUrl"
        noun="Movie"
        :image-base-url="imageBaseUrl"
        :title="movie"
    />
</template>
