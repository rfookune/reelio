<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import MovieCast from '@/components/movie/MovieCast.vue';
import type { CastMember } from '@/components/movie/MovieCast.vue';
import MovieDetailsCard from '@/components/movie/MovieDetailsCard.vue';
import MovieHero from '@/components/movie/MovieHero.vue';
import { isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';

const props = defineProps<{
    canonicalUrl: string;
    imageBaseUrl: string;
    movie: {
        id: number;
        title: string | null;
        originalTitle: string | null;
        originalLanguage: string | null;
        year: number | null;
        releaseDate: string | null;
        runtime: number | null;
        tagline: string | null;
        overview: string | null;
        posterPath: string | null;
        backdropPath: string | null;
        genres: string[];
        directors: string[];
        writers: string[];
        cast: CastMember[];
        certification: string | null;
        usReleaseDate: string | null;
        detailsUnavailable: boolean;
    };
}>();

const glowUrl = (width: 780 | 1280) =>
    tmdbImageUrl(props.imageBaseUrl, width, props.movie.backdropPath ?? '');
const hasGlow = computed(() => isSafeTmdbPath(props.movie.backdropPath));

/*
 * The sticky column clears the 4rem top bar plus 1.5rem. When it is taller
 * than the viewport, `top` goes negative so its bottom stays reachable. The
 * measurement is client-only; the server renders the default.
 */
const barOffset = 64;
const gap = 24;
const stickyColumn = ref<HTMLElement | null>(null);
const stickyTop = ref(`${(barOffset + gap) / 16}rem`);

function measureStickyTop(): void {
    const column = stickyColumn.value;

    if (column === null) {
        return;
    }

    const top = Math.min(
        barOffset + gap,
        window.innerHeight - column.offsetHeight - gap,
    );

    stickyTop.value = `${top}px`;
}

onMounted(() => {
    void nextTick(measureStickyTop);
    window.addEventListener('resize', measureStickyTop);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', measureStickyTop);
});
</script>

<template>
    <div class="pb-12">
        <Head :title="movie.title ?? 'Movie'">
            <link rel="canonical" :href="canonicalUrl" />
        </Head>

        <MovieHero
            :image-base-url="imageBaseUrl"
            :title="movie.title"
            :original-title="movie.originalTitle"
            :original-language="movie.originalLanguage"
            :year="movie.year"
            :runtime="movie.runtime"
            :certification="movie.certification"
            :genres="movie.genres"
            :poster-path="movie.posterPath"
            :backdrop-path="movie.backdropPath"
        />

        <div
            data-slot="movie-body"
            class="relative -mt-28 grid gap-x-12 gap-y-10 md:-mt-40 lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-y-0"
        >
            <picture
                v-if="hasGlow"
                data-slot="movie-glow"
                aria-hidden="true"
                class="pointer-events-none absolute inset-x-0 -top-16 -z-10 h-64 overflow-hidden opacity-30 blur-3xl"
            >
                <source media="(min-width: 768px)" :srcset="glowUrl(1280)" />
                <img
                    :src="glowUrl(780)"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="size-full object-cover"
                />
            </picture>

            <section
                v-if="movie.tagline || movie.overview"
                data-slot="movie-overview"
                class="flex max-w-prose flex-col gap-3 lg:col-start-1 lg:mb-10"
            >
                <p v-if="movie.tagline" class="text-lg text-muted italic">
                    {{ movie.tagline }}
                </p>
                <h2 class="sr-only">Overview</h2>
                <p v-if="movie.overview" class="leading-relaxed">
                    {{ movie.overview }}
                </p>
            </section>

            <!-- Where to watch and Trailer slot in here, before Cast. -->

            <MovieCast
                :image-base-url="imageBaseUrl"
                :cast="movie.cast"
                :unavailable="movie.detailsUnavailable"
                class="lg:col-start-1 lg:mb-10"
            />

            <div
                ref="stickyColumn"
                data-slot="movie-details-column"
                :style="{ top: stickyTop }"
                class="lg:sticky lg:col-start-2 lg:row-span-4 lg:row-start-1 lg:self-start"
            >
                <MovieDetailsCard
                    :directors="movie.directors"
                    :writers="movie.writers"
                    :release-date="movie.releaseDate"
                    :us-release-date="movie.usReleaseDate"
                    :runtime="movie.runtime"
                    :certification="movie.certification"
                    :genres="movie.genres"
                    :title="movie.title"
                    :original-title="movie.originalTitle"
                    :original-language="movie.originalLanguage"
                    :unavailable="movie.detailsUnavailable"
                />
            </div>
        </div>
    </div>
</template>
