<script setup lang="ts">
import { Check } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type {
    Episode,
    Season,
    WatchedProgress,
} from '@/composables/useTvShowActions';
defineProps<{ progress: WatchedProgress; busy: boolean }>();
defineEmits<{
    toggle: [season: Season, episode: Episode];
    markSeason: [season: Season];
    clearSeason: [season: Season];
}>();

const seasonName = (season: Season): string =>
    season.number === 0 ? 'Specials' : `Season ${season.number}`;
</script>

<template>
    <section aria-labelledby="seasons-heading" class="flex flex-col gap-3">
        <h2 id="seasons-heading" class="text-xl font-semibold">
            Seasons and Episodes
        </h2>
        <p v-if="progress.seasons.length === 0" class="text-sm text-muted">
            No Episodes available yet.
        </p>
        <details
            v-for="season in progress.seasons"
            :key="season.id"
            :open="
                season.number !== 0 &&
                season.total > 0 &&
                season.id === progress.defaultOpenSeasonId
            "
            class="rounded-xl border border-line bg-surface"
        >
            <summary
                class="flex cursor-pointer flex-col gap-3 p-4 focus-visible:outline-2 focus-visible:outline-offset-2"
            >
                <span>
                    <span class="block font-semibold">{{
                        seasonName(season)
                    }}</span>
                    <span class="block text-sm text-muted">{{
                        season.total === 0
                            ? 'Upcoming'
                            : `${season.watched} of ${season.total} watched`
                    }}</span>
                    <span
                        v-if="season.number === 0"
                        class="block text-sm text-muted"
                        >Specials do not count toward progress.</span
                    >
                </span>
                <progress
                    v-if="season.total > 0"
                    :value="season.watched"
                    :max="season.total"
                    :aria-label="`${seasonName(season)} watched`"
                    class="h-1.5 w-full overflow-hidden rounded-full bg-raised accent-primary [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-primary [&::-webkit-progress-bar]:rounded-full [&::-webkit-progress-bar]:bg-raised [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-primary"
                />
            </summary>
            <div
                v-if="season.total > 0"
                data-slot="season-bulk-actions"
                class="flex flex-wrap gap-2 border-t border-line p-4"
            >
                <Button
                    type="button"
                    size="sm"
                    data-slot="season-mark"
                    :aria-label="`Mark ${seasonName(season)} as watched`"
                    :disabled="busy || season.watched === season.total"
                    @click="$emit('markSeason', season)"
                >
                    Mark {{ season.number === 0 ? 'Specials' : 'Season' }} as
                    watched
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    data-slot="season-clear"
                    :aria-label="`Clear ${seasonName(season)}`"
                    :disabled="busy || season.watched === 0"
                    @click="$emit('clearSeason', season)"
                >
                    Clear {{ season.number === 0 ? 'Specials' : 'Season' }}
                </Button>
            </div>
            <ul class="divide-y divide-line border-t border-line">
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
                        <p class="font-medium">
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
                        <details v-if="episode.overview" class="mt-2 text-sm">
                            <summary class="cursor-pointer text-muted">
                                Episode overview
                            </summary>
                            <p class="pt-2 leading-relaxed">
                                {{ episode.overview }}
                            </p>
                        </details>
                    </div>
                </li>
            </ul>
        </details>
    </section>
</template>
