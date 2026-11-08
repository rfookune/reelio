<script setup lang="ts">
import type { CastMember } from '@/components/casts/CastGrid.vue';
import FullCast from '@/components/casts/FullCast.vue';
import type { CrewMember } from '@/lib/crew';

defineProps<{
    kind: 'movie' | 'tv';
    canonicalUrl: string;
    titleUrl: string;
    imageBaseUrl: string;
    title: {
        name: string | null;
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
        :title-url="titleUrl"
        :noun="kind === 'tv' ? 'TV show' : 'Movie'"
        :image-base-url="imageBaseUrl"
        :title="title"
    />
</template>
