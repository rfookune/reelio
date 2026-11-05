<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Variant A: "Backdrop hero + side rail".
 *
 * Continues Discover's language: a full-bleed backdrop hero with the poster, title block,
 * ratings and actions over it. Below, a two-column body: story, trailer and cast on the
 * left; a sticky rail with the facts on the right.
 * LCP element: the hero backdrop (w1280). No backdrop → a blurred poster stands in.
 *
 * Iteration 2: cast is variant C's photo grid; `watch` picks one of five Where to watch
 * designs and where it sits. On mobile, a rail block moves to straight after the story.
 */
import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';
import CastList from './CastList.vue';
import type { AvailabilityLook } from './AvailabilityBlock.vue';
import AvailabilityBlock from './AvailabilityBlock.vue';
import AvailabilitySummary from './AvailabilitySummary.vue';
import DetailActions from './DetailActions.vue';
import DetailFacts from './DetailFacts.vue';
import DetailRatings from './DetailRatings.vue';
import DetailShell from './DetailShell.vue';
import TrailerPlayer from './TrailerPlayer.vue';
import UnavailablePanel from './UnavailablePanel.vue';
import SkeletonBlock from '../shell/SkeletonBlock.vue';
import { useDetailState } from './useDetailState';

export type WatchDesign =
    | 'rail-logos'
    | 'rail-tabs'
    | 'band'
    | 'hero-summary'
    | 'services';

const props = withDefaults(defineProps<{ watch?: WatchDesign }>(), {
    watch: 'rail-logos',
});

defineEmits<{ changeCountry: [code: string] }>();

const { settings, view } = useDetailState();

const isDesktop = useMediaQuery('(min-width: 1024px)');

const looks: Record<WatchDesign, AvailabilityLook> = {
    'rail-logos': 'logos',
    'rail-tabs': 'tabs',
    band: 'columns',
    'hero-summary': 'list',
    services: 'services',
};

const look = computed(() => looks[props.watch]);
const isRailDesign = computed(
    () => props.watch === 'rail-logos' || props.watch === 'rail-tabs',
);
const inRail = computed(() => isRailDesign.value && isDesktop.value);
const afterStory = computed(
    () =>
        props.watch === 'hero-summary' ||
        props.watch === 'services' ||
        (isRailDesign.value && !isDesktop.value),
);
</script>

<template>
    <DetailShell overlay>
        <!-- Hero -->
        <section
            class="relative isolate overflow-hidden"
            aria-labelledby="movie-title"
        >
            <SkeletonBlock
                v-if="settings.state === 'loading'"
                class="absolute inset-0 -z-10"
            />
            <template v-else>
                <img
                    v-if="view.backdropUrl"
                    :src="view.backdropUrl"
                    alt=""
                    fetchpriority="high"
                    class="absolute inset-0 -z-10 size-full object-cover"
                />
                <img
                    v-else-if="view.posterUrl"
                    :src="view.posterUrl"
                    alt=""
                    class="absolute inset-0 -z-10 size-full scale-125 object-cover opacity-60 blur-2xl"
                />
            </template>
            <div
                class="absolute inset-0 -z-10 bg-gradient-to-t from-(--rp-bg) via-black/60 to-black/40"
            />

            <div
                class="mx-auto flex max-w-7xl flex-col gap-6 px-4 pt-24 pb-10 text-white sm:flex-row sm:items-end md:px-8 md:pt-40 lg:pt-56"
            >
                <div
                    class="hidden w-40 shrink-0 overflow-hidden rounded-lg shadow-2xl ring-1 ring-white/20 sm:block lg:w-52"
                >
                    <SkeletonBlock
                        v-if="settings.state === 'loading'"
                        class="aspect-[2/3]"
                    />
                    <img
                        v-else-if="view.posterUrl"
                        :src="view.posterUrl"
                        :alt="`Poster for ${view.title}`"
                        class="aspect-[2/3] w-full object-cover"
                    />
                </div>

                <div
                    v-if="settings.state === 'loading'"
                    class="flex-1 space-y-4"
                >
                    <SkeletonBlock class="h-12 w-2/3 rounded-lg" />
                    <SkeletonBlock class="h-5 w-1/2 rounded" />
                    <SkeletonBlock class="h-11 w-72 rounded-lg" />
                </div>
                <div v-else class="min-w-0 flex-1">
                    <h1
                        id="movie-title"
                        class="text-4xl leading-none font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl"
                    >
                        {{ view.title }}
                    </h1>
                    <p
                        v-if="view.originalTitle"
                        class="mt-2 text-sm text-white/70"
                    >
                        Original title: {{ view.originalTitle }}
                    </p>
                    <p
                        class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-white/80"
                    >
                        <span class="tabular-nums">{{ view.year }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ view.runtimeLabel }}</span>
                        <template v-if="view.certification">
                            <span aria-hidden="true">·</span>
                            <span
                                class="rounded border border-white/40 px-1.5 text-xs"
                                >{{ view.certification }}</span
                            >
                        </template>
                        <span aria-hidden="true">·</span>
                        <span>{{ view.genres.join(', ') }}</span>
                    </p>
                    <DetailRatings look="inline" on-image class="mt-4" />
                    <DetailActions layout="hero" class="mt-6" />
                    <AvailabilitySummary
                        v-if="watch === 'hero-summary'"
                        class="mt-5"
                    />
                </div>
            </div>
        </section>

        <!-- Where to watch as a full-width band -->
        <div
            v-if="watch === 'band' && settings.state !== 'loading'"
            class="mx-auto max-w-7xl px-4 pt-2 pb-8 md:px-8"
        >
            <AvailabilityBlock
                look="columns"
                wide
                @change-country="$emit('changeCountry', $event)"
            />
        </div>

        <!-- Body -->
        <div
            class="mx-auto grid max-w-7xl gap-10 px-4 pt-4 md:px-8 lg:grid-cols-[minmax(0,1fr)_22rem]"
        >
            <div class="min-w-0 space-y-10">
                <section v-if="settings.state === 'loading'" class="space-y-3">
                    <SkeletonBlock class="h-5 w-1/3 rounded" />
                    <SkeletonBlock class="h-4 w-full rounded" />
                    <SkeletonBlock class="h-4 w-5/6 rounded" />
                    <SkeletonBlock class="aspect-video rounded-2xl" />
                </section>
                <template v-else>
                    <section aria-labelledby="story">
                        <h2 id="story" class="sr-only">Story</h2>
                        <p
                            v-if="view.tagline"
                            class="text-lg font-medium text-(--rp-muted) italic"
                        >
                            {{ view.tagline }}
                        </p>
                        <p class="mt-3 max-w-prose text-base leading-relaxed">
                            {{ view.overview }}
                        </p>
                    </section>

                    <AvailabilityBlock
                        v-if="afterStory"
                        :look="look"
                        @change-country="$emit('changeCountry', $event)"
                    />

                    <section
                        v-if="view.trailer !== null"
                        aria-labelledby="trailer"
                    >
                        <h2 id="trailer" class="mb-3 text-lg font-semibold">
                            Trailer
                        </h2>
                        <UnavailablePanel
                            v-if="view.trailer === undefined"
                            what="The trailer is"
                            aspect="video"
                        />
                        <TrailerPlayer
                            v-else
                            :trailer="view.trailer"
                            :mode="settings.trailer"
                            :poster-image="view.backdropUrl"
                            :title="view.title"
                        />
                    </section>

                    <section aria-labelledby="cast">
                        <h2 id="cast" class="mb-3 text-lg font-semibold">
                            Cast
                        </h2>
                        <UnavailablePanel
                            v-if="view.cast === undefined"
                            what="Cast and crew are"
                        />
                        <CastList
                            v-else
                            :cast="view.cast"
                            layout="grid"
                            :limit="12"
                        />
                    </section>
                </template>
            </div>

            <aside class="space-y-6 lg:sticky lg:top-6 lg:self-start">
                <SkeletonBlock
                    v-if="settings.state === 'loading'"
                    class="h-80 rounded-2xl"
                />
                <AvailabilityBlock
                    v-else-if="inRail"
                    :look="look"
                    @change-country="$emit('changeCountry', $event)"
                />
                <section
                    v-if="settings.state !== 'loading'"
                    aria-labelledby="details"
                    class="rounded-2xl bg-(--rp-surface) p-5 ring-1 ring-(--rp-line)"
                >
                    <h2 id="details" class="mb-4 text-lg font-semibold">
                        Details
                    </h2>
                    <DetailFacts />
                </section>
            </aside>
        </div>
    </DetailShell>
</template>
