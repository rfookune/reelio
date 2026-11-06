<script setup lang="ts">
import { Play } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';

export type Trailer = {
    key: string;
    name: string;
    type: string;
};

const props = defineProps<{
    imageBaseUrl: string;
    title: string;
    trailer: Trailer;
    coverPath: string | null;
    /** Keeps the "Trailer" heading for screen readers only, where a dialog title already names it. */
    hideHeading?: boolean;
}>();

const playing = ref(false);
const frame = ref<HTMLIFrameElement | null>(null);
const validKey = computed(() => /^[A-Za-z0-9_-]{11}$/.test(props.trailer.key));
const embedUrl = computed(
    () =>
        `https://www.youtube-nocookie.com/embed/${props.trailer.key}?rel=0&autoplay=1`,
);
const watchUrl = computed(
    () => `https://www.youtube.com/watch?v=${props.trailer.key}`,
);

async function play(): Promise<void> {
    playing.value = true;
    await nextTick();
    frame.value?.focus();
}

const hasCover = computed(() => isSafeTmdbPath(props.coverPath));
</script>

<template>
    <section
        v-if="validKey"
        data-slot="trailer"
        aria-labelledby="trailer-heading"
    >
        <h2
            id="trailer-heading"
            :class="hideHeading ? 'sr-only' : 'mb-4 text-xl font-semibold'"
        >
            Trailer
        </h2>

        <div
            class="relative aspect-video min-h-[200px] w-full overflow-hidden rounded-xl bg-raised"
        >
            <iframe
                v-if="playing"
                ref="frame"
                :src="embedUrl"
                :title="`Trailer: ${title}`"
                referrerpolicy="strict-origin-when-cross-origin"
                allow="autoplay; fullscreen; encrypted-media"
                allowfullscreen
                class="absolute inset-0 size-full border-0"
            ></iframe>
            <template v-else>
                <img
                    v-if="hasCover"
                    :src="tmdbImageUrl(imageBaseUrl, 780, coverPath ?? '')"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="absolute inset-0 size-full object-cover"
                />
                <button
                    type="button"
                    :aria-label="`Play trailer: ${title}`"
                    class="group absolute inset-0 flex flex-col items-center justify-center gap-3 bg-black/40 text-white transition-colors hover:bg-black/50"
                    @click="play"
                >
                    <span
                        class="flex size-16 items-center justify-center rounded-full bg-accent-fill text-accent-fill-foreground shadow-lg transition-transform group-hover:scale-105"
                    >
                        <Play class="ml-1 size-7 fill-current" />
                    </span>
                    <span class="px-4 text-center text-sm font-medium">
                        {{ trailer.type }} · {{ trailer.name }}
                    </span>
                </button>
            </template>
        </div>

        <p class="mt-3 text-sm text-muted">
            Plays from YouTube. YouTube may set cookies once you press play.
            <a
                :href="watchUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="ml-1 text-accent underline-offset-4 hover:underline"
            >
                Watch on YouTube
            </a>
        </p>
    </section>
</template>
