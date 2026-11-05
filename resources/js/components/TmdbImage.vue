<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import MissingPoster from '@/components/MissingPoster.vue';
import { isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';

type ImageWidth = 92 | 154 | 185 | 342 | 500 | 780 | 1280;

const props = withDefaults(
    defineProps<{
        baseUrl: string;
        path: string | null;
        alt: string;
        widths?: ImageWidth[];
        sizes: string;
    }>(),
    { widths: () => [185, 342, 500] },
);

const failed = ref(false);
const validPath = computed(() => isSafeTmdbPath(props.path));
const imageUrl = (width: ImageWidth) =>
    tmdbImageUrl(props.baseUrl, width, props.path ?? '');
const srcset = computed(() =>
    props.widths.map((width) => `${imageUrl(width)} ${width}w`).join(', '),
);

watch(
    () => props.path,
    () => {
        failed.value = false;
    },
);
</script>

<template>
    <div data-slot="tmdb-image" class="aspect-[2/3] overflow-hidden bg-raised">
        <img
            v-if="validPath && !failed"
            :src="imageUrl(widths[0] ?? 342)"
            :srcset="srcset"
            :sizes="sizes"
            :alt="alt"
            width="342"
            height="513"
            loading="lazy"
            decoding="async"
            class="size-full object-cover"
            @error="failed = true"
        />
        <MissingPoster v-else :title="alt" />
    </div>
</template>
