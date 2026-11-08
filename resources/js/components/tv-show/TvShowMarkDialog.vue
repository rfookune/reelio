<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * The confirmation before a whole TV Show is marked as watched, which names how many aired Episodes it will mark.
 * Cancel and Escape change nothing; the focus is trapped inside and returned by the dialog.
 */
defineProps<{
    /** The aired Episodes the mark covers; 0 when none are stored yet, so the count isn't known. */
    total: number;
    busy?: boolean;
}>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ confirm: [] }>();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Mark as watched?</DialogTitle>
                <DialogDescription>
                    {{
                        total === 0
                            ? 'This marks every aired Episode as watched.'
                            : `This marks all ${total} aired ${total === 1 ? 'Episode' : 'Episodes'} as watched.`
                    }}
                    You can undo it right after.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    data-slot="tv-show-mark-cancel"
                    @click="open = false"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    data-slot="tv-show-mark-confirm"
                    :disabled="busy"
                    @click="emit('confirm')"
                >
                    Mark as watched
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
