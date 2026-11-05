<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. The chosen app shell (visual language ticket, variant C) around
 * the detail page: top bar with pill nav, theme toggle and avatar (or Sign in for guests),
 * mobile bottom nav, TMDB footer. Nav items are inert. `overlay` floats the top bar over
 * a full-bleed hero.
 */
import { Moon, Sun } from '@lucide/vue';
import type { Ref } from 'vue';
import { inject } from 'vue';
import MobileBottomNav from '../shell/MobileBottomNav.vue';
import TmdbAttribution from '../shell/TmdbAttribution.vue';
import Wordmark from '../shell/Wordmark.vue';
import { useDetailState } from './useDetailState';

defineProps<{ overlay?: boolean }>();

const theme = inject<Ref<string>>('rpTheme');
const toggleTheme = inject<() => void>('rpToggleTheme');
const { settings } = useDetailState();

const nav = ['Discover', 'Search', 'Watchlist', 'Watched'];
</script>

<template>
    <div class="relative min-h-svh bg-(--rp-bg) text-(--rp-text)">
        <header
            class="z-30"
            :class="overlay ? 'absolute inset-x-0 top-0' : 'relative'"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 md:px-8"
                :class="overlay && '[--rp-text:white]'"
            >
                <a href="#" aria-label="Home"><Wordmark /></a>
                <nav
                    aria-label="Primary"
                    class="mx-auto hidden rounded-full p-1 ring-1 backdrop-blur-md md:flex"
                    :class="
                        overlay
                            ? 'bg-black/30 ring-white/15'
                            : 'bg-(--rp-surface)/70 ring-(--rp-line)'
                    "
                >
                    <a
                        v-for="item in nav"
                        :key="item"
                        href="#"
                        class="rounded-full px-4 py-1.5 text-sm font-medium"
                        :class="
                            overlay
                                ? 'text-white/75 hover:text-white'
                                : 'text-(--rp-muted) hover:text-(--rp-text)'
                        "
                        >{{ item }}</a
                    >
                </nav>
                <button
                    type="button"
                    class="ml-auto flex size-9 items-center justify-center rounded-full md:ml-0"
                    :class="
                        overlay
                            ? 'text-white/80 hover:bg-white/10'
                            : 'text-(--rp-muted) hover:bg-(--rp-raised) hover:text-(--rp-text)'
                    "
                    :aria-label="
                        theme === 'dark'
                            ? 'Switch to light theme'
                            : 'Switch to dark theme'
                    "
                    @click="toggleTheme?.()"
                >
                    <Sun v-if="theme === 'dark'" class="size-4.5" />
                    <Moon v-else class="size-4.5" />
                </button>
                <a
                    v-if="settings.auth === 'guest'"
                    href="#"
                    class="hidden h-9 items-center rounded-full bg-(--rp-accent-fill) px-4 text-sm font-semibold text-(--rp-on-accent) md:flex"
                    >Sign in</a
                >
                <span
                    v-else
                    class="hidden size-9 items-center justify-center rounded-full bg-(--rp-raised) text-sm font-semibold text-(--rp-text) ring-1 ring-(--rp-line) md:flex"
                    aria-label="Profile"
                    >JD</span
                >
            </div>
        </header>

        <main>
            <slot />
        </main>

        <TmdbAttribution />
        <MobileBottomNav />
    </div>
</template>
