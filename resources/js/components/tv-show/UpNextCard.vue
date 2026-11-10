<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { Episode, Season } from '@/composables/useTvShowActions';

/** The next Episode to watch, with a one-tap Mark watched. */
defineProps<{ season: Season; episode: Episode; busy: boolean }>();
defineEmits<{ mark: [] }>();
</script>

<template>
    <div
        data-slot="up-next"
        class="flex max-w-md items-center gap-3 rounded-xl border border-line bg-surface/80 p-3 backdrop-blur"
    >
        <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold tracking-wide text-muted uppercase">
                Up next
            </p>
            <p class="truncate font-medium">
                S{{ season.number }}·E{{ episode.number }} ·
                {{ episode.name ?? 'Unnamed Episode' }}
            </p>
        </div>
        <Button
            type="button"
            size="sm"
            data-slot="up-next-mark"
            :aria-label="`Mark Season ${season.number} Episode ${episode.number} as watched`"
            :disabled="busy"
            @click="$emit('mark')"
        >
            Mark watched
        </Button>
    </div>
</template>
