<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Variant C: "Trailer first, one column".
 *
 * The trailer is the hero: a 16:9 player at the top of the page (a still backdrop when
 * there is no trailer). One centred reading column follows, with a sticky in-page section
 * bar (Overview, Where to watch, Cast, Details) that also carries the title and a
 * compact Watchlist toggle once the title block has scrolled away.
 * LCP element: the trailer facade's backdrop (w1280), or the iframe area under ?trailer=embed.
 */
import { Bookmark, BookmarkCheck } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
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

const { settings, view, act, isOnWatchlist } = useDetailState();

const sections = [
    { id: 'overview', label: 'Overview' },
    { id: 'watch', label: 'Where to watch' },
    { id: 'cast', label: 'Cast' },
    { id: 'details', label: 'Details' },
];

const titleBlock = ref<HTMLElement | null>(null);
const titleGone = ref(false);
let observer: IntersectionObserver | null = null;

onMounted(() => {
    observer = new IntersectionObserver(([entry]) => {
        titleGone.value = !entry.isIntersecting;
    });

    if (titleBlock.value) {
        observer.observe(titleBlock.value);
    }
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <DetailShell>
        <div class="mx-auto max-w-5xl px-0 md:px-8">
            <SkeletonBlock
                v-if="settings.state === 'loading'"
                class="aspect-video md:rounded-2xl"
            />
            <UnavailablePanel
                v-else-if="view.trailer === undefined"
                what="The trailer is"
                aspect="video"
            />
            <TrailerPlayer
                v-else-if="view.trailer"
                :trailer="view.trailer"
                :mode="settings.trailer"
                :poster-image="view.backdropUrl"
                :title="view.title"
                class="[&_figcaption]:px-4 md:[&_figcaption]:px-0"
            />
            <img
                v-else-if="view.backdropUrl"
                :src="view.backdropUrl"
                alt=""
                class="aspect-video w-full object-cover md:rounded-2xl"
            />
            <div
                v-else
                class="flex aspect-[3/1] items-center justify-center overflow-hidden bg-(--rp-raised) md:rounded-2xl"
            >
                <img
                    v-if="view.posterUrl"
                    :src="view.posterUrl"
                    alt=""
                    class="size-full scale-125 object-cover opacity-50 blur-2xl"
                />
            </div>
        </div>

        <div class="mx-auto max-w-3xl px-4 md:px-8">
            <div v-if="settings.state === 'loading'" class="mt-8 space-y-4">
                <SkeletonBlock class="h-10 w-2/3 rounded-lg" />
                <SkeletonBlock class="h-5 w-1/2 rounded" />
                <SkeletonBlock class="h-11 w-80 rounded-lg" />
                <SkeletonBlock class="h-4 w-full rounded" />
                <SkeletonBlock class="h-4 w-5/6 rounded" />
            </div>

            <template v-else>
                <div ref="titleBlock" class="mt-8 flex gap-5">
                    <img
                        v-if="view.posterUrl"
                        :src="view.posterUrl"
                        :alt="`Poster for ${view.title}`"
                        class="aspect-[2/3] w-20 shrink-0 self-start rounded-md object-cover ring-1 ring-(--rp-line) sm:w-28"
                    />
                    <div class="min-w-0">
                        <h1
                            class="text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl"
                        >
                            {{ view.title }}
                        </h1>
                        <p
                            v-if="view.originalTitle"
                            class="mt-1 text-sm text-(--rp-muted)"
                        >
                            Original title: {{ view.originalTitle }}
                        </p>
                        <p
                            class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-(--rp-muted)"
                        >
                            <span class="tabular-nums">{{ view.year }}</span>
                            <span>{{ view.runtimeLabel }}</span>
                            <span
                                v-if="view.certification"
                                class="rounded border border-(--rp-line) px-1.5 text-xs"
                                >{{ view.certification }}</span
                            >
                            <span>{{ view.genres.join(', ') }}</span>
                        </p>
                        <DetailRatings look="inline" class="mt-3" />
                    </div>
                </div>

                <DetailActions layout="row" class="mt-6" />

                <!-- Sticky section bar -->
                <nav
                    aria-label="On this page"
                    class="sticky top-0 z-20 -mx-4 mt-8 flex items-center gap-1 overflow-x-auto border-b border-(--rp-line) bg-(--rp-bg)/90 px-4 backdrop-blur-md md:mx-0 md:px-0"
                >
                    <span
                        v-if="titleGone"
                        class="mr-2 hidden shrink-0 truncate text-sm font-semibold sm:block sm:max-w-48"
                        >{{ view.title }}</span
                    >
                    <a
                        v-for="section in sections"
                        :key="section.id"
                        :href="`#${section.id}`"
                        class="shrink-0 border-b-2 border-transparent px-3 py-3 text-sm font-medium whitespace-nowrap text-(--rp-muted) hover:border-(--rp-accent) hover:text-(--rp-text)"
                        >{{ section.label }}</a
                    >
                    <button
                        v-if="titleGone"
                        type="button"
                        class="ml-auto flex size-9 shrink-0 items-center justify-center rounded-full"
                        :class="
                            isOnWatchlist
                                ? 'bg-(--rp-accent-fill) text-(--rp-on-accent)'
                                : 'text-(--rp-muted) ring-1 ring-(--rp-line) hover:text-(--rp-text)'
                        "
                        :aria-pressed="isOnWatchlist"
                        :aria-label="
                            isOnWatchlist
                                ? 'Remove from Watchlist'
                                : 'Add to Watchlist'
                        "
                        @click="act(isOnWatchlist ? 'remove' : 'add')"
                    >
                        <BookmarkCheck v-if="isOnWatchlist" class="size-4" />
                        <Bookmark v-else class="size-4" />
                    </button>
                </nav>

                <section id="overview" class="scroll-mt-14 pt-8">
                    <h2 class="sr-only">Overview</h2>
                    <p
                        v-if="view.tagline"
                        class="text-lg font-medium text-(--rp-muted) italic"
                    >
                        {{ view.tagline }}
                    </p>
                    <p class="mt-2 text-lg leading-relaxed">
                        {{ view.overview }}
                    </p>
                    <p v-if="view.directors" class="mt-4 text-sm">
                        Directed by
                        <span class="font-semibold">{{
                            view.directors.join(', ')
                        }}</span>
                    </p>
                </section>

                <div id="watch" class="scroll-mt-14 pt-10">
                    <AvailabilityBlock
                        density="full"
                        @change-country="$emit('changeCountry', $event)"
                    />
                </div>

                <section id="cast" class="scroll-mt-14 pt-10">
                    <h2 class="mb-4 text-lg font-semibold">Cast</h2>
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

                <section id="details" class="scroll-mt-14 pt-10">
                    <h2 class="mb-4 text-lg font-semibold">Details</h2>
                    <DetailFacts :columns="2" />
                </section>
            </template>
        </div>
    </DetailShell>
</template>
