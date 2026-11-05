<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. The trailer, in one of three modes (`?trailer=`):
 *
 * - facade: the TMDB backdrop (already loaded, already credited) with a play button.
 *   Nothing from YouTube/Google loads until the visitor presses play.
 * - embed: a `youtube-nocookie` iframe on page load. It fetches YouTube's player
 *   (several hundred KB of JS, requests to Google) for every visitor, played or not.
 * - disabled: simulates a video whose owner blocks embedding. TMDB doesn't say which
 *   videos are embeddable, so Reelio can't detect this in advance: the
 *   "Watch on YouTube" link has to be there always, not only on failure.
 *
 * YouTube's embed rules (TMDB API coverage ticket): ≥ 200×200 px, no overlays on the
 * playing video, and the Referer must be sent, hence `referrerpolicy` below.
 */
import { ExternalLink, Play } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { TrailerMode } from './useDetailState';

const props = defineProps<{
    trailer: { key: string; name: string; type: string };
    mode: TrailerMode;
    posterImage: string | null;
    title: string;
    rounded?: boolean;
}>();

const playing = ref(false);

watch(
    () => props.mode,
    () => {
        playing.value = false;
    },
);

const showIframe = computed(
    () => props.mode === 'embed' || (props.mode === 'facade' && playing.value),
);

const src = computed(
    () =>
        `https://www.youtube-nocookie.com/embed/${props.trailer.key}?rel=0${props.mode === 'facade' ? '&autoplay=1' : ''}`,
);
</script>

<template>
    <figure>
        <div
            class="relative aspect-video w-full min-w-[200px] overflow-hidden bg-black"
            :class="rounded !== false && 'rounded-2xl ring-1 ring-(--rp-line)'"
        >
            <iframe
                v-if="showIframe"
                :src="src"
                :title="`${title}: ${trailer.name} (YouTube)`"
                class="absolute inset-0 size-full"
                allow="
                    autoplay;
                    encrypted-media;
                    picture-in-picture;
                    fullscreen;
                "
                referrerpolicy="strict-origin-when-cross-origin"
                allowfullscreen
            />

            <div
                v-else-if="mode === 'disabled' && playing"
                class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-[#0f0f0f] p-6 text-center font-[Roboto,Arial,sans-serif] text-white"
            >
                <p class="text-[10px] tracking-widest text-white/40 uppercase">
                    Simulated YouTube player
                </p>
                <p class="text-lg">Video unavailable</p>
                <p class="max-w-sm text-sm text-white/70">
                    Playback on other websites has been disabled by the video
                    owner.
                </p>
                <span class="text-sm text-white underline"
                    >Watch on YouTube</span
                >
            </div>

            <button
                v-else
                type="button"
                class="group absolute inset-0 flex size-full items-center justify-center"
                :aria-label="`Play trailer for ${title}`"
                @click="playing = true"
            >
                <img
                    v-if="posterImage"
                    :src="posterImage"
                    alt=""
                    class="absolute inset-0 size-full object-cover opacity-80 transition-opacity group-hover:opacity-95"
                />
                <span
                    class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"
                />
                <span
                    class="relative flex size-16 items-center justify-center rounded-full bg-white/95 text-black shadow-xl transition-transform group-hover:scale-105"
                >
                    <Play class="ml-1 size-7 fill-current" />
                </span>
                <span
                    class="absolute bottom-3 left-4 text-sm font-medium text-white"
                >
                    {{ trailer.type }} · {{ trailer.name }}
                </span>
            </button>
        </div>

        <figcaption
            class="mt-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-(--rp-muted)"
        >
            <span v-if="mode !== 'embed'"
                >Plays from YouTube. YouTube may set cookies once you press
                play.</span
            >
            <span v-else>Embedded from YouTube.</span>
            <a
                :href="`https://www.youtube.com/watch?v=${trailer.key}`"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 font-medium text-(--rp-accent) hover:underline"
            >
                Watch on YouTube
                <ExternalLink class="size-3" />
                <span class="sr-only">(opens in a new tab)</span>
            </a>
        </figcaption>
    </figure>
</template>
