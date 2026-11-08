<script setup lang="ts">
import type { CastMember } from '@/components/movie/CastGrid.vue';
import FullCast from '@/components/movie/FullCast.vue';
import type { CrewMember } from '@/lib/crew';

defineProps<{
    canonicalUrl: string;
    tvShowUrl: string;
    imageBaseUrl: string;
    tvShow: {
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
        :title-url="tvShowUrl"
        noun="TV show"
        :image-base-url="imageBaseUrl"
        :title="tvShow"
    />
</template>
