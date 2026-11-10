<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import type { ExternalRating } from '@/components/ExternalRatings.vue';
import { watch as watchPage } from '@/routes/movies';
import SeoHead from '@/components/SeoHead.vue';
import TitleCast from '@/components/casts/TitleCast.vue';
import type { CastMember } from '@/components/casts/TitleCast.vue';
import MovieDetailsCard from '@/components/movie/MovieDetailsCard.vue';
import MovieActions from '@/components/movie/MovieActions.vue';
import TitleHero from '@/components/shared/TitleHero.vue';
import TrailerPlayer from '@/components/shared/TrailerPlayer.vue';
import WhereToWatch from '@/components/shared/WhereToWatch.vue';
import type { WhereToWatchBlock } from '@/components/shared/WhereToWatch.vue';
import type { Trailer } from '@/components/shared/TrailerPlayer.vue';
import { describeMovie, movieJsonLd, movieTitle, shareImage } from '@/lib/seo';
import { isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';
import type { UserMovies } from '@/types';

const props = defineProps<{
    canonicalUrl: string;
    castUrl: string;
    imageBaseUrl: string;
    whereToWatch: WhereToWatchBlock;
    userMovies: UserMovies;
    /** The signed-in viewer's own rating of the Movie, or null. */
    userRating: number | null;
    movie: {
        id: number;
        tmdbId: number;
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
        castCount: number;
        certification: string | null;
        usReleaseDate: string | null;
        ratings: ExternalRating[];
        trailer: Trailer | null;
        detailsUnavailable: boolean;
    };
}>();

const glowUrl = (width: 780 | 1280) =>
    tmdbImageUrl(props.imageBaseUrl, width, props.movie.backdropPath ?? '');
const page = usePage();
const user = computed(() => page.props.auth.user);
const title = computed(() => movieTitle(props.movie.title, props.movie.year));
const description = computed(() =>
    describeMovie(
        props.movie.overview,
        `Where to watch ${props.movie.title ?? 'this movie'}, with its cast, ratings and trailer.`,
    ),
);
const image = computed(() =>
    shareImage(
        props.imageBaseUrl,
        props.movie.backdropPath,
        props.movie.posterPath,
        page.props.seo.card,
    ),
);
const jsonLd = computed(() =>
    movieJsonLd({
        name: props.movie.title ?? 'Movie',
        datePublished: props.movie.releaseDate,
        directors: props.movie.directors,
        genres: props.movie.genres,
        runtime: props.movie.runtime,
        image: image.value,
        description: props.movie.overview,
        url: props.canonicalUrl,
    }),
);
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

/* The column's height changes with the viewport and when a Where to watch tab is switched. */
let columnObserver: ResizeObserver | null = null;

onMounted(() => {
    void nextTick(measureStickyTop);
    window.addEventListener('resize', measureStickyTop);

    if (stickyColumn.value !== null && 'ResizeObserver' in window) {
        columnObserver = new ResizeObserver(measureStickyTop);
        columnObserver.observe(stickyColumn.value);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', measureStickyTop);
    columnObserver?.disconnect();
});
</script>

<template>
    <div class="pb-12">
        <SeoHead
            :title="title"
            :description="description"
            :image="image"
            :canonical-url="canonicalUrl"
            type="video.movie"
            :json-ld="jsonLd"
        />

        <TitleHero
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
            :ratings="movie.ratings"
        >
            <template #actions>
                <MovieActions
                    :tmdb-id="movie.tmdbId"
                    :facts="userMovies[movie.tmdbId]"
                    :rating="
                        user
                            ? {
                                  titleId: movie.id,
                                  title: movie.title,
                                  value: userRating,
                              }
                            : undefined
                    "
                />
            </template>
        </TitleHero>

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
                class="order-1 flex max-w-prose flex-col gap-3 lg:col-start-1 lg:mb-10"
            >
                <p v-if="movie.tagline" class="text-lg text-muted italic">
                    {{ movie.tagline }}
                </p>
                <h2 class="sr-only">Overview</h2>
                <p v-if="movie.overview" class="leading-relaxed">
                    {{ movie.overview }}
                </p>
            </section>

            <div
                ref="stickyColumn"
                data-slot="movie-details-column"
                :style="{ top: stickyTop }"
                class="contents lg:sticky lg:col-start-2 lg:row-span-4 lg:row-start-1 lg:flex lg:flex-col lg:gap-6 lg:self-start"
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
                    class="order-5 lg:order-none"
                />
                <WhereToWatch
                    :image-base-url="imageBaseUrl"
                    :watch-url="
                        watchPage(
                            { id: movie.id },
                            { query: { country: whereToWatch.country } },
                        ).url
                    "
                    :block="whereToWatch"
                    class="order-2 lg:order-none"
                />
            </div>

            <TrailerPlayer
                v-if="movie.trailer"
                :image-base-url="imageBaseUrl"
                :title="movie.title ?? 'Movie'"
                :trailer="movie.trailer"
                :cover-path="movie.backdropPath"
                class="order-3 lg:col-start-1 lg:mb-10"
            />
            <section
                v-else-if="movie.detailsUnavailable"
                data-slot="trailer-unavailable"
                aria-labelledby="trailer-heading"
                class="order-3 lg:col-start-1 lg:mb-10"
            >
                <h2 id="trailer-heading" class="mb-4 text-xl font-semibold">
                    Trailer
                </h2>
                <p
                    class="rounded-xl border border-line bg-surface p-5 text-sm text-muted"
                >
                    The trailer is temporarily unavailable.
                </p>
            </section>

            <TitleCast
                :image-base-url="imageBaseUrl"
                :cast="movie.cast"
                :unavailable="movie.detailsUnavailable"
                :full-cast="{ href: castUrl, count: movie.castCount }"
                class="order-4 lg:col-start-1 lg:mb-10"
            />
        </div>
    </div>
</template>
