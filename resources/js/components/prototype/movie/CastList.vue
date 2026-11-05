<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Top-billed cast. `layout`: scroller (horizontal photo strip),
 * grid (photo grid), text (names and characters only, no images).
 */
import { UserRound } from '@lucide/vue';
import type { CastMember } from './mockDetail';

withDefaults(
    defineProps<{
        cast: CastMember[];
        layout?: 'scroller' | 'grid' | 'text';
        limit?: number;
    }>(),
    { layout: 'scroller', limit: 12 },
);

const PROFILE_BASE = 'https://image.tmdb.org/t/p/w185';
</script>

<template>
    <ul
        v-if="layout === 'text'"
        class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2"
    >
        <li
            v-for="member in cast.slice(0, limit)"
            :key="member.name"
            class="flex justify-between gap-3 border-b border-(--rp-line) pb-2"
        >
            <span class="font-medium">{{ member.name }}</span>
            <span class="truncate text-right text-(--rp-muted)">{{
                member.character
            }}</span>
        </li>
    </ul>

    <ul
        v-else
        :class="
            layout === 'scroller'
                ? '-mx-4 flex snap-x gap-4 overflow-x-auto px-4 pb-2 md:mx-0 md:px-0'
                : 'grid grid-cols-3 gap-4 sm:grid-cols-4 lg:grid-cols-6'
        "
    >
        <li
            v-for="member in cast.slice(0, limit)"
            :key="member.name"
            :class="layout === 'scroller' ? 'w-28 shrink-0 snap-start' : ''"
        >
            <div
                class="aspect-[2/3] overflow-hidden rounded-lg bg-(--rp-raised)"
            >
                <img
                    v-if="member.profilePath"
                    :src="`${PROFILE_BASE}${member.profilePath}`"
                    :alt="''"
                    loading="lazy"
                    class="size-full object-cover"
                />
                <div
                    v-else
                    class="flex size-full items-center justify-center text-(--rp-muted)"
                >
                    <UserRound class="size-8" stroke-width="1.5" />
                </div>
            </div>
            <p class="mt-2 text-sm leading-tight font-medium">
                {{ member.name }}
            </p>
            <p class="mt-0.5 text-xs leading-tight text-(--rp-muted)">
                {{ member.character }}
            </p>
        </li>
    </ul>
</template>
