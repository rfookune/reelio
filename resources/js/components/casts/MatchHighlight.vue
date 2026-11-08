<script setup lang="ts">
import { computed } from 'vue';

/** The text with the first place it contains the filter's text marked. */
const props = defineProps<{ text: string; query: string }>();

const parts = computed(() => {
    const needle = props.query.trim().toLowerCase();
    const at = needle === '' ? -1 : props.text.toLowerCase().indexOf(needle);

    if (at === -1) {
        return [{ text: props.text, match: false }];
    }

    return [
        { text: props.text.slice(0, at), match: false },
        { text: props.text.slice(at, at + needle.length), match: true },
        { text: props.text.slice(at + needle.length), match: false },
    ];
});
</script>

<template>
    <span>
        <template v-for="(part, index) in parts" :key="index">
            <mark
                v-if="part.match"
                class="rounded-sm bg-accent/30 text-inherit"
                >{{ part.text }}</mark
            >
            <template v-else>{{ part.text }}</template>
        </template>
    </span>
</template>
