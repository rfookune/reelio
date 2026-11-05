<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    status: number;
    title: string;
    message: string;
}>();
</script>

<template>
    <div class="flex flex-col items-start gap-4 py-16">
        <Head :title="title">
            <meta name="robots" content="noindex" />
        </Head>
        <p class="text-sm font-medium text-muted">{{ status }}</p>
        <h1 class="text-3xl font-semibold tracking-tight">{{ title }}</h1>
        <p class="max-w-prose text-muted">{{ message }}</p>
        <Link
            :href="home()"
            class="rounded-lg text-accent underline-offset-4 hover:underline"
        >
            Back to Discover
        </Link>
    </div>
</template>
