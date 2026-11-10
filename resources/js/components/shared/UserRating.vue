<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Star, X } from '@lucide/vue';
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { announce } from '@/composables/useAnnouncer';
import { update } from '@/routes/titles/rating';

/**
 * The signed-in viewer's own rating of a Title, set apart from the External ratings. Unrated, a "Rate" button opens a
 * 1–10 slider panel; rated, a chip reads "Your rating 7/10" (opening the same panel to change it) with a × that clears.
 * Changes show at once and roll back, with a toast, if the server refuses them. Guests are not shown this control.
 */
const props = defineProps<{
    titleId: number;
    /** The saved rating, or null when unrated. */
    rating: number | null;
}>();

const DEFAULT_DRAFT = 5;
const current = ref<number | null>(props.rating);
const draft = ref<number>(props.rating ?? DEFAULT_DRAFT);
const open = ref(false);
const root = ref<HTMLElement | null>(null);
const slider = ref<HTMLInputElement | null>(null);
const panelId = `user-rating-panel-${useId()}`;
const sliderId = `user-rating-slider-${useId()}`;
let latestSave = 0;
const http = useHttp<{ rating: number | null }>({ rating: null });

watch(
    () => props.rating,
    (rating) => {
        current.value = rating;
    },
);

async function openPanel(): Promise<void> {
    draft.value = current.value ?? DEFAULT_DRAFT;
    open.value = true;
    await nextTick();
    slider.value?.focus();
}

/** Focuses whichever trigger is shown once the DOM has caught up, since rating or clearing swaps it. */
function focusTrigger(): void {
    void nextTick(() =>
        root.value
            ?.querySelector<HTMLElement>('[data-slot="rating-trigger"]')
            ?.focus(),
    );
}

function closePanel(returnFocus: boolean): void {
    open.value = false;

    if (returnFocus) {
        focusTrigger();
    }
}

/** Tabbing out of the control closes the panel, so it is never left floating over the page. */
function onFocusOut(event: FocusEvent): void {
    const next = event.relatedTarget as Node | null;

    if (open.value && next && !root.value?.contains(next)) {
        closePanel(false);
    }
}

function onDocumentPointerDown(event: PointerEvent): void {
    if (open.value && !root.value?.contains(event.target as Node)) {
        closePanel(false);
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('pointerdown', onDocumentPointerDown);
    } else {
        document.removeEventListener('pointerdown', onDocumentPointerDown);
    }
});
onBeforeUnmount(() =>
    document.removeEventListener('pointerdown', onDocumentPointerDown),
);

function save(value: number | null): void {
    const previous = current.value;
    const sequence = ++latestSave;
    let reverted = false;
    const revert = (): boolean => {
        // A newer save owns the displayed value; only the latest request may roll it back.
        if (!reverted && sequence === latestSave) {
            current.value = previous;
            toast.error("Couldn't save your rating. Try again.", {
                duration: 6000,
            });
        }
        reverted = true;

        return false;
    };

    current.value = value;
    http.rating = value;
    announce(
        value === null ? 'Your rating cleared' : `Rated ${value} out of 10`,
    );

    void http
        .put(update(props.titleId).url, {
            onError: revert,
            onHttpException: revert,
            onNetworkError: revert,
        })
        .catch(revert);
}

function saveDraft(): void {
    save(draft.value);
    closePanel(true);
}

function clear(): void {
    save(null);
    focusTrigger();
}
</script>

<template>
    <div
        ref="root"
        data-slot="user-rating"
        class="relative"
        @keydown.esc.stop="open && closePanel(true)"
        @focusout="onFocusOut"
    >
        <div
            v-if="current !== null"
            data-slot="rating-chip"
            class="flex h-10 items-center gap-1 rounded-md border border-control bg-surface pr-1 pl-3 text-sm"
        >
            <Star class="size-4 fill-current text-accent" aria-hidden="true" />
            <button
                type="button"
                data-slot="rating-trigger"
                aria-haspopup="dialog"
                :aria-expanded="open"
                :aria-controls="panelId"
                :aria-label="`Your rating: ${current} out of 10. Change`"
                class="ml-1 rounded-sm"
                @click="open ? closePanel(false) : openPanel()"
            >
                Your rating
                <span class="font-semibold tabular-nums">{{ current }}</span
                ><span class="text-muted">/10</span>
            </button>
            <button
                type="button"
                data-slot="clear-rating"
                aria-label="Clear your rating"
                title="Clear your rating"
                class="ml-1 flex size-8 items-center justify-center rounded-md text-muted transition-colors hover:bg-raised hover:text-foreground"
                @click="clear"
            >
                <X class="size-4" aria-hidden="true" />
            </button>
        </div>
        <Button
            v-else
            type="button"
            size="lg"
            variant="outline"
            data-slot="rating-trigger"
            aria-haspopup="dialog"
            :aria-expanded="open"
            :aria-controls="panelId"
            @click="open ? closePanel(false) : openPanel()"
        >
            <Star class="text-muted" aria-hidden="true" />
            Rate
        </Button>

        <div
            v-if="open"
            :id="panelId"
            role="dialog"
            aria-label="Your rating"
            data-slot="rating-panel"
            class="fixed inset-x-4 bottom-4 z-20 flex flex-col gap-3 rounded-lg border border-control bg-surface p-4 shadow-lg sm:absolute sm:inset-x-auto sm:top-full sm:bottom-auto sm:left-0 sm:mt-2 sm:w-72"
        >
            <label :for="sliderId" class="text-sm font-medium"
                >Your rating</label
            >
            <div class="flex items-center gap-3">
                <input
                    :id="sliderId"
                    ref="slider"
                    v-model.number="draft"
                    type="range"
                    min="1"
                    max="10"
                    step="1"
                    :aria-valuetext="`${draft} out of 10`"
                    class="h-6 min-w-0 flex-1 accent-primary"
                    @keydown.enter.prevent="saveDraft"
                />
                <output
                    :for="sliderId"
                    aria-hidden="true"
                    class="w-10 text-right text-lg font-semibold tabular-nums"
                    >{{ draft }}<span class="text-sm text-muted">/10</span>
                </output>
            </div>
            <Button
                type="button"
                data-slot="save-rating"
                class="self-end"
                @click="saveDraft"
            >
                Save
            </Button>
        </div>
    </div>
</template>
