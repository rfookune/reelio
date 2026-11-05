<script setup lang="ts">
/** PROTOTYPE, throwaway. Placeholder for screens this prototype doesn't build. */
import { computed } from 'vue';
import { usePrototypeState } from './usePrototypeState';

const { state, go } = usePrototypeState();

const copy = computed(
    () =>
        ({
            search: {
                title: 'Search',
                body: 'Search isn’t built in this prototype. It only judges the shell, the movie card, and loading states.',
            },
            watched: {
                title: 'Watched',
                body: 'You haven’t marked any movies as watched yet. Movies you mark as watched show up here with the date.',
            },
            profile: {
                title: 'Profile',
                body: 'Account settings stay on the starter kit’s settings pages: profile, security, passkeys, appearance.',
            },
        })[state.screen as 'search' | 'watched' | 'profile'],
);
</script>

<template>
    <section class="mx-auto max-w-xl px-4 py-24 text-center">
        <h1 class="text-2xl font-semibold tracking-tight">{{ copy.title }}</h1>
        <p class="mt-3 text-(--rp-muted)">{{ copy.body }}</p>
        <button
            type="button"
            class="mt-6 rounded-lg bg-(--rp-accent-fill) px-4 py-2 text-sm font-medium text-(--rp-on-accent)"
            @click="go('discover')"
        >
            Browse Discover
        </button>
    </section>
</template>
