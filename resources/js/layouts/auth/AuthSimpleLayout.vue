<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppFooter from '@/components/AppFooter.vue';
import LiveRegion from '@/components/LiveRegion.vue';
import SkipLink from '@/components/SkipLink.vue';
import Wordmark from '@/components/Wordmark.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col">
        <SkipLink />

        <div
            class="flex flex-1 flex-col items-center justify-center p-6 md:p-10"
        >
            <div class="flex w-full max-w-sm flex-col items-center gap-4">
                <header>
                    <Link :href="home()" class="rounded-lg">
                        <Wordmark />
                    </Link>
                </header>

                <main
                    id="main-content"
                    tabindex="-1"
                    class="flex w-full flex-col gap-8"
                >
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-semibold">{{ title }}</h1>
                        <p class="text-center text-sm text-muted">
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </main>
            </div>
        </div>

        <AppFooter />
        <LiveRegion />
    </div>
</template>
