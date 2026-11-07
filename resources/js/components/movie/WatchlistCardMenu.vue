<script setup lang="ts">
import { Ellipsis } from '@lucide/vue';
import { ref } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { markAsWatched } from '@/lib/userMovieActions';

const props = defineProps<{ tmdbId: number; title: string | null }>();
const emit = defineEmits<{ removing: [] }>();
const trigger = ref<HTMLButtonElement | null>(null);

function mark(): void {
    emit('removing');
    markAsWatched(props.tmdbId, 'watchlist', trigger.value);
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
            <DropdownMenuItem @select="mark">Mark as watched</DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
