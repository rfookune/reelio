<script setup lang="ts">
import { computed } from 'vue';
import ShimmerSkeleton from '@/components/ShimmerSkeleton.vue';
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
        /** The ratings are still being looked up: a placeholder shows in place of them. */
        pending?: boolean;
        /** Credits IMDb in short and smaller, for dense cards, without the URL it otherwise spells out. */
        compact?: boolean;
    }>(),
    { variant: 'all', pending: false, compact: false },
);

const shown = computed(() =>
    props.variant === 'single' ? props.ratings.slice(0, 1) : props.ratings,
);
const showsImdb = computed(() =>
    shown.value.some((rating) => rating.source === 'imdb'),
);
const sourceClass = {
    imdb: 'text-imdb',
    tmdb: 'text-tmdb',
} as const;
</script>

<template>
    <div data-slot="external-ratings" class="flex flex-col gap-1">
        <div v-if="pending" data-slot="ratings-pending" class="py-0.5">
            <ShimmerSkeleton class="h-4 w-28" />
        </div>
        <p
            v-else
            class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted"
        >
            <template v-if="shown.length > 0">
                <template v-for="(rating, index) in shown" :key="rating.source">
                    <template v-if="index > 0">
                        {{ ' ' }}
                        <span aria-hidden="true">·</span>
                        {{ ' ' }}
                    </template>
                    <span data-slot="external-rating">
                        <span
                            :class="[
                                sourceClass[rating.source],
                                'font-semibold',
                            ]"
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
        <p
            v-if="showsImdb && !pending && compact"
            data-slot="imdb-credit"
            class="text-[0.6875rem] leading-snug text-muted"
        >
            Information courtesy of
            <a
                href="https://www.imdb.com"
                target="_blank"
                rel="noopener noreferrer"
                class="underline-offset-4 hover:underline"
                >IMDb</a
            >. Used with permission.
        </p>
        <p
            v-else-if="showsImdb && !pending"
            data-slot="imdb-credit"
            class="text-xs text-muted"
        >
            Information courtesy of IMDb (<a
                href="https://www.imdb.com"
                target="_blank"
                rel="noopener noreferrer"
                class="underline-offset-4 hover:underline"
                >https://www.imdb.com</a
            >). Used with permission.
        </p>
    </div>
</template>
