<script setup lang="ts">
/** PROTOTYPE, throwaway. Stand-in for a cache-only section when the Catalog source is down. */
import { CloudOff } from '@lucide/vue';

defineProps<{ what: string; aspect?: 'video' | 'strip' }>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-(--rp-line) p-6 text-center text-sm text-(--rp-muted)"
        :class="aspect === 'video' ? 'aspect-video' : 'min-h-32'"
    >
        <CloudOff class="size-5" />
        <p>{{ what }} temporarily unavailable. Try again in a few minutes.</p>
    </div>
</template>
