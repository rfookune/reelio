<script setup lang="ts">
import { Bookmark, BookmarkCheck } from '@lucide/vue';
import { computed } from 'vue';
import { setWatchlist } from '@/lib/userMovieActions';
import type { UserMovieActionSource, UserMovieFacts } from '@/types';

/** The compact Watchlist toggle at the end of a list row. */
const props = defineProps<{
    tmdbId: number;
    title: string;
    facts: UserMovieFacts | undefined;
    source: UserMovieActionSource;
}>();

const watchlisted = computed(() => props.facts?.watchlisted ?? false);
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
        class="flex size-11 shrink-0 items-center justify-center rounded-full transition-colors hover:bg-raised md:size-10"
        :class="
            watchlisted ? 'text-accent' : 'text-muted hover:text-foreground'
        "
        @click="
            setWatchlist(
                tmdbId,
                !watchlisted,
                source,
                $event.currentTarget as HTMLElement,
            )
        "
    >
        <BookmarkCheck
            v-if="watchlisted"
            class="size-5 fill-current"
            aria-hidden="true"
        />
        <Bookmark v-else class="size-5" aria-hidden="true" />
    </button>
</template>
