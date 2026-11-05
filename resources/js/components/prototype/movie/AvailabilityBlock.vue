<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. The Availability block, per "Country and Availability presentation":
 * country always named with a Change control; groups Stream, Free (may include ads), Rent, Buy,
 * empty groups hidden; entries not clickable; one labelled TMDB watch-page link;
 * JustWatch credit + "not a guarantee" caveat + freshness on every block, empty state included.
 *
 * The frame (heading, country, notices, link, credit) is shared; the body has five looks:
 * - logos:    logo tiles per group (names only on hover / to screen readers)
 * - tabs:     one group at a time behind Stream / Free / Rent / Buy tabs with counts
 * - columns:  the groups side by side as columns (for a full-width band)
 * - services: one row per Streaming service, with the ways it offers the Movie as chips
 *             (merges e.g. Amazon Video under Rent and Buy into one row)
 * - list:     logo + name rows per group
 */
import { AlertTriangle, ExternalLink, MapPin } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { ProviderEntry } from './mockDetail';
import { countryNames, useDetailState } from './useDetailState';

export type AvailabilityLook =
    | 'logos'
    | 'tabs'
    | 'columns'
    | 'services'
    | 'list';

withDefaults(defineProps<{ look?: AvailabilityLook; wide?: boolean }>(), {
    look: 'list',
    wide: false,
});

const emit = defineEmits<{ changeCountry: [code: string] }>();

const { view } = useDetailState();

const availability = computed(() => view.value.availability);

type GroupKey = 'stream' | 'free' | 'rent' | 'buy';

const labels: Record<GroupKey, string> = {
    stream: 'Stream',
    free: 'Free, may include ads',
    rent: 'Rent',
    buy: 'Buy',
};

const shortLabels: Record<GroupKey, string> = {
    stream: 'Stream',
    free: 'Free',
    rent: 'Rent',
    buy: 'Buy',
};

const groups = computed(() =>
    (['stream', 'free', 'rent', 'buy'] as GroupKey[])
        .map((key) => ({
            key,
            label: labels[key],
            entries: availability.value.data[key] as ProviderEntry[],
        }))
        .filter((group) => group.entries.length > 0),
);

const activeTab = ref<GroupKey>('stream');

watch(
    groups,
    (list) => {
        if (!list.some((group) => group.key === activeTab.value)) {
            activeTab.value = list[0]?.key ?? 'stream';
        }
    },
    { immediate: true },
);

const activeGroup = computed(() =>
    groups.value.find((group) => group.key === activeTab.value),
);

const services = computed(() => {
    const byName = new Map<
        string,
        { name: string; logo: string; offers: GroupKey[] }
    >();

    for (const group of groups.value) {
        for (const entry of group.entries) {
            const row = byName.get(entry.name) ?? { ...entry, offers: [] };
            row.offers.push(group.key);
            byName.set(entry.name, row);
        }
    }

    return [...byName.values()];
});

const LOGO_BASE = 'https://image.tmdb.org/t/p/w92';

const chipClass: Record<GroupKey, string> = {
    stream: 'bg-(--rp-accent-fill) text-(--rp-on-accent)',
    free: 'bg-emerald-700 text-white',
    rent: 'bg-(--rp-raised) text-(--rp-text) ring-1 ring-(--rp-line)',
    buy: 'bg-(--rp-raised) text-(--rp-text) ring-1 ring-(--rp-line)',
};
</script>

<template>
    <section
        id="where-to-watch-block"
        aria-labelledby="where-to-watch"
        class="scroll-mt-6 rounded-2xl bg-(--rp-surface) ring-1 ring-(--rp-line)"
        :class="wide ? 'p-5 md:p-6' : 'p-5'"
    >
        <div
            class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
        >
            <h2 id="where-to-watch" class="text-lg font-semibold">
                Where to watch
            </h2>
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

        <p v-if="!groups.length" class="mt-4 text-sm text-(--rp-text)">
            We don't have streaming information for this movie in
            {{ availability.countryName }}.
        </p>

        <!-- logos -->
        <div v-else-if="look === 'logos'" class="mt-4 space-y-4">
            <div v-for="group in groups" :key="group.key">
                <h3
                    class="text-xs font-semibold tracking-wide text-(--rp-muted) uppercase"
                >
                    {{ group.label }}
                </h3>
                <ul class="mt-2 flex flex-wrap gap-2">
                    <li v-for="entry in group.entries" :key="entry.name">
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

        <!-- tabs -->
        <div v-else-if="look === 'tabs'" class="mt-4">
            <div
                role="tablist"
                aria-label="Ways to watch"
                class="grid rounded-lg bg-(--rp-raised) p-1"
                :style="{
                    gridTemplateColumns: `repeat(${groups.length}, minmax(0, 1fr))`,
                }"
            >
                <button
                    v-for="group in groups"
                    :id="`tab-${group.key}`"
                    :key="group.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === group.key"
                    :aria-controls="`panel-${group.key}`"
                    class="rounded-md px-2 py-1.5 text-sm font-medium"
                    :class="
                        activeTab === group.key
                            ? 'bg-(--rp-surface) text-(--rp-text) shadow-sm'
                            : 'text-(--rp-muted) hover:text-(--rp-text)'
                    "
                    @click="activeTab = group.key"
                >
                    {{ shortLabels[group.key] }}
                    <span class="text-xs text-(--rp-muted) tabular-nums">{{
                        group.entries.length
                    }}</span>
                </button>
            </div>
            <div
                v-if="activeGroup"
                :id="`panel-${activeGroup.key}`"
                role="tabpanel"
                :aria-labelledby="`tab-${activeGroup.key}`"
                class="mt-3"
            >
                <p
                    v-if="activeGroup.key === 'free'"
                    class="mb-2 text-xs text-(--rp-muted)"
                >
                    Free, may include ads
                </p>
                <ul class="space-y-1">
                    <li
                        v-for="entry in activeGroup.entries"
                        :key="entry.name"
                        class="flex items-center gap-3 px-1 py-1.5 text-sm"
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
            </div>
        </div>

        <!-- columns -->
        <div
            v-else-if="look === 'columns'"
            class="mt-5 grid gap-6 sm:grid-cols-2"
            :class="{
                'lg:grid-cols-3': groups.length === 3,
                'lg:grid-cols-4': groups.length >= 4,
            }"
        >
            <div
                v-for="group in groups"
                :key="group.key"
                class="min-w-0 lg:border-l lg:border-(--rp-line) lg:pl-5 lg:first:border-l-0 lg:first:pl-0"
            >
                <h3
                    class="flex items-baseline justify-between text-sm font-semibold"
                >
                    {{ group.label }}
                    <span
                        class="text-xs font-normal text-(--rp-muted) tabular-nums"
                        >{{ group.entries.length }}</span
                    >
                </h3>
                <ul class="mt-3 space-y-2">
                    <li
                        v-for="entry in group.entries"
                        :key="entry.name"
                        class="flex min-w-0 items-center gap-2.5 text-sm"
                    >
                        <img
                            :src="`${LOGO_BASE}${entry.logo}`"
                            alt=""
                            loading="lazy"
                            class="size-7 shrink-0 rounded-md"
                        />
                        <span class="truncate">{{ entry.name }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- services -->
        <table v-else-if="look === 'services'" class="mt-4 w-full text-sm">
            <caption class="sr-only">
                Streaming services and how each offers this movie
            </caption>
            <thead class="sr-only">
                <tr>
                    <th scope="col">Service</th>
                    <th scope="col">Offers</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-(--rp-line)">
                <tr v-for="service in services" :key="service.name">
                    <th scope="row" class="py-2 pr-3 text-left font-normal">
                        <span class="flex items-center gap-3">
                            <img
                                :src="`${LOGO_BASE}${service.logo}`"
                                alt=""
                                loading="lazy"
                                class="size-8 shrink-0 rounded-md"
                            />
                            {{ service.name }}
                        </span>
                    </th>
                    <td class="py-2 text-right">
                        <span class="inline-flex flex-wrap justify-end gap-1.5">
                            <span
                                v-for="offer in service.offers"
                                :key="offer"
                                class="rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
                                :class="chipClass[offer]"
                                >{{
                                    offer === 'free'
                                        ? 'Free, may include ads'
                                        : shortLabels[offer]
                                }}</span
                            >
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- list -->
        <div v-else class="mt-4 space-y-4">
            <div v-for="group in groups" :key="group.key">
                <h3
                    class="text-xs font-semibold tracking-wide text-(--rp-muted) uppercase"
                >
                    {{ group.label }}
                </h3>
                <ul class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <li
                        v-for="entry in group.entries"
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
            </div>
        </div>

        <div
            class="mt-5 flex flex-col gap-3 border-t border-(--rp-line) pt-3"
            :class="wide && 'md:flex-row md:items-end md:justify-between'"
        >
            <div class="space-y-1 text-xs text-(--rp-muted)">
                <p>
                    Availability changes often and may be incomplete. Not listed
                    doesn't mean unavailable.
                </p>
                <p>
                    Streaming data from JustWatch · Updated
                    {{ availability.updatedAgo }}
                </p>
            </div>
            <a
                v-if="availability.data.link"
                :href="availability.data.link"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex shrink-0 items-center gap-1.5 text-sm font-medium text-(--rp-accent) hover:underline"
            >
                See all watch options on TMDB
                <ExternalLink class="size-3.5" />
                <span class="sr-only">(opens in a new tab)</span>
            </a>
        </div>
    </section>
</template>
