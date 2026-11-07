<script setup lang="ts">
import { Ellipsis } from '@lucide/vue';
import { ref } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { clearWatched, markAsWatched } from '@/lib/userMovieActions';
import type { UserMovieList } from '@/types';

const props = defineProps<{
    tmdbId: number;
    title: string | null;
    collection: UserMovieList;
}>();
const emit = defineEmits<{ removing: [] }>();
const trigger = ref<HTMLButtonElement | null>(null);

function apply(): void {
    emit('removing');
    if (props.collection === 'watchlist') {
        markAsWatched(props.tmdbId, props.collection, trigger.value);
    } else {
        clearWatched(props.tmdbId, props.collection, trigger.value);
    }
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                ref="trigger"
                type="button"
                data-slot="movie-menu"
                :aria-label="`More actions for ${title ?? 'Movie'}`"
                class="absolute right-1.5 bottom-1.5 flex size-11 items-center justify-center rounded-full bg-black/70 text-white hover:bg-black/85 md:size-9"
            >
                <Ellipsis class="size-5" aria-hidden="true" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            @close-auto-focus="
                (event) => {
                    if (!trigger?.isConnected) event.preventDefault();
                }
            "
        >
            <DropdownMenuItem @select="apply">{{
                collection === 'watchlist' ? 'Mark as watched' : 'Clear Watched'
            }}</DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
