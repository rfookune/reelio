<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. A one-line Availability summary for the hero, pointing at the
 * full block further down. Answers "can I stream it?" at a glance:
 * the best group available (Stream, else Free, else Rent/Buy), up to four logos, "+N".
 * Carries the country and a short JustWatch credit, since it displays Availability too.
 */
import { ArrowDown } from '@lucide/vue';
import { computed } from 'vue';
import { useDetailState } from './useDetailState';

const { view } = useDetailState();

const LOGO_BASE = 'https://image.tmdb.org/t/p/w92';

const summary = computed(() => {
    const data = view.value.availability.data;

    if (data.stream.length) {
        return { label: 'Stream on', entries: data.stream };
    }

    if (data.free.length) {
        return { label: 'Free (may include ads) on', entries: data.free };
    }

    const paid = [...data.rent, ...data.buy].filter(
        (entry, index, all) =>
            all.findIndex((other) => other.name === entry.name) === index,
    );

    if (paid.length) {
        return { label: 'Rent or buy on', entries: paid };
    }

    return null;
});

const shown = computed(() => summary.value?.entries.slice(0, 4) ?? []);
const more = computed(
    () => (summary.value?.entries.length ?? 0) - shown.value.length,
);
</script>

<template>
    <div
        class="inline-flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl bg-black/45 px-3 py-2 text-sm text-white ring-1 ring-white/15 backdrop-blur-md"
    >
        <template v-if="summary">
            <span class="text-white/80">{{ summary.label }}</span>
            <ul class="flex items-center gap-1.5">
                <li v-for="entry in shown" :key="entry.name">
                    <img
                        :src="`${LOGO_BASE}${entry.logo}`"
                        :alt="entry.name"
                        :title="entry.name"
                        class="size-7 rounded-md"
                    />
                </li>
                <li
                    v-if="more > 0"
                    class="flex h-7 items-center rounded-md bg-white/15 px-1.5 text-xs font-medium tabular-nums"
                >
                    +{{ more }}
                </li>
            </ul>
        </template>
        <span v-else class="text-white/80"
            >No streaming information for this movie</span
        >
        <span class="text-xs text-white/60"
            >in {{ view.availability.countryCode }} · JustWatch</span
        >
        <a
            href="#where-to-watch-block"
            class="inline-flex items-center gap-1 font-medium text-white underline-offset-2 hover:underline"
        >
            All options
            <ArrowDown class="size-3.5" />
        </a>
    </div>
</template>
