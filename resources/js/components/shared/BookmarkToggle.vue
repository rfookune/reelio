<script setup lang="ts">
import { Bookmark, BookmarkCheck } from '@lucide/vue';
import { computed } from 'vue';
import { setWatchlist } from '@/lib/userMovieActions';
import type { UserMovieActionSource, UserMovieFacts } from '@/types';

/**
 * The compact Watchlist toggle, of a Movie unless given its own `watchlisted` state: at the end of a list row, or floating on a card's poster on a solid chip, filled
 * with the accent when the Movie is on the Watchlist.
 */
const props = withDefaults(
    defineProps<{
        tmdbId: number;
        title: string;
        facts?: UserMovieFacts | undefined;
        source: UserMovieActionSource;
        variant?: 'row' | 'floating';
        /** A controlled toggle, as for a TV Show: it shows this state and asks the parent to change it. */
        watchlisted?: boolean;
    }>(),
    { variant: 'row', facts: undefined, watchlisted: undefined },
);
const emit = defineEmits<{ toggle: [watchlisted: boolean] }>();

const watchlisted = computed(
    () => props.watchlisted ?? props.facts?.watchlisted ?? false,
);

function toggle(event: MouseEvent): void {
    if (props.watchlisted === undefined) {
        setWatchlist(
            props.tmdbId,
            !watchlisted.value,
            props.source,
            event.currentTarget as HTMLElement,
        );
    } else {
        emit('toggle', !watchlisted.value);
    }
}
</script>

<template>
    <button
        type="button"
        data-slot="bookmark-toggle"
        :aria-pressed="watchlisted"
        :aria-label="
            watchlisted
                ? `Remove ${title} from Watchlist`
                : `Add ${title} to Watchlist`
        "
        :title="watchlisted ? 'Remove from Watchlist' : 'Add to Watchlist'"
        class="flex size-11 shrink-0 items-center justify-center rounded-full transition-colors"
        :class="
            variant === 'floating'
                ? [
                      'md:size-9',
                      watchlisted
                          ? 'bg-accent-fill text-accent-fill-foreground'
                          : 'bg-black/70 text-white hover:bg-black/85',
                  ]
                : [
                      'hover:bg-raised md:size-10',
                      watchlisted
                          ? 'text-accent'
                          : 'text-muted hover:text-foreground',
                  ]
        "
        @click="toggle"
    >
        <BookmarkCheck
            v-if="watchlisted"
            class="size-5 fill-current"
            aria-hidden="true"
        />
        <Bookmark v-else class="size-5" aria-hidden="true" />
    </button>
</template>
