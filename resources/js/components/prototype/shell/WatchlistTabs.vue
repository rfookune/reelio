<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Watchlist / Watched switch at the top of the Watchlist
 * screen: on mobile this is the only route to Watched (PRD §6.3).
 */
import { usePrototypeState } from './usePrototypeState';

const { state, go } = usePrototypeState();
</script>

<template>
    <div
        role="tablist"
        aria-label="Your movies"
        class="inline-flex rounded-lg bg-(--rp-surface) p-1 text-sm font-medium ring-1 ring-(--rp-line)"
    >
        <button
            v-for="tab in ['watchlist', 'watched'] as const"
            :key="tab"
            type="button"
            role="tab"
            :aria-selected="state.screen === tab"
            class="rounded-md px-3 py-1.5 capitalize"
            :class="
                state.screen === tab
                    ? 'bg-(--rp-raised) text-(--rp-text)'
                    : 'text-(--rp-muted) hover:text-(--rp-text)'
            "
            @click="go(tab)"
        >
            {{ tab }}
        </button>
    </div>
</template>
