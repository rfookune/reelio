<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Ticket: "Visual language and app shell" (Reelio MVP map).
 *
 * Three structurally different shells + movie cards, switchable via ?variant=A|B|C,
 * with orthogonal toggles so the cosmetic choices can be judged apart from layout:
 *   ?theme=dark|light  ?palette=navy|charcoal  ?font=inter|geist
 *   ?loading=off|pulse|shimmer
 * All data is mock (see components/prototype/shell/mockMovies.ts); nothing persists.
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, provide, watchEffect } from 'vue';
import PrototypeSwitcher from '@/components/prototype/PrototypeSwitcher.vue';
import type { SkeletonStyle } from '@/components/prototype/shell/usePrototypeState';
import VariantA from '@/components/prototype/shell/VariantA.vue';
import VariantB from '@/components/prototype/shell/VariantB.vue';
import VariantC from '@/components/prototype/shell/VariantC.vue';
import { Toaster } from '@/components/ui/sonner';

const variants = [
    { key: 'A', name: 'Top bar + poster shelves' },
    { key: 'B', name: 'Starter-kit sidebar + grid' },
    { key: 'C', name: 'Backdrop hero + list rows' },
];

const toggles = {
    theme: ['dark', 'light'],
    palette: ['navy', 'charcoal'],
    font: ['inter', 'geist'],
    loading: ['off', 'pulse', 'shimmer'],
} as const;

type ToggleKey = keyof typeof toggles;

const page = usePage();

const query = computed(() => new URLSearchParams(page.url.split('?')[1] ?? ''));

function read(key: string, allowed: readonly string[]): string {
    const value = query.value.get(key) ?? allowed[0];

    return allowed.includes(value) ? value : allowed[0];
}

const variant = computed(() =>
    read(
        'variant',
        variants.map((v) => v.key),
    ),
);
const settings = computed(() => ({
    theme: read('theme', toggles.theme),
    palette: read('palette', toggles.palette),
    font: read('font', toggles.font),
    loading: read('loading', toggles.loading) as SkeletonStyle,
}));

provide(
    'rpSkeletonStyle',
    computed(() => settings.value.loading),
);

function setParam(key: string, value: string): void {
    const params = new URLSearchParams(query.value);
    params.set(key, value);
    router.replace({
        url: `${page.url.split('?')[0]}?${params.toString()}`,
        preserveState: true,
        preserveScroll: true,
    });
}

function cycle(key: ToggleKey): void {
    const options = toggles[key] as readonly string[];
    const current = options.indexOf(settings.value[key]);
    setParam(key, options[(current + 1) % options.length]);
}

watchEffect(() => {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    root.dataset.rp = '';
    root.dataset.rpTheme = settings.value.theme;
    root.dataset.rpPalette = settings.value.palette;
    root.dataset.rpFont = settings.value.font;
    root.classList.toggle('dark', settings.value.theme === 'dark');
});

onBeforeUnmount(() => {
    const root = document.documentElement;
    delete root.dataset.rp;
    delete root.dataset.rpTheme;
    delete root.dataset.rpPalette;
    delete root.dataset.rpFont;
});
</script>

<template>
    <Head title="Prototype: visual language and shell">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link
            rel="stylesheet"
            href="https://fonts.bunny.net/css?family=inter:400,500,600,700|geist:400,500,600,700"
        />
        <link rel="preconnect" href="https://image.tmdb.org" />
    </Head>

    <VariantA v-if="variant === 'A'" :loading="settings.loading !== 'off'" />
    <VariantB
        v-else-if="variant === 'B'"
        :loading="settings.loading !== 'off'"
    />
    <VariantC v-else :loading="settings.loading !== 'off'" />

    <Toaster
        :theme="settings.theme === 'dark' ? 'dark' : 'light'"
        position="top-center"
    />

    <PrototypeSwitcher
        :variants="variants"
        :current="variant"
        @change="(key) => setParam('variant', key)"
    >
        <button
            v-for="key in Object.keys(toggles) as ToggleKey[]"
            :key="key"
            type="button"
            class="rounded-full bg-black/10 px-2 py-1 whitespace-nowrap hover:bg-black/20"
            @click="cycle(key)"
        >
            {{ key }}: <strong>{{ settings[key] }}</strong>
        </button>
    </PrototypeSwitcher>
</template>

<style>
/* PROTOTYPE, throwaway. Reelio candidate tokens, scoped to this page via html[data-rp]. */
html[data-rp] {
    --rp-accent-fill: #2860ee;
    --rp-on-accent: #ffffff;

    background-color: var(--rp-bg);
    color-scheme: dark;

    --background: var(--rp-bg);
    --foreground: var(--rp-text);
    --card: var(--rp-surface);
    --card-foreground: var(--rp-text);
    --popover: var(--rp-raised);
    --popover-foreground: var(--rp-text);
    --primary: var(--rp-accent-fill);
    --primary-foreground: var(--rp-on-accent);
    --secondary: var(--rp-raised);
    --secondary-foreground: var(--rp-text);
    --muted: var(--rp-raised);
    --muted-foreground: var(--rp-muted);
    --accent: var(--rp-raised);
    --accent-foreground: var(--rp-text);
    --border: var(--rp-line);
    --input: var(--rp-line);
    --ring: var(--rp-accent);
    --sidebar-background: var(--rp-sidebar);
    --sidebar: var(--rp-sidebar);
    --sidebar-foreground: var(--rp-text);
    --sidebar-primary: var(--rp-accent-fill);
    --sidebar-primary-foreground: var(--rp-on-accent);
    --sidebar-accent: var(--rp-raised);
    --sidebar-accent-foreground: var(--rp-text);
    --sidebar-border: var(--rp-line);
    --sidebar-ring: var(--rp-accent);
    --radius: 0.625rem;
}

/* Dark, navy reading: deep blue-black ground, slate surfaces. */
html[data-rp][data-rp-theme='dark'][data-rp-palette='navy'] {
    --rp-bg: #0a1122;
    --rp-sidebar: #070c19;
    --rp-surface: #121b30;
    --rp-raised: #1a253f;
    --rp-line: #25314d;
    --rp-text: #e8edf7;
    --rp-muted: #97a3bc;
    --rp-accent: #4d8dff;
    --rp-imdb: #f5c518;
    --rp-tmdb: #5fd4f5;
}

/* Dark, charcoal reading: neutral graphite, the blue lives only in the accent. */
html[data-rp][data-rp-theme='dark'][data-rp-palette='charcoal'] {
    --rp-bg: #101215;
    --rp-sidebar: #0b0c0f;
    --rp-surface: #181b20;
    --rp-raised: #20242b;
    --rp-line: #2c3139;
    --rp-text: #eceef2;
    --rp-muted: #9aa1ad;
    --rp-accent: #4d8dff;
    --rp-imdb: #f5c518;
    --rp-tmdb: #5fd4f5;
}

/* Light: cool paper, same accent hue darkened for AA text contrast. */
html[data-rp][data-rp-theme='light'] {
    color-scheme: light;
    --rp-bg: #f3f5fa;
    --rp-sidebar: #e9edf5;
    --rp-surface: #ffffff;
    --rp-raised: #e6ebf3;
    --rp-line: #d5dce8;
    --rp-text: #0c1428;
    --rp-muted: #4e5a73;
    --rp-accent: #1f5bdb;
    --rp-imdb: #7a5c00;
    --rp-tmdb: #00708f;
}

html[data-rp-theme='light'][data-rp-palette='charcoal'] {
    --rp-bg: #f4f4f5;
    --rp-sidebar: #ebebed;
    --rp-raised: #e8e8eb;
    --rp-line: #d9d9de;
    --rp-text: #111216;
    --rp-muted: #565964;
}

html[data-rp-font='inter'],
html[data-rp-font='inter'] body {
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    font-feature-settings: 'cv11', 'ss03';
}

html[data-rp-font='geist'],
html[data-rp-font='geist'] body {
    --font-sans: 'Geist', ui-sans-serif, system-ui, sans-serif;
    font-family: 'Geist', ui-sans-serif, system-ui, sans-serif;
}

html[data-rp] :focus-visible {
    outline: 2px solid var(--rp-accent);
    outline-offset: 2px;
}

.rp-poster-placeholder {
    background:
        radial-gradient(
            120% 80% at 50% 0%,
            color-mix(in oklab, var(--rp-accent) 10%, transparent),
            transparent 70%
        ),
        var(--rp-raised);
}

.rp-skel--pulse {
    animation: rp-pulse 1.6s ease-in-out infinite;
}

.rp-skel--shimmer {
    background-image: linear-gradient(
        100deg,
        transparent 30%,
        color-mix(in oklab, var(--rp-text) 9%, transparent) 50%,
        transparent 70%
    );
    background-size: 220% 100%;
    animation: rp-shimmer 1.5s linear infinite;
}

@keyframes rp-pulse {
    50% {
        opacity: 0.45;
    }
}

@keyframes rp-shimmer {
    from {
        background-position: 120% 0;
    }
    to {
        background-position: -120% 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .rp-skel--pulse,
    .rp-skel--shimmer {
        animation: none;
    }
}
</style>
