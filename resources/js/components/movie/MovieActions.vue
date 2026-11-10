<script setup lang="ts">
import { Check, CircleCheckBig, X } from '@lucide/vue';
import { computed, onBeforeUpdate, onUpdated, ref } from 'vue';
import WatchlistButton from '@/components/movie/WatchlistButton.vue';
import UserRating from '@/components/shared/UserRating.vue';
import { Button } from '@/components/ui/button';
import { useShortDate } from '@/composables/useShortDate';
import { clearWatched, markAsWatched } from '@/lib/userMovieActions';
import type { UserMovieFacts } from '@/types';

/**
 * The Movie page's Watchlist and Watched actions, by state:
 *
 * - neither, or only on the Watchlist: the Watchlist toggle and "Mark as watched";
 * - Watched, not on the Watchlist: the toggle and a "Watched {date}" chip with Clear (×);
 * - Watched and on the Watchlist (a rewatch queued): the toggle, "Mark as watched" (which moves the date) and a
 *   quiet "Last watched {date} · Clear".
 */
const props = defineProps<{
    tmdbId: number;
    facts: UserMovieFacts | undefined;
    /** The Title's id and the viewer's User rating of it; absent for guests, who get no rating control. */
    rating?: { titleId: number; title: string | null; value: number | null };
}>();

const formatShortDate = useShortDate();
const watchlisted = computed(() => props.facts?.watchlisted ?? false);
const watchedDateLabel = computed(() =>
    props.facts?.watched && props.facts.watchedDate
        ? formatShortDate(props.facts.watchedDate)
        : null,
);
const showsChip = computed(
    () => watchedDateLabel.value !== null && !watchlisted.value,
);

/*
 * Marking or clearing swaps the pressed control for another, and a rollback swaps it back. When the control that
 * had focus goes, focus moves to the Watched control now shown: "Mark as watched", else the chip's Clear.
 */
const actions = ref<HTMLElement | null>(null);
let hadFocus = false;

onBeforeUpdate(() => {
    hadFocus = actions.value?.contains(document.activeElement) ?? false;
});

onUpdated(() => {
    if (!hadFocus || actions.value?.contains(document.activeElement)) {
        return;
    }

    actions.value
        ?.querySelector<HTMLElement>(
            '[data-slot="mark-as-watched"], [data-slot="clear-watched"]',
        )
        ?.focus();
});
</script>

<template>
    <div
        ref="actions"
        data-slot="movie-actions"
        :data-tmdb-id="tmdbId"
        class="mt-3 flex flex-wrap items-center gap-3"
    >
        <WatchlistButton :tmdb-id="tmdbId" :facts="facts" source="movie" />

        <div
            v-if="showsChip"
            data-slot="watched-chip"
            class="flex h-10 items-center gap-1 rounded-md border border-control bg-surface pr-1 pl-3 text-sm"
        >
            <CircleCheckBig class="size-4 text-accent" aria-hidden="true" />
            <span class="ml-1"
                >Watched
                <span class="tabular-nums">{{ watchedDateLabel }}</span></span
            >
            <button
                type="button"
                data-slot="clear-watched"
                aria-label="Clear Watched"
                title="Clear Watched"
                class="ml-1 flex size-8 items-center justify-center rounded-md text-muted transition-colors hover:bg-raised hover:text-foreground"
                @click="
                    clearWatched(
                        tmdbId,
                        'movie',
                        $event.currentTarget as HTMLElement,
                    )
                "
            >
                <X class="size-4" aria-hidden="true" />
            </button>
        </div>
        <Button
            v-else
            type="button"
            size="lg"
            variant="outline"
            data-slot="mark-as-watched"
            @click="
                markAsWatched(
                    tmdbId,
                    'movie',
                    $event.currentTarget as HTMLElement,
                )
            "
        >
            <Check aria-hidden="true" />
            Mark as watched
        </Button>

        <UserRating
            v-if="rating"
            :title-id="rating.titleId"
            :title="rating.title"
            :rating="rating.value"
        />

        <p
            v-if="watchedDateLabel !== null && watchlisted"
            data-slot="last-watched"
            class="w-full text-sm text-muted"
        >
            Last watched
            <span class="tabular-nums">{{ watchedDateLabel }}</span> ·
            <button
                type="button"
                data-slot="clear-last-watched"
                aria-label="Clear Watched"
                class="underline underline-offset-2 transition-colors hover:text-foreground"
                @click="
                    clearWatched(
                        tmdbId,
                        'movie',
                        $event.currentTarget as HTMLElement,
                    )
                "
            >
                Clear
            </button>
        </p>
    </div>
</template>
