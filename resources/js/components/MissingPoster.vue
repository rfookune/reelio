<script setup lang="ts">
import { Film } from '@lucide/vue';

defineProps<{
    title: string;
}>();
</script>

<template>
    <div
        aria-hidden="true"
        class="@container flex size-full flex-col items-center justify-center gap-1.5 bg-raised p-1.5 text-center text-muted @[6rem]:gap-3 @[6rem]:p-3"
    >
        <Film class="size-5 shrink-0 @[6rem]:size-8" :stroke-width="1.5" />
        <span
            class="line-clamp-3 text-[0.625rem] leading-tight font-medium text-balance break-words @[6rem]:text-sm @[6rem]:leading-snug"
        >
            {{ title }}
        </span>
    </div>
</template>
