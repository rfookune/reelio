<script setup lang="ts">
import { Tv } from '@lucide/vue';
import { isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';

defineProps<{
    imageBaseUrl: string;
    service: { name: string; logoPath: string | null };
    /** The name is written beside the logo, so a missing logo's placeholder needn't name the service too. */
    nameShown?: boolean;
}>();
</script>

<template>
    <img
        v-if="isSafeTmdbPath(service.logoPath)"
        :src="tmdbImageUrl(imageBaseUrl, 92, service.logoPath)"
        :alt="service.name"
        :title="nameShown ? undefined : service.name"
        width="40"
        height="40"
        loading="lazy"
        decoding="async"
        class="size-10 shrink-0 rounded-lg bg-raised object-cover"
    />
    <span
        v-else
        :role="nameShown ? undefined : 'img'"
        :aria-label="nameShown ? undefined : service.name"
        :aria-hidden="nameShown ? 'true' : undefined"
        :title="nameShown ? undefined : service.name"
        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-raised text-muted"
    >
        <Tv class="size-5" :stroke-width="1.5" aria-hidden="true" />
    </span>
</template>
