<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TmdbImage from '@/components/TmdbImage.vue';

defineProps<{
    canonicalUrl: string;
    imageBaseUrl: string;
    movie: {
        id: number;
        title: string | null;
        year: number | null;
        overview: string | null;
        posterPath: string | null;
    };
}>();
</script>

<template>
    <div class="flex flex-col gap-8 py-10 sm:flex-row">
        <Head :title="movie.title ?? 'Movie'">
            <link rel="canonical" :href="canonicalUrl" />
        </Head>
        <TmdbImage
            :base-url="imageBaseUrl"
            :path="movie.posterPath"
            :alt="movie.title ?? 'Movie'"
            :widths="[342, 500]"
            sizes="192px"
            class="w-48 shrink-0 rounded-lg"
        />
        <div class="flex max-w-prose flex-col gap-4">
            <h1 class="text-3xl font-semibold tracking-tight">
                {{ movie.title }}
            </h1>
            <p v-if="movie.year" class="text-muted">{{ movie.year }}</p>
            <p v-if="movie.overview" class="leading-relaxed">
                {{ movie.overview }}
            </p>
        </div>
    </div>
</template>
