<script setup lang="ts">
import SeoHead from '@/components/SeoHead.vue';

defineProps<{
    title: string;
    description: string;
    lastUpdated?: string;
}>();
</script>

<template>
    <article class="mx-auto flex max-w-3xl flex-col gap-6 py-10 md:py-14">
        <SeoHead :title="title" :description="description" />
        <header class="flex flex-col gap-2">
            <h1 class="text-3xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="lastUpdated" class="text-sm text-muted">
                Last updated: {{ lastUpdated }}
            </p>
        </header>
        <div
            class="flex flex-col gap-4 leading-relaxed [&_a]:underline [&_a]:underline-offset-4 [&_h2]:mt-6 [&_h2]:text-xl [&_h2]:font-semibold [&_ul]:flex [&_ul]:list-disc [&_ul]:flex-col [&_ul]:gap-1 [&_ul]:pl-6"
        >
            <slot />
        </div>
    </article>
</template>
