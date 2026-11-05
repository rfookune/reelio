<script setup lang="ts">
/** PROTOTYPE, throwaway. A loading block in the selected skeleton style. */
import { inject } from 'vue';
import type { SkeletonStyle } from './usePrototypeState';

const skeletonStyle = inject<{ value: SkeletonStyle }>('rpSkeletonStyle');
</script>

<template>
    <div
        aria-hidden="true"
        :class="[
            'rp-skel bg-(--rp-raised)',
            skeletonStyle?.value === 'shimmer'
                ? 'rp-skel--shimmer'
                : 'rp-skel--pulse',
        ]"
    />
</template>
