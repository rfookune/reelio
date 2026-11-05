<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Floating variant switcher for UI prototypes.
 * Arrows (and ← / → keys) cycle variants; the default slot holds extra toggles.
 * Renders only in Vite dev mode so a stray merge can't ship it.
 */
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted } from 'vue';

const props = defineProps<{
    variants: { key: string; name: string }[];
    current: string;
}>();

const emit = defineEmits<{ change: [key: string] }>();

const isDev = import.meta.env.DEV;

const index = computed(() =>
    Math.max(
        0,
        props.variants.findIndex((variant) => variant.key === props.current),
    ),
);

function step(offset: number): void {
    const count = props.variants.length;
    emit('change', props.variants[(index.value + offset + count) % count].key);
}

function onKeydown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;

    if (
        target?.closest('input, textarea, select, [contenteditable]') ||
        event.metaKey ||
        event.ctrlKey ||
        event.altKey
    ) {
        return;
    }

    if (event.key === 'ArrowLeft') {
        step(-1);
    } else if (event.key === 'ArrowRight') {
        step(1);
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        v-if="isDev"
        class="fixed bottom-20 left-1/2 z-[100] flex max-w-[calc(100vw-1rem)] -translate-x-1/2 flex-wrap items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-black bg-yellow-300 p-1 font-mono text-xs text-black shadow-xl md:bottom-4 md:rounded-full"
        style="font-family: ui-monospace, monospace"
    >
        <button
            type="button"
            class="rounded-full p-1.5 hover:bg-black/10"
            aria-label="Previous variant"
            @click="step(-1)"
        >
            <ChevronLeft class="size-4" />
        </button>
        <span class="min-w-40 px-1 text-center font-semibold">
            {{ variants[index].key }} ({{ variants[index].name }})
        </span>
        <button
            type="button"
            class="rounded-full p-1.5 hover:bg-black/10"
            aria-label="Next variant"
            @click="step(1)"
        >
            <ChevronRight class="size-4" />
        </button>
        <slot />
    </div>
</template>
