<script setup lang="ts">
import { BookmarkCheck, BookmarkPlus } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { setWatchlist } from '@/lib/userMovieActions';
import type { UserMovieActionSource, UserMovieFacts } from '@/types';

const props = defineProps<{
    tmdbId: number;
    facts: UserMovieFacts | undefined;
    source: UserMovieActionSource;
}>();

/** Colour shows state, as on the cards: the accent fill when on the Watchlist, a neutral outline when not. */
const watchlisted = computed(() => props.facts?.watchlisted ?? false);
</script>

<template>
    <Button
        type="button"
        size="lg"
        data-slot="watchlist-toggle"
        :variant="watchlisted ? 'default' : 'outline'"
        :aria-pressed="watchlisted"
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
            class="fill-current"
            aria-hidden="true"
        />
        <BookmarkPlus v-else aria-hidden="true" />
        {{ watchlisted ? 'On your Watchlist' : 'Add to Watchlist' }}
    </Button>
</template>
