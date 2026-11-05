<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Ticket: "Movie detail page" (Reelio MVP map). Never merge.
 *
 * Three structurally different Movie pages inside the chosen shell, switchable via
 * ?variant=A|B|C (← / → keys), with orthogonal toggles so states are judged apart from layout:
 *   ?state=ok|loading|stale|unavailable|sparse|shell   (shell = dropped Movie, the 410 page)
 *   ?facts=none|watchlist|watched|both                  (the four lifecycle states)
 *   ?auth=user|guest  ?trailer=facade|embed|disabled  ?country=US|CA|unsupported
 *   ?imdb=on|off  ?theme=dark|light
 * Data: the real TMDB fixture for Interstellar (see components/prototype/movie/mockDetail.ts).
 * Nothing persists; actions only change in-memory state and show the toasts.
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, provide, watch, watchEffect } from 'vue';
import PrototypeSwitcher from '@/components/prototype/PrototypeSwitcher.vue';
import ShellGone from '@/components/prototype/movie/ShellGone.vue';
import type { ToggleKey } from '@/components/prototype/movie/useDetailState';
import {
    resetFacts,
    toggles,
    useDetailState,
} from '@/components/prototype/movie/useDetailState';
import VariantHero from '@/components/prototype/movie/VariantHero.vue';
import VariantPoster from '@/components/prototype/movie/VariantPoster.vue';
import VariantTrailer from '@/components/prototype/movie/VariantTrailer.vue';
import { Toaster } from '@/components/ui/sonner';

const variants = [
    { key: 'A', name: 'Backdrop hero + side rail' },
    { key: 'B', name: 'Poster column, no hero' },
    { key: 'C', name: 'Trailer first, one column' },
];

const page = usePage();
const { settings, view } = useDetailState();

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

watchEffect(() => {
    for (const key of Object.keys(toggles) as ToggleKey[]) {
        (settings as Record<string, string>)[key] = read(key, toggles[key]);
    }
});

watch(() => settings.facts, resetFacts, { immediate: true });

provide(
    'rpTheme',
    computed(() => settings.theme),
);
provide('rpToggleTheme', () => cycle('theme'));
provide(
    'rpSkeletonStyle',
    computed(() => 'shimmer'),
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
    const current = options.indexOf(settings[key]);
    setParam(key, options[(current + 1) % options.length]);
}

function changeCountry(code: string): void {
    setParam('country', code === 'CA' ? 'CA' : 'US');
}

watchEffect(() => {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    root.dataset.rp = '';
    root.dataset.rpTheme = settings.theme;
    root.dataset.rpPalette = 'charcoal';
    root.dataset.rpFont = 'inter';
    root.classList.toggle('dark', settings.theme === 'dark');
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
    <Head :title="`${view.title} (${view.year}) · Prototype`">
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link
            rel="stylesheet"
            href="https://fonts.bunny.net/css?family=inter:400,500,600,700"
        />
        <link rel="preconnect" href="https://image.tmdb.org" />
    </Head>

    <ShellGone v-if="settings.state === 'shell'" />
    <VariantHero v-else-if="variant === 'A'" @change-country="changeCountry" />
    <VariantPoster
        v-else-if="variant === 'B'"
        @change-country="changeCountry"
    />
    <VariantTrailer v-else @change-country="changeCountry" />

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
/* PROTOTYPE, throwaway. Copied from ShellPrototype.vue: the charcoal tokens, scoped via html[data-rp]. */
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
