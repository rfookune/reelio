<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. The Availability block, per "Country and Availability presentation":
 * country always named with a Change control; groups Stream, Free (may include ads), Rent, Buy,
 * empty groups hidden; entries not clickable; one labelled TMDB watch-page link;
 * JustWatch credit + "not a guarantee" caveat + freshness on every block, empty state included.
 *
 * `density`: full (logo + name rows) or logos (logo grid, names on hover/aria only).
 */
import { AlertTriangle, ExternalLink, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import type { ProviderEntry } from './mockDetail';
import { countryNames, useDetailState } from './useDetailState';

withDefaults(
    defineProps<{ density?: 'full' | 'logos'; headingLevel?: 'h2' | 'h3' }>(),
    {
        density: 'full',
        headingLevel: 'h2',
    },
);

const emit = defineEmits<{ changeCountry: [code: string] }>();

const { view } = useDetailState();

const availability = computed(() => view.value.availability);

const groups = computed(() =>
    (
        [
            ['Stream', availability.value.data.stream],
            ['Free, may include ads', availability.value.data.free],
            ['Rent', availability.value.data.rent],
            ['Buy', availability.value.data.buy],
        ] as [string, ProviderEntry[]][]
    ).filter(([, entries]) => entries.length > 0),
);

const LOGO_BASE = 'https://image.tmdb.org/t/p/w92';
</script>

<template>
    <section
        aria-labelledby="where-to-watch"
        class="rounded-2xl bg-(--rp-surface) p-5 ring-1 ring-(--rp-line)"
    >
        <div
            class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
        >
            <component
                :is="headingLevel"
                id="where-to-watch"
                class="text-lg font-semibold"
            >
                Where to watch
            </component>
            <p class="flex items-center gap-1.5 text-sm text-(--rp-muted)">
                <MapPin class="size-3.5" />
                <label for="country-select">Showing</label>
                <select
                    id="country-select"
                    class="cursor-pointer rounded bg-transparent font-medium text-(--rp-accent) hover:underline"
                    :value="availability.countryCode"
                    @change="
                        emit(
                            'changeCountry',
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option
                        v-for="(name, code) in countryNames"
                        :key="code"
                        :value="code"
                        class="bg-(--rp-surface) text-(--rp-text)"
                    >
                        {{ name.replace(/^the /, '') }}
                    </option>
                </select>
            </p>
        </div>

        <p
            v-if="availability.unsupportedDetected"
            class="mt-3 rounded-lg bg-(--rp-raised) px-3 py-2 text-sm text-(--rp-text)"
        >
            Showing US availability.
            {{ availability.unsupportedDetected }} isn't supported yet.
        </p>

        <p
            v-if="availability.stale"
            class="mt-3 flex items-start gap-2 rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-700 ring-1 ring-amber-500/25 dark:text-amber-300"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            We couldn't refresh this recently, so it may be out of date.
        </p>

        <div v-if="groups.length" class="mt-4 space-y-4">
            <div v-for="[label, entries] in groups" :key="label">
                <h3
                    class="text-xs font-semibold tracking-wide text-(--rp-muted) uppercase"
                >
                    {{ label }}
                </h3>
                <ul
                    v-if="density === 'full'"
                    class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2"
                >
                    <li
                        v-for="entry in entries"
                        :key="entry.name"
                        class="flex items-center gap-3 text-sm"
                    >
                        <img
                            :src="`${LOGO_BASE}${entry.logo}`"
                            alt=""
                            loading="lazy"
                            class="size-8 rounded-md"
                        />
                        {{ entry.name }}
                    </li>
                </ul>
                <ul v-else class="mt-2 flex flex-wrap gap-2">
                    <li v-for="entry in entries" :key="entry.name">
                        <img
                            :src="`${LOGO_BASE}${entry.logo}`"
                            :alt="entry.name"
                            :title="entry.name"
                            loading="lazy"
                            class="size-10 rounded-lg"
                        />
                    </li>
                </ul>
            </div>
        </div>

        <p v-else class="mt-4 text-sm text-(--rp-text)">
            We don't have streaming information for this movie in
            {{ availability.countryName }}.
        </p>

        <a
            v-if="availability.data.link"
            :href="availability.data.link"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-5 inline-flex items-center gap-1.5 text-sm font-medium text-(--rp-accent) hover:underline"
        >
            See all watch options on TMDB
            <ExternalLink class="size-3.5" />
            <span class="sr-only">(opens in a new tab)</span>
        </a>

        <div
            class="mt-4 space-y-1 border-t border-(--rp-line) pt-3 text-xs text-(--rp-muted)"
        >
            <p>
                Availability changes often and may be incomplete. Not listed
                doesn't mean unavailable.
            </p>
            <p>
                Streaming data from JustWatch · Updated
                {{ availability.updatedAgo }}
            </p>
        </div>
    </section>
</template>
