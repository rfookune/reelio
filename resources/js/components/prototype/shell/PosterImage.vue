<script setup lang="ts">
/** PROTOTYPE, throwaway. Poster with the shared missing-poster placeholder. */
import { Film } from '@lucide/vue';
import { computed } from 'vue';
import type { MockMovie } from './mockMovies';
import { posterUrl } from './mockMovies';

const props = withDefaults(
    defineProps<{
        movie: MockMovie;
        size?: 'w185' | 'w342' | 'w500';
        compact?: boolean;
    }>(),
    { size: 'w342', compact: false },
);

const src = computed(() => posterUrl(props.movie, props.size));
</script>

<template>
    <div class="relative aspect-[2/3] w-full overflow-hidden bg-(--rp-raised)">
        <img
            v-if="src"
            :src="src"
            :alt="`Poster for ${movie.title}`"
            loading="lazy"
            decoding="async"
            class="absolute inset-0 size-full object-cover"
        />
        <div
            v-else
            role="img"
            :aria-label="`No poster available for ${movie.title}`"
            class="rp-poster-placeholder absolute inset-0 flex flex-col items-center justify-center gap-3 p-3 text-center text-(--rp-muted)"
        >
            <Film :class="compact ? 'size-5' : 'size-8'" stroke-width="1.5" />
            <span
                v-if="!compact"
                class="line-clamp-3 text-sm leading-snug font-medium text-balance"
            >
                {{ movie.title }}
            </span>
        </div>
    </div>
</template>
