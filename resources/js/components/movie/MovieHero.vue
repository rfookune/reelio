<script setup lang="ts">
import { computed } from 'vue';
import ExternalRatings from '@/components/ExternalRatings.vue';
import type { ExternalRating } from '@/components/ExternalRatings.vue';
import TmdbImage from '@/components/TmdbImage.vue';
import { formatRuntime, isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';

const props = defineProps<{
    imageBaseUrl: string;
    title: string | null;
    originalTitle: string | null;
    originalLanguage: string | null;
    year: number | null;
    runtime: number | null;
    certification: string | null;
    genres: string[];
    posterPath: string | null;
    backdropPath: string | null;
    ratings: ExternalRating[];
}>();

const displayTitle = computed(() => props.title ?? 'Movie');
const showOriginalTitle = computed(
    () =>
        props.originalTitle !== null &&
        props.originalTitle !== '' &&
        props.originalTitle !== props.title,
);
const hasBackdrop = computed(() => isSafeTmdbPath(props.backdropPath));
const hasPoster = computed(() => isSafeTmdbPath(props.posterPath));
const backdropUrl = (width: 780 | 1280) =>
    tmdbImageUrl(props.imageBaseUrl, width, props.backdropPath ?? '');
const blurredPosterUrl = computed(() =>
    tmdbImageUrl(props.imageBaseUrl, 185, props.posterPath ?? ''),
);
const meta = computed(() =>
    [props.year, formatRuntime(props.runtime)].filter((part) => part !== null),
);
</script>

<template>
    <header
        data-slot="movie-hero"
        class="relative ml-[calc(50%-50vw)] w-screen"
    >
        <div
            data-slot="movie-hero-backdrop"
            aria-hidden="true"
            class="absolute inset-0 overflow-hidden bg-surface [mask-image:linear-gradient(to_bottom,black_calc(100%-7rem),transparent)] md:[mask-image:linear-gradient(to_bottom,black_calc(100%-10rem),transparent)]"
        >
            <picture v-if="hasBackdrop">
                <source
                    media="(min-width: 768px)"
                    :srcset="backdropUrl(1280)"
                />
                <img
                    :src="backdropUrl(780)"
                    alt=""
                    fetchpriority="high"
                    decoding="async"
                    class="size-full object-cover"
                />
            </picture>
            <img
                v-else-if="hasPoster"
                :src="blurredPosterUrl"
                alt=""
                decoding="async"
                class="size-full scale-125 object-cover blur-2xl"
            />
        </div>

        <div
            class="relative mx-auto flex max-w-7xl items-end gap-6 px-4 pt-32 pb-36 md:px-8 md:pt-48 md:pb-52"
        >
            <div class="hidden w-40 shrink-0 sm:block md:w-52">
                <TmdbImage
                    :base-url="imageBaseUrl"
                    :path="posterPath"
                    :alt="displayTitle"
                    :widths="[342, 500]"
                    sizes="(min-width: 768px) 208px, 160px"
                    class="rounded-lg shadow-lg ring-1 ring-line"
                />
            </div>
            <div class="flex min-w-0 flex-col gap-2">
                <h1
                    class="text-3xl font-semibold tracking-tight text-balance md:text-5xl"
                >
                    {{ displayTitle }}
                </h1>
                <p
                    v-if="showOriginalTitle"
                    :lang="originalLanguage ?? undefined"
                    class="text-muted"
                >
                    {{ originalTitle }}
                </p>
                <p
                    class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted"
                >
                    <template v-for="(part, index) in meta" :key="index">
                        <span v-if="index > 0" aria-hidden="true">·</span>
                        <span>{{ part }}</span>
                    </template>
                    <span
                        v-if="certification"
                        class="rounded border border-control px-1.5 text-xs font-medium text-foreground"
                    >
                        {{ certification }}
                    </span>
                    <template v-if="genres.length > 0">
                        <span
                            v-if="meta.length > 0 || certification"
                            aria-hidden="true"
                            >·</span
                        >
                        <span>{{ genres.join(', ') }}</span>
                    </template>
                </p>
                <ExternalRatings :ratings="ratings" />
                <div
                    v-if="$slots.actions"
                    data-slot="movie-actions"
                    class="mt-3 flex flex-wrap items-center gap-3"
                >
                    <slot name="actions" />
                </div>
            </div>
        </div>
    </header>
</template>
