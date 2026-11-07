<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import CastGrid from '@/components/movie/CastGrid.vue';
import type { CastMember } from '@/components/movie/CastGrid.vue';

defineProps<{
    canonicalUrl: string;
    movieUrl: string;
    imageBaseUrl: string;
    movie: {
        title: string | null;
        year: number | null;
        cast: CastMember[];
        detailsUnavailable: boolean;
    };
}>();
</script>

<template>
    <div class="flex flex-col gap-8 py-8">
        <Head :title="`Full cast · ${movie.title ?? 'Movie'}`">
            <link rel="canonical" :href="canonicalUrl" />
        </Head>

        <div class="flex flex-col gap-3">
            <Link
                :href="movieUrl"
                data-slot="back-to-movie"
                class="inline-flex items-center gap-1.5 self-start rounded-lg text-sm text-accent underline-offset-4 hover:underline"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ movie.title ?? 'Movie'
                }}<template v-if="movie.year"> ({{ movie.year }})</template>
            </Link>
            <h1 class="text-3xl font-semibold tracking-tight tabular-nums">
                Full cast
                <span v-if="movie.cast.length > 0" class="text-muted"
                    >· {{ movie.cast.length }}</span
                >
            </h1>
        </div>

        <p
            v-if="movie.detailsUnavailable"
            data-slot="cast-unavailable"
            class="rounded-xl border border-line bg-surface p-5 text-sm text-muted"
        >
            Cast is temporarily unavailable.
        </p>
        <p
            v-else-if="movie.cast.length === 0"
            data-slot="cast-empty"
            class="rounded-xl border border-line bg-surface p-5 text-sm text-muted"
        >
            No cast is listed for this movie.
        </p>
        <CastGrid v-else :image-base-url="imageBaseUrl" :cast="movie.cast" />
    </div>
</template>
