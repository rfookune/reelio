<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Variant A: "Backdrop hero + side rail".
 *
 * Continues Discover's language: a full-bleed backdrop hero with the poster, title block,
 * ratings and actions over it. Below, a two-column body: story, trailer and cast on the
 * left; a right column with Details.
 * LCP element: the hero backdrop (w1280). No backdrop → a blurred poster stands in.
 *
 * Iteration 2: cast is variant C's photo grid.
 * Iteration 3: the hero dissolves into the page. The backdrop fades out over a reserved
 * band below the title block (so white hero text never sits on the faded part), and a
 * blurred glow of the same backdrop bleeds behind the top of the body, as on Discover.
 * Two Where to watch finalists (`watch`):
 * - summary-last:      hero summary strip; the full block is the last item, under Cast.
 * - details-then-tabs: tabs block in the right column under Details on desktop;
 *                      straight after the story on mobile.
 * Iteration 4: the right column sticks while the left scrolls. If it is taller than the
 * viewport, it scrolls with the page until its bottom is in view and sticks there, so
 * nothing in it is ever out of reach (a negative `top` computed from its height).
 */
import { useElementSize, useMediaQuery, useWindowSize } from '@vueuse/core';
import { computed, ref } from 'vue';
import SkeletonBlock from '../shell/SkeletonBlock.vue';
import AvailabilityBlock from './AvailabilityBlock.vue';
import AvailabilitySummary from './AvailabilitySummary.vue';
import CastList from './CastList.vue';
import DetailActions from './DetailActions.vue';
import DetailFacts from './DetailFacts.vue';
import DetailRatings from './DetailRatings.vue';
import DetailShell from './DetailShell.vue';
import TrailerPlayer from './TrailerPlayer.vue';
import UnavailablePanel from './UnavailablePanel.vue';
import { useDetailState } from './useDetailState';

export type WatchDesign = 'summary-last' | 'details-then-tabs';

const props = withDefaults(defineProps<{ watch?: WatchDesign }>(), {
    watch: 'summary-last',
});

defineEmits<{ changeCountry: [code: string] }>();

const { settings, view } = useDetailState();

const isDesktop = useMediaQuery('(min-width: 1024px)');

const isLoading = computed(() => settings.state === 'loading');
const heroImage = computed(
    () => view.value.backdropUrl ?? view.value.posterUrl,
);

const tabsInRail = computed(
    () => props.watch === 'details-then-tabs' && isDesktop.value,
);
const tabsAfterStory = computed(
    () => props.watch === 'details-then-tabs' && !isDesktop.value,
);

/** Gap kept between the sticky column and the viewport edge, in px (1.5rem). */
const STICKY_GAP = 24;

const rail = ref<HTMLElement | null>(null);
const { height: railHeight } = useElementSize(rail, undefined, {
    box: 'border-box',
});
const { height: viewportHeight } = useWindowSize();

const railTop = computed(
    () =>
        `${Math.min(STICKY_GAP, viewportHeight.value - railHeight.value - STICKY_GAP)}px`,
);
</script>

<template>
    <DetailShell overlay>
        <!-- Glow: the backdrop's light bleeding down behind the body (as on Discover). -->
        <div
            v-if="heroImage && !isLoading"
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 h-[64rem] overflow-hidden"
        >
            <img
                :src="heroImage"
                alt=""
                class="size-full scale-110 object-cover opacity-40 blur-3xl"
            />
            <div
                class="absolute inset-0 bg-gradient-to-b from-transparent via-(--rp-bg)/70 to-(--rp-bg)"
            />
        </div>

        <!-- Hero -->
        <section class="relative isolate" aria-labelledby="movie-title">
            <div class="hero-fade absolute inset-0 -z-10 overflow-hidden">
                <SkeletonBlock v-if="isLoading" class="size-full" />
                <template v-else>
                    <img
                        v-if="view.backdropUrl"
                        :src="view.backdropUrl"
                        alt=""
                        fetchpriority="high"
                        class="size-full object-cover"
                    />
                    <img
                        v-else-if="view.posterUrl"
                        :src="view.posterUrl"
                        alt=""
                        class="size-full scale-125 object-cover opacity-60 blur-2xl"
                    />
                </template>
                <div
                    class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/45 to-black/35"
                />
            </div>

            <div
                class="hero-content mx-auto flex max-w-7xl flex-col gap-6 px-4 pt-24 text-white sm:flex-row sm:items-end md:px-8 md:pt-40 lg:pt-56"
            >
                <div
                    class="hidden w-40 shrink-0 overflow-hidden rounded-lg shadow-2xl ring-1 ring-white/20 sm:block lg:w-52"
                >
                    <SkeletonBlock v-if="isLoading" class="aspect-[2/3]" />
                    <img
                        v-else-if="view.posterUrl"
                        :src="view.posterUrl"
                        :alt="`Poster for ${view.title}`"
                        class="aspect-[2/3] w-full object-cover"
                    />
                </div>

                <div v-if="isLoading" class="flex-1 space-y-4">
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
                        v-if="watch === 'summary-last'"
                        class="mt-5"
                    />
                </div>
            </div>
        </section>

        <!-- Body: pulled up into the lower, mostly transparent part of the fade. -->
        <div
            class="relative mx-auto -mt-10 grid max-w-7xl gap-10 px-4 md:-mt-14 md:px-8 lg:grid-cols-[minmax(0,1fr)_22rem]"
        >
            <div class="min-w-0 space-y-10">
                <section v-if="isLoading" class="space-y-3">
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
                        v-if="tabsAfterStory"
                        look="tabs"
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

                    <AvailabilityBlock
                        v-if="watch === 'summary-last'"
                        look="list"
                        @change-country="$emit('changeCountry', $event)"
                    />
                </template>
            </div>

            <!-- Sticky right column; see iteration 4 in the header comment. -->
            <aside
                ref="rail"
                class="space-y-6 lg:sticky lg:self-start"
                :style="{ top: railTop }"
            >
                <SkeletonBlock v-if="isLoading" class="h-80 rounded-2xl" />
                <template v-else>
                    <section
                        aria-labelledby="details"
                        class="rounded-2xl bg-(--rp-surface) p-5 ring-1 ring-(--rp-line)"
                    >
                        <h2 id="details" class="mb-4 text-lg font-semibold">
                            Details
                        </h2>
                        <DetailFacts />
                    </section>
                    <AvailabilityBlock
                        v-if="tabsInRail"
                        look="tabs"
                        @change-country="$emit('changeCountry', $event)"
                    />
                </template>
            </aside>
        </div>
    </DetailShell>
</template>

<style scoped>
/* PROTOTYPE, throwaway. The backdrop fades out over a band reserved below the hero text. */
.hero-content {
    padding-bottom: var(--fade);
}

.hero-fade,
.hero-content {
    --fade: 7rem;
}

.hero-fade {
    mask-image: linear-gradient(
        to bottom,
        #000 calc(100% - var(--fade)),
        transparent
    );
}

@media (min-width: 768px) {
    .hero-fade,
    .hero-content {
        --fade: 10rem;
    }
}
</style>
