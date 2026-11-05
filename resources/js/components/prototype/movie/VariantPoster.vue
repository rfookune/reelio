<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Variant B: "Poster column, no hero".
 *
 * A reading page, closer to a film reference: no full-bleed image (the backdrop is only
 * a faint glow behind the top). A sticky left column holds the poster, the actions and
 * the ratings; the right column reads top to bottom with Where to watch promoted
 * directly under the story, then trailer, then cast as text.
 * Mobile: a small poster beside the title, and the actions pinned in a bar above the
 * bottom nav. LCP element: the poster (w500).
 */
import AvailabilityBlock from './AvailabilityBlock.vue';
import CastList from './CastList.vue';
import DetailActions from './DetailActions.vue';
import DetailFacts from './DetailFacts.vue';
import DetailRatings from './DetailRatings.vue';
import DetailShell from './DetailShell.vue';
import TrailerPlayer from './TrailerPlayer.vue';
import UnavailablePanel from './UnavailablePanel.vue';
import SkeletonBlock from '../shell/SkeletonBlock.vue';
import { useDetailState } from './useDetailState';

defineEmits<{ changeCountry: [code: string] }>();

const { settings, view } = useDetailState();
</script>

<template>
    <DetailShell>
        <div
            v-if="view.backdropUrl && settings.state !== 'loading'"
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 -z-0 h-[32rem] overflow-hidden"
        >
            <img
                :src="view.backdropUrl"
                alt=""
                class="size-full scale-110 object-cover opacity-25 blur-3xl"
            />
            <div
                class="absolute inset-0 bg-gradient-to-b from-transparent to-(--rp-bg)"
            />
        </div>

        <div
            class="relative mx-auto grid max-w-6xl gap-8 px-4 pt-6 pb-24 md:grid-cols-[16rem_minmax(0,1fr)] md:gap-12 md:px-8 md:pt-10 md:pb-0"
        >
            <!-- Left column (desktop) -->
            <aside
                class="hidden space-y-5 md:sticky md:top-6 md:block md:self-start"
            >
                <SkeletonBlock
                    v-if="settings.state === 'loading'"
                    class="aspect-[2/3] rounded-lg"
                />
                <img
                    v-else-if="view.posterUrl"
                    :src="view.posterUrl"
                    :alt="`Poster for ${view.title}`"
                    fetchpriority="high"
                    class="aspect-[2/3] w-full rounded-lg object-cover shadow-2xl ring-1 ring-(--rp-line)"
                />
                <template v-if="settings.state !== 'loading'">
                    <DetailActions layout="stack" />
                    <div
                        class="rounded-xl bg-(--rp-surface) px-4 py-2 ring-1 ring-(--rp-line)"
                    >
                        <DetailRatings look="list" />
                    </div>
                </template>
            </aside>

            <!-- Right column -->
            <div class="min-w-0">
                <div v-if="settings.state === 'loading'" class="space-y-4">
                    <SkeletonBlock class="h-10 w-2/3 rounded-lg" />
                    <SkeletonBlock class="h-5 w-1/2 rounded" />
                    <SkeletonBlock class="h-4 w-full rounded" />
                    <SkeletonBlock class="h-4 w-5/6 rounded" />
                    <SkeletonBlock class="mt-8 h-64 rounded-2xl" />
                </div>

                <template v-else>
                    <header class="flex gap-4">
                        <img
                            v-if="view.posterUrl"
                            :src="view.posterUrl"
                            :alt="`Poster for ${view.title}`"
                            class="aspect-[2/3] w-24 shrink-0 rounded-md object-cover ring-1 ring-(--rp-line) md:hidden"
                        />
                        <div class="min-w-0">
                            <h1
                                class="text-3xl leading-tight font-semibold tracking-tight text-balance md:text-5xl"
                            >
                                {{ view.title }}
                                <span
                                    class="font-normal text-(--rp-muted) tabular-nums"
                                    >({{ view.year }})</span
                                >
                            </h1>
                            <p
                                v-if="view.originalTitle"
                                class="mt-1 text-sm text-(--rp-muted)"
                            >
                                Original title: {{ view.originalTitle }}
                            </p>
                            <p
                                class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-(--rp-muted)"
                            >
                                <span>{{ view.runtimeLabel }}</span>
                                <span
                                    v-if="view.certification"
                                    class="rounded border border-(--rp-line) px-1.5 text-xs"
                                    >{{ view.certification }}</span
                                >
                                <span>{{ view.genres.join(', ') }}</span>
                            </p>
                            <p v-if="view.directors" class="mt-2 text-sm">
                                Directed by
                                <span class="font-semibold">{{
                                    view.directors.join(', ')
                                }}</span>
                            </p>
                        </div>
                    </header>

                    <div class="mt-5 md:hidden">
                        <DetailRatings look="inline" />
                    </div>

                    <section class="mt-8" aria-labelledby="story">
                        <h2 id="story" class="sr-only">Story</h2>
                        <p
                            v-if="view.tagline"
                            class="text-sm font-semibold tracking-wide text-(--rp-muted) uppercase"
                        >
                            {{ view.tagline }}
                        </p>
                        <p class="mt-2 max-w-prose text-lg leading-relaxed">
                            {{ view.overview }}
                        </p>
                    </section>

                    <div class="mt-10">
                        <AvailabilityBlock
                            look="list"
                            @change-country="$emit('changeCountry', $event)"
                        />
                    </div>

                    <section
                        v-if="view.trailer !== null"
                        class="mt-10"
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

                    <section class="mt-10" aria-labelledby="cast">
                        <h2 id="cast" class="mb-4 text-lg font-semibold">
                            Cast
                        </h2>
                        <UnavailablePanel
                            v-if="view.cast === undefined"
                            what="Cast and crew are"
                        />
                        <CastList
                            v-else
                            :cast="view.cast"
                            layout="text"
                            :limit="12"
                        />
                    </section>

                    <section class="mt-10" aria-labelledby="details">
                        <h2 id="details" class="mb-4 text-lg font-semibold">
                            Details
                        </h2>
                        <DetailFacts :columns="2" />
                    </section>
                </template>
            </div>
        </div>

        <!-- Mobile sticky action bar, above the bottom nav (h-16 + safe area). -->
        <div
            v-if="settings.state !== 'loading'"
            class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-30 border-t border-(--rp-line) bg-(--rp-bg)/95 px-4 py-2 backdrop-blur-md md:hidden"
        >
            <DetailActions layout="bar" />
        </div>
    </DetailShell>
</template>
