<script setup lang="ts">
import type {
    Episode,
    Season,
    WatchedProgress,
} from '@/composables/useTvShowActions';
defineProps<{ progress: WatchedProgress; busy: boolean }>();
defineEmits<{ toggle: [season: Season, episode: Episode] }>();
</script>
<template>
    <section aria-labelledby="seasons-heading" class="flex flex-col gap-4">
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
                class="cursor-pointer p-4 focus-visible:outline-2 focus-visible:outline-offset-2"
            >
                <span class="font-semibold">{{
                    season.number === 0 ? 'Specials' : `Season ${season.number}`
                }}</span>
                <span class="ml-3 text-sm text-muted">{{
                    season.total === 0
                        ? 'Upcoming'
                        : `${season.watched} of ${season.total} watched`
                }}</span>
                <span
                    v-if="season.number === 0"
                    class="mt-1 block text-sm text-muted"
                    >Specials do not count toward progress.</span
                >
                <progress
                    v-if="season.total > 0"
                    :value="season.watched"
                    :max="season.total"
                    :aria-label="
                        season.number === 0
                            ? 'Specials watched'
                            : `Season ${season.number} watched`
                    "
                    class="mt-2 block h-1 w-full accent-primary"
                />
            </summary>
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
                        class="hover:bg-canvas flex size-10 shrink-0 items-center justify-center rounded-full border border-line text-lg transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50"
                        :class="
                            episode.watchedDate
                                ? 'bg-primary text-primary-foreground'
                                : ''
                        "
                        @click="$emit('toggle', season, episode)"
                    >
                        <span aria-hidden="true">{{
                            episode.watchedDate ? '✓' : '○'
                        }}</span>
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ episode.number }}.
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
