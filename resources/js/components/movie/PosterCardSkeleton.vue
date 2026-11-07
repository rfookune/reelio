<script setup lang="ts">
import ShimmerSkeleton from '@/components/ShimmerSkeleton.vue';

/** A poster card while it loads: the poster, the title and the year as shimmer. */
</script>

<template>
    <div data-slot="poster-card-skeleton" aria-hidden="true">
        <ShimmerSkeleton class="aspect-[2/3] rounded-lg" />
        <ShimmerSkeleton class="mt-2.5 h-4 w-4/5" />
        <ShimmerSkeleton class="mt-2 h-3 w-1/3" />
    </div>
</template>
