<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Star, X } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { announce } from '@/composables/useAnnouncer';
import { update } from '@/routes/titles/rating';

/**
 * The signed-in viewer's own rating of a Title, set apart from the External ratings. Unrated, a "Rate" button opens a
 * dialog named for the Title; rated, a chip reads "Your rating 7/10" (opening the same dialog to change it) with a ×
 * that clears. The dialog holds ten stars: choosing the Nth fills every star up to it and shows N in a bubble below it, and the Rate
 * button confirms it (Cancel or Escape change nothing). Changes show at once and roll back, with a toast, if the server refuses them. Guests
 * are not shown this control.
 */
const props = defineProps<{
    titleId: number;
    /** The Title's name, which heads the dialog. */
    title: string | null;
    /** The saved rating, or null when unrated. */
    rating: number | null;
}>();

const STARS = Array.from({ length: 10 }, (_, index) => index + 1);
const current = ref<number | null>(props.rating);
/** The star under the pointer or with focus, previewed as filled up to it. */
const preview = ref<number | null>(null);
/** The star chosen in the open dialog, not yet confirmed. */
const draft = ref<number | null>(null);
const open = ref(false);
const root = ref<HTMLElement | null>(null);
const stars = ref<HTMLElement | null>(null);
let latestSave = 0;
const http = useHttp<{ rating: number | null }>({ rating: null });

watch(
    () => props.rating,
    (rating) => {
        current.value = rating;
    },
);

/** The star that takes focus when the group is tabbed into: the chosen one, else the first. */
function tabStop(): number {
    return draft.value ?? 1;
}

watch(open, (isOpen) => {
    if (isOpen) {
        draft.value = current.value;
        preview.value = null;
    }
});

function focusStar(value: number): void {
    stars.value?.querySelector<HTMLElement>(`[data-star="${value}"]`)?.focus();
}

/** Focuses whichever trigger is shown once the DOM has caught up, since rating or clearing swaps it. */
function focusTrigger(): void {
    void nextTick(() =>
        root.value
            ?.querySelector<HTMLElement>('[data-slot="rating-trigger"]')
            ?.focus(),
    );
}

function onOpenAutoFocus(event: Event): void {
    event.preventDefault();
    focusStar(tabStop());
}

/** The trigger that opened the dialog may have been swapped by the save, so focus is returned by hand. */
function onCloseAutoFocus(event: Event): void {
    event.preventDefault();
    focusTrigger();
}

function onStarKeydown(event: KeyboardEvent, value: number): void {
    const next = {
        ArrowRight: Math.min(10, value + 1),
        ArrowUp: Math.min(10, value + 1),
        ArrowLeft: Math.max(1, value - 1),
        ArrowDown: Math.max(1, value - 1),
        Home: 1,
        End: 10,
    }[event.key];

    if (next !== undefined) {
        event.preventDefault();
        focusStar(next);
    }
}

function isFilled(value: number): boolean {
    return value <= (preview.value ?? draft.value ?? 0);
}

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

function confirm(): void {
    if (draft.value !== null && draft.value !== current.value) {
        save(draft.value);
    }

    open.value = false;
}

function clear(): void {
    save(null);
    focusTrigger();
}
</script>

<template>
    <div ref="root" data-slot="user-rating" class="relative">
        <div
            v-if="current !== null"
            data-slot="rating-chip"
            class="flex h-10 items-center gap-1 rounded-md border border-control bg-surface pr-1 pl-3 text-sm"
        >
            <Star
                class="size-4 fill-yellow-400 text-yellow-400"
                aria-hidden="true"
            />
            <button
                type="button"
                data-slot="rating-trigger"
                aria-haspopup="dialog"
                :aria-expanded="open"
                :aria-label="`Your rating: ${current} out of 10. Change`"
                class="ml-1 rounded-sm"
                @click="open = true"
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
            @click="open = true"
        >
            <Star class="text-yellow-400" aria-hidden="true" />
            Rate
        </Button>

        <Dialog v-model:open="open">
            <DialogContent
                data-slot="rating-dialog"
                class="sm:max-w-md"
                @open-auto-focus="onOpenAutoFocus"
                @close-auto-focus="onCloseAutoFocus"
            >
                <DialogHeader>
                    <DialogTitle>{{ title ?? 'Your rating' }}</DialogTitle>
                    <DialogDescription class="sr-only">
                        Choose a rating from 1 to 10, then confirm it.
                    </DialogDescription>
                </DialogHeader>
                <div
                    ref="stars"
                    role="radiogroup"
                    aria-label="Your rating"
                    class="flex justify-center gap-0.5 pb-7"
                    @mouseleave="preview = null"
                >
                    <button
                        v-for="value in STARS"
                        :key="value"
                        type="button"
                        role="radio"
                        :data-star="value"
                        :aria-checked="value === draft"
                        :aria-label="`${value} out of 10`"
                        :tabindex="value === tabStop() ? 0 : -1"
                        class="relative flex size-8 items-center justify-center rounded-md sm:size-9"
                        @mouseenter="preview = value"
                        @focus="preview = value"
                        @blur="preview = null"
                        @keydown="onStarKeydown($event, value)"
                        @click="draft = value"
                    >
                        <span
                            v-if="value === (preview ?? draft)"
                            data-slot="rating-value"
                            aria-hidden="true"
                            class="absolute -bottom-7 left-1/2 -translate-x-1/2 rounded-md bg-foreground px-1.5 py-0.5 text-xs font-semibold text-background tabular-nums"
                            >{{ value }}</span
                        >
                        <Star
                            class="size-6 sm:size-7"
                            :class="
                                isFilled(value)
                                    ? 'fill-yellow-400 text-yellow-400'
                                    : 'text-muted'
                            "
                            aria-hidden="true"
                        />
                    </button>
                </div>
                <DialogFooter class="flex-row justify-end">
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            data-slot="cancel-rating"
                            @click="open = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            data-slot="save-rating"
                            :disabled="draft === null"
                            @click="confirm"
                        >
                            Rate
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
