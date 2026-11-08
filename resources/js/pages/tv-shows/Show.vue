<script setup lang="ts">
import SeoHead from '@/components/SeoHead.vue';
import TmdbImage from '@/components/TmdbImage.vue';

defineProps<{
    canonicalUrl: string;
    imageBaseUrl: string;
    catalogStatus: 'fresh' | 'stale';
    tvShow: {
        id: number;
        title: string | null;
        overview: string | null;
        posterPath: string | null;
    };
}>();
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-5xl flex-col gap-8 px-5 py-10 sm:flex-row sm:px-8"
    >
        <SeoHead
            :title="tvShow.title ?? 'TV show'"
            :description="tvShow.overview ?? 'TV show details on Reelio.'"
            :canonical-url="canonicalUrl"
        />
        <div
            class="w-48 shrink-0 overflow-hidden rounded-lg ring-1 ring-line sm:w-64"
        >
            <TmdbImage
                :base-url="imageBaseUrl"
                :path="tvShow.posterPath"
                :alt="`${tvShow.title ?? 'TV show'} poster`"
                :placeholder="tvShow.title ?? 'TV show'"
                :widths="[185, 342, 500]"
                sizes="(min-width: 640px) 256px, 192px"
                loading="eager"
            />
        </div>
        <div class="flex flex-col gap-5">
            <p class="text-sm text-muted">TV show</p>
            <h1 class="text-4xl font-bold tracking-tight">
                {{ tvShow.title ?? 'TV show' }}
            </h1>
            <p
                v-if="catalogStatus === 'stale'"
                role="status"
                class="text-sm text-muted"
            >
                Details may be out of date. We couldn’t refresh this TV show
                right now.
            </p>
            <p v-if="tvShow.overview" class="max-w-prose leading-relaxed">
                {{ tvShow.overview }}
            </p>
        </div>
    </div>
</template>
