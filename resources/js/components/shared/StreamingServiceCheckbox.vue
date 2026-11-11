<script setup lang="ts">
defineProps<{
    name: string;
    checked: boolean;
    /** Whether it can't be chosen now, as when the most services are already chosen. */
    disabled: boolean;
}>();

const emit = defineEmits<{ change: [chosen: boolean] }>();
</script>

<template>
    <label
        class="flex cursor-pointer items-center gap-2 rounded-md border border-input bg-surface px-3 py-1.5 text-sm has-[:checked]:border-primary has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50"
    >
        <input
            type="checkbox"
            :checked="checked"
            :disabled="disabled"
            @change="
                emit('change', ($event.target as HTMLInputElement).checked)
            "
        />
        {{ name }}
    </label>
</template>
