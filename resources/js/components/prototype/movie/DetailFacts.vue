<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. The factual details: Director, writers, release date, runtime,
 * certification, genres, original title. Cache-only facts (Director, writers,
 * certification) show "Temporarily unavailable" when the Catalog source is down.
 */
import { computed } from 'vue';
import { useDetailState } from './useDetailState';

withDefaults(defineProps<{ columns?: 1 | 2 }>(), { columns: 1 });

const { view } = useDetailState();

const UNAVAILABLE = 'Temporarily unavailable';

const rows = computed(() => {
    const v = view.value;
    const list: [string, string][] = [];

    list.push([
        v.directors && v.directors.length > 1 ? 'Directors' : 'Director',
        v.directors === undefined ? UNAVAILABLE : v.directors.join(', '),
    ]);

    if (v.writers === undefined) {
        list.push(['Writers', UNAVAILABLE]);
    } else if (v.writers.length) {
        list.push(['Writers', v.writers.join(', ')]);
    }

    list.push(['Released (US)', v.releaseLabel]);
    list.push(['Runtime', v.runtimeLabel]);

    if (v.certification === undefined) {
        list.push(['Rated', UNAVAILABLE]);
    } else if (v.certification) {
        list.push(['Rated', v.certification]);
    }

    list.push(['Genres', v.genres.join(', ')]);

    if (v.originalTitle) {
        list.push(['Original title', v.originalTitle]);
    }

    return list;
});
</script>

<template>
    <dl
        class="grid gap-x-8 gap-y-3 text-sm"
        :class="columns === 2 ? 'sm:grid-cols-2' : 'grid-cols-1'"
    >
        <div v-for="[label, value] in rows" :key="label">
            <dt class="text-(--rp-muted)">{{ label }}</dt>
            <dd
                class="mt-0.5 font-medium"
                :class="
                    value === UNAVAILABLE &&
                    'font-normal text-(--rp-muted) italic'
                "
            >
                {{ value }}
            </dd>
        </div>
    </dl>
</template>
