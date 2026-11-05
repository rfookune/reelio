<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Both External ratings with vote counts (FR-004) and the
 * IMDb credit the IMDb permission ticket asks for. A missing rating is simply
 * absent; the IMDb rating must be removable without breaking the layout.
 *
 * `look`: tiles (two side-by-side panels), inline (one line, e.g. in a hero), list (stacked rows).
 */
import { computed } from 'vue';
import { useDetailState } from './useDetailState';

withDefaults(
    defineProps<{ look?: 'tiles' | 'inline' | 'list'; onImage?: boolean }>(),
    { look: 'tiles', onImage: false },
);

const { view } = useDetailState();

const ratings = computed(() => view.value.ratings);
const hasImdb = computed(() => ratings.value.some((r) => r.source === 'IMDb'));

function votes(count: number): string {
    return new Intl.NumberFormat('en', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(count);
}
</script>

<template>
    <div>
        <p
            v-if="ratings.length === 0"
            class="text-sm"
            :class="onImage ? 'text-white/75' : 'text-(--rp-muted)'"
        >
            Not yet rated
        </p>

        <ul
            v-else-if="look === 'tiles'"
            class="grid grid-cols-2 gap-3"
            :class="ratings.length === 1 && 'grid-cols-1 sm:max-w-[50%]'"
        >
            <li
                v-for="rating in ratings"
                :key="rating.source"
                class="rounded-xl bg-(--rp-surface) p-4 ring-1 ring-(--rp-line)"
            >
                <p
                    class="text-xs font-semibold tracking-wide uppercase"
                    :class="
                        rating.source === 'IMDb'
                            ? 'text-(--rp-imdb)'
                            : 'text-(--rp-tmdb)'
                    "
                >
                    {{ rating.source }}
                </p>
                <p class="mt-1 flex items-baseline gap-1 tabular-nums">
                    <span class="text-3xl font-semibold">{{
                        rating.value.toFixed(1)
                    }}</span>
                    <span class="text-sm text-(--rp-muted)">/ 10</span>
                </p>
                <p class="mt-1 text-xs text-(--rp-muted) tabular-nums">
                    {{ votes(rating.votes) }} votes
                </p>
            </li>
        </ul>

        <ul
            v-else-if="look === 'inline'"
            class="flex flex-wrap items-baseline gap-x-5 gap-y-1"
        >
            <li
                v-for="rating in ratings"
                :key="rating.source"
                class="flex items-baseline gap-1.5 tabular-nums"
                :aria-label="`${rating.source} rating ${rating.value.toFixed(1)} out of 10 from ${votes(rating.votes)} votes`"
            >
                <span
                    class="text-sm font-semibold"
                    :class="
                        rating.source === 'IMDb'
                            ? onImage
                                ? 'text-[#f5c518]'
                                : 'text-(--rp-imdb)'
                            : onImage
                              ? 'text-[#5fd4f5]'
                              : 'text-(--rp-tmdb)'
                    "
                    >{{ rating.source }}</span
                >
                <span class="text-lg font-semibold">{{
                    rating.value.toFixed(1)
                }}</span>
                <span
                    class="text-xs"
                    :class="onImage ? 'text-white/70' : 'text-(--rp-muted)'"
                    >{{ votes(rating.votes) }}</span
                >
            </li>
        </ul>

        <dl v-else class="divide-y divide-(--rp-line)">
            <div
                v-for="rating in ratings"
                :key="rating.source"
                class="flex items-baseline justify-between py-2 tabular-nums"
            >
                <dt
                    class="text-sm font-semibold"
                    :class="
                        rating.source === 'IMDb'
                            ? 'text-(--rp-imdb)'
                            : 'text-(--rp-tmdb)'
                    "
                >
                    {{ rating.source }}
                </dt>
                <dd class="flex items-baseline gap-2">
                    <span class="text-lg font-semibold">{{
                        rating.value.toFixed(1)
                    }}</span>
                    <span class="text-xs text-(--rp-muted)"
                        >{{ votes(rating.votes) }} votes</span
                    >
                </dd>
            </div>
        </dl>

        <p
            v-if="hasImdb"
            class="mt-2 text-xs"
            :class="onImage ? 'text-white/60' : 'text-(--rp-muted)'"
        >
            IMDb rating: information courtesy of
            <a
                href="https://www.imdb.com"
                target="_blank"
                rel="noopener noreferrer"
                class="underline underline-offset-2"
                >IMDb</a
            >. Used with permission.
        </p>
    </div>
</template>
