<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Play } from '@lucide/vue';
import { computed } from 'vue';
import type { DiscoverMovie } from '@/components/discover/DiscoverRow.vue';
import ExternalRatings from '@/components/ExternalRatings.vue';
import TrailerPlayer from '@/components/movie/TrailerPlayer.vue';
import type { Trailer } from '@/components/movie/TrailerPlayer.vue';
import WatchlistButton from '@/components/movie/WatchlistButton.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatRuntime, isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';
import type { UserMovieFacts } from '@/types';

export type DiscoverHeroMovie = Omit<DiscoverMovie, 'opensOn'> & {
    overview: string | null;
    backdropPath: string | null;
    genres: string[];
    trailer: Trailer | null;
};

/**
 * The daily featured Movie: a full-bleed backdrop that runs up under the top bar, which floats over it as a
 * blurred glow (the bar turns translucent while the page has one), the title set large, year, runtime and genres, both External ratings, the overview, and
 * "Add to Watchlist" and "Watch trailer" (only when a trailer exists).
 */
const props = defineProps<{
    imageBaseUrl: string;
    movie: DiscoverHeroMovie;
    facts: UserMovieFacts | undefined;
}>();

const title = computed(() => props.movie.title ?? 'Movie');
const hasBackdrop = computed(() => isSafeTmdbPath(props.movie.backdropPath));
const backdropUrl = (width: 780 | 1280) =>
    tmdbImageUrl(props.imageBaseUrl, width, props.movie.backdropPath ?? '');
const meta = computed(() =>
    [
        props.movie.year,
        formatRuntime(props.movie.runtime),
        props.movie.genres.length > 0 ? props.movie.genres.join(', ') : null,
    ].filter((part) => part !== null),
);
</script>

<template>
    <section
        data-slot="discover-hero"
        :data-tmdb-id="movie.tmdbId"
        :data-top-bar-glow="hasBackdrop ? '' : undefined"
        aria-labelledby="discover-hero-title"
        class="relative -mt-[calc(4rem+1px)] ml-[calc(50%-50vw)] w-screen"
    >
        <div
            aria-hidden="true"
            class="absolute inset-0 overflow-hidden bg-surface [mask-image:linear-gradient(to_bottom,black_calc(100%-8rem),transparent)]"
        >
            <picture v-if="hasBackdrop">
                <source
                    media="(min-width: 768px)"
                    :srcset="backdropUrl(1280)"
                />
                <img
                    data-slot="discover-hero-backdrop"
                    :src="backdropUrl(780)"
                    alt=""
                    fetchpriority="high"
                    loading="eager"
                    decoding="async"
                    class="size-full object-cover"
                />
            </picture>
            <div
                class="absolute inset-0 bg-gradient-to-t from-background via-background/85 to-background/30 md:bg-gradient-to-r md:from-background md:via-background/70 md:to-transparent"
            ></div>
        </div>

        <div
            class="relative mx-auto flex max-w-7xl flex-col gap-3 px-4 pt-40 pb-16 md:px-8 md:pt-56 md:pb-24"
        >
            <h2
                id="discover-hero-title"
                class="max-w-3xl text-4xl font-bold tracking-tight text-balance md:text-6xl"
            >
                <Link
                    :href="movie.href"
                    class="underline-offset-8 hover:underline"
                >
                    {{ title }}
                </Link>
            </h2>
            <p
                v-if="meta.length > 0"
                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted tabular-nums"
            >
                <template v-for="(part, index) in meta" :key="index">
                    <span v-if="index > 0" aria-hidden="true">·</span>
                    <span>{{ part }}</span>
                </template>
            </p>
            <ExternalRatings :ratings="movie.ratings" />
            <p
                v-if="movie.overview"
                class="line-clamp-4 max-w-prose leading-relaxed md:line-clamp-none"
            >
                {{ movie.overview }}
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <WatchlistButton
                    :tmdb-id="movie.tmdbId"
                    :facts="facts"
                    source="discover"
                />
                <Dialog v-if="movie.trailer">
                    <DialogTrigger as-child>
                        <Button
                            type="button"
                            size="lg"
                            variant="outline"
                            data-slot="watch-trailer"
                        >
                            <Play aria-hidden="true" />
                            Watch trailer
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-3xl">
                        <DialogTitle>Trailer: {{ title }}</DialogTitle>
                        <DialogDescription class="sr-only">
                            The trailer plays from YouTube when you press play.
                        </DialogDescription>
                        <TrailerPlayer
                            :image-base-url="imageBaseUrl"
                            :title="title"
                            :trailer="movie.trailer"
                            :cover-path="movie.backdropPath"
                            hide-heading
                        />
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </section>
</template>
