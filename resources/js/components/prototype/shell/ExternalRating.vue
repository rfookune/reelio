<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. One External rating, always named with its Rating source.
 * `chip` is a filled pill; `inline` is bare text for dense rows.
 */
withDefaults(
    defineProps<{
        source: 'TMDB' | 'IMDb';
        value: number;
        look?: 'chip' | 'inline';
    }>(),
    { look: 'inline' },
);
</script>

<template>
    <span
        :class="[
            'inline-flex items-baseline gap-1 text-xs tabular-nums',
            look === 'chip'
                ? 'rounded-md bg-(--rp-raised) px-1.5 py-0.5 text-(--rp-text)'
                : 'text-(--rp-muted)',
        ]"
        :aria-label="`${source} rating ${value.toFixed(1)} out of 10`"
    >
        <span
            :class="
                source === 'IMDb'
                    ? 'font-semibold text-(--rp-imdb)'
                    : 'font-semibold text-(--rp-tmdb)'
            "
            >{{ source }}</span
        >
        <span :class="look === 'chip' ? 'font-medium' : 'text-(--rp-text)'">{{
            value.toFixed(1)
        }}</span>
    </span>
</template>
