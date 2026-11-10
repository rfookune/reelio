<script setup lang="ts">
import { Check, ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import type {
    Episode,
    Season,
    WatchedProgress,
} from '@/composables/useTvShowActions';
const props = defineProps<{ progress: WatchedProgress; busy: boolean }>();
defineEmits<{
    toggle: [season: Season, episode: Episode];
    markSeason: [season: Season];
    clearSeason: [season: Season];
}>();

const seasonName = (season: Season): string =>
    season.number === 0 ? 'Specials' : `Season ${season.number}`;

/**
 * The Seasons showing their Episodes. The Season to continue with starts open; a Season with no Episodes yet has
 * nothing to open, so it is a plain card with no toggle.
 */
const openSeasonIds = ref(
    new Set(
        props.progress.seasons
            .filter(
                (season) =>
                    season.number !== 0 &&
                    season.total > 0 &&
                    season.id === props.progress.defaultOpenSeasonId,
            )
            .map((season) => season.id),
    ),
);
const isOpen = (season: Season): boolean => openSeasonIds.value.has(season.id);
function toggleSeason(season: Season): void {
    if (openSeasonIds.value.has(season.id)) {
        openSeasonIds.value.delete(season.id);
    } else {
        openSeasonIds.value.add(season.id);
    }
}
</script>

<template>
    <section aria-labelledby="seasons-heading" class="flex flex-col gap-3">
        <h2 id="seasons-heading" class="text-xl font-semibold">
            Seasons and Episodes
        </h2>
        <p v-if="progress.seasons.length === 0" class="text-sm text-muted">
            No Episodes available yet.
        </p>
        <div
            v-for="season in progress.seasons"
            :key="season.id"
            class="rounded-xl border border-line bg-surface"
        >
            <div
                v-if="season.total === 0"
                data-slot="season-upcoming"
                class="p-4"
            >
                <p class="font-semibold">{{ seasonName(season) }}</p>
                <p class="text-sm text-muted">Upcoming</p>
            </div>
            <template v-else>
                <div class="flex flex-col gap-3 p-4">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <button
                            type="button"
                            data-slot="season-toggle"
                            :aria-expanded="isOpen(season)"
                            :aria-controls="`season-${season.id}-episodes`"
                            class="flex min-w-0 flex-1 items-center gap-3 rounded-lg text-left focus-visible:outline-2 focus-visible:outline-offset-2"
                            @click="toggleSeason(season)"
                        >
                            <ChevronDown
                                class="size-5 shrink-0 text-muted transition-transform"
                                :class="isOpen(season) ? 'rotate-180' : ''"
                                aria-hidden="true"
                            />
                            <span class="min-w-0">
                                <span class="block font-semibold">{{
                                    seasonName(season)
                                }}</span>
                                <span class="block text-sm text-muted">{{
                                    `${season.watched} of ${season.total} watched`
                                }}</span>
                                <span
                                    v-if="season.number === 0"
                                    class="block text-sm text-muted"
                                    >Specials do not count toward
                                    progress.</span
                                >
                            </span>
                        </button>
                        <div data-slot="season-bulk-actions" class="flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                data-slot="season-mark"
                                :aria-label="`Mark watched, ${seasonName(season)}`"
                                :disabled="
                                    busy || season.watched === season.total
                                "
                                @click="$emit('markSeason', season)"
                            >
                                Mark watched
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                data-slot="season-clear"
                                :aria-label="`Clear all, ${seasonName(season)}`"
                                :disabled="busy || season.watched === 0"
                                @click="$emit('clearSeason', season)"
                            >
                                Clear all
                            </Button>
                        </div>
                    </div>
                    <progress
                        :value="season.watched"
                        :max="season.total"
                        :aria-label="`${seasonName(season)} watched`"
                        class="h-1.5 w-full overflow-hidden rounded-full bg-raised accent-primary [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-primary [&::-webkit-progress-bar]:rounded-full [&::-webkit-progress-bar]:bg-raised [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-primary"
                    />
                </div>
                <ul
                    v-show="isOpen(season)"
                    :id="`season-${season.id}-episodes`"
                    class="divide-y divide-line border-t border-line"
                >
                    <li
                        v-for="episode in season.episodes"
                        :key="episode.id"
                        class="flex items-start gap-3 p-4"
                    >
                        <button
                            data-slot="episode-toggle"
                            type="button"
                            :aria-label="`Episode ${episode.number}: ${episode.name ?? 'Unnamed Episode'}`"
                            :aria-pressed="episode.watchedDate !== null"
                            :disabled="busy || !episode.markable"
                            class="flex size-10 shrink-0 items-center justify-center rounded-full border text-sm font-semibold tabular-nums transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
                            :class="
                                episode.watchedDate
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : episode.markable
                                      ? 'border-line hover:border-primary'
                                      : 'border-dashed border-line text-muted'
                            "
                            @click="$emit('toggle', season, episode)"
                        >
                            <Check
                                v-if="episode.watchedDate"
                                class="size-5"
                                :stroke-width="3"
                                aria-hidden="true"
                            />
                            <span v-else aria-hidden="true">{{
                                episode.number ?? '·'
                            }}</span>
                        </button>
                        <div class="min-w-0 flex-1">
                            <p
                                class="font-medium"
                                :class="episode.watchedDate ? 'text-muted' : ''"
                            >
                                {{ episode.name ?? 'Unnamed Episode' }}
                            </p>
                            <p class="text-sm text-muted">
                                {{
                                    episode.airDate === null
                                        ? 'Date to be announced'
                                        : episode.markable
                                          ? episode.airDate
                                          : `Upcoming · ${episode.airDate}`
                                }}<span v-if="episode.runtime">
                                    · {{ episode.runtime }} min</span
                                >
                            </p>
                            <p
                                v-if="episode.overview"
                                class="mt-1 text-sm leading-relaxed"
                                :class="episode.watchedDate ? 'text-muted' : ''"
                            >
                                {{ episode.overview }}
                            </p>
                        </div>
                    </li>
                </ul>
            </template>
        </div>
    </section>
</template>
