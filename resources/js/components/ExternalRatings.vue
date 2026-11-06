<script setup lang="ts">
import { computed } from 'vue';
import { formatVoteCount } from '@/lib/tmdb';

export type ExternalRating = {
    source: 'imdb' | 'tmdb';
    name: string;
    value: number;
    voteCount: number;
};

const props = withDefaults(
    defineProps<{
        ratings: ExternalRating[];
        /** `all` lists every source; `single` shows only the first, as cards do. */
        variant?: 'all' | 'single';
    }>(),
    { variant: 'all' },
);

const shown = computed(() =>
    props.variant === 'single' ? props.ratings.slice(0, 1) : props.ratings,
);
const sourceClass = {
    imdb: 'text-imdb',
    tmdb: 'text-tmdb',
} as const;
</script>

<template>
    <p
        data-slot="external-ratings"
        class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted"
    >
        <template v-if="shown.length > 0">
            <template v-for="(rating, index) in shown" :key="rating.source">
                <span v-if="index > 0" aria-hidden="true">·</span>
                <span data-slot="external-rating">
                    <span
                        :class="[sourceClass[rating.source], 'font-semibold']"
                    >
                        {{ rating.name }}
                    </span>
                    {{ ' ' }}
                    <span class="text-foreground">
                        {{ rating.value.toFixed(1) }}
                    </span>
                    {{ ' ' }}
                    <span>{{ formatVoteCount(rating.voteCount) }}</span>
                </span>
            </template>
        </template>
        <span v-else>Not yet rated</span>
    </p>
</template>
