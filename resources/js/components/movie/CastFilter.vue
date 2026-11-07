<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/**
 * The cast filter. It starts as a "Filter" button, which "/" also presses, and becomes a text input that takes
 * focus; Escape or the ✕ clears it and puts focus back on the button, so keyboard users never lose their place.
 */
const filter = defineModel<string>({ required: true });
const open = ref(filter.value !== '');
const button = ref<InstanceType<typeof Button> | null>(null);
const input = ref<InstanceType<typeof Input> | null>(null);

async function show(): Promise<void> {
    open.value = true;
    await nextTick();
    (input.value?.$el as HTMLInputElement | undefined)?.focus();
}

async function hide(): Promise<void> {
    open.value = false;
    filter.value = '';
    await nextTick();
    (button.value?.$el as HTMLButtonElement | undefined)?.focus();
}

/** "/" opens the filter, unless the visitor is typing somewhere or means another shortcut. */
function onSlash(event: KeyboardEvent): void {
    const target = event.target;

    if (
        event.key !== '/' ||
        open.value ||
        event.ctrlKey ||
        event.metaKey ||
        event.altKey ||
        (target instanceof Element &&
            target.closest('input, textarea, select, [contenteditable]'))
    ) {
        return;
    }

    event.preventDefault();
    void show();
}

onMounted(() => window.addEventListener('keydown', onSlash));
onBeforeUnmount(() => window.removeEventListener('keydown', onSlash));
</script>

<template>
    <div class="flex min-h-9 items-center">
        <Button
            v-if="!open"
            ref="button"
            type="button"
            variant="outline"
            aria-controls="cast-filter"
            aria-expanded="false"
            @click="show"
        >
            <Search aria-hidden="true" />
            Filter
            <kbd
                aria-hidden="true"
                class="hidden rounded border border-line bg-raised px-1.5 text-xs text-muted md:inline"
                >/</kbd
            >
        </Button>
        <div v-else class="relative w-full md:w-80">
            <label for="cast-filter" class="sr-only"
                >Filter the cast by actor or character</label
            >
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted"
                aria-hidden="true"
            />
            <Input
                id="cast-filter"
                ref="input"
                v-model="filter"
                type="text"
                autocomplete="off"
                placeholder="Filter by actor or character"
                class="pr-10 pl-9"
                @keydown.esc="hide"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                aria-label="Close the filter"
                class="absolute top-1/2 right-0.5 -translate-y-1/2 text-muted"
                @click="hide"
            >
                <X aria-hidden="true" />
            </Button>
        </div>
    </div>
</template>
