<script setup lang="ts">
import AppFooter from '@/components/AppFooter.vue';
import AppTopBar from '@/components/AppTopBar.vue';
import BottomNav from '@/components/BottomNav.vue';
import LiveRegion from '@/components/LiveRegion.vue';
import SkipLink from '@/components/SkipLink.vue';
import { Toaster } from '@/components/ui/sonner';

/*
 * Sonner's own mobile breakpoint (600px) is narrower than the bottom nav's
 * (md), so both offsets follow one variable that clears the nav below md.
 */
const toastOffset = { bottom: 'var(--toast-offset-bottom)' };
</script>

<template>
    <div
        class="flex min-h-svh flex-col pb-[calc(4rem+env(safe-area-inset-bottom))] [--toast-offset-bottom:calc(5rem+env(safe-area-inset-bottom))] md:pb-0 md:[--toast-offset-bottom:1.5rem]"
    >
        <SkipLink />

        <AppTopBar />

        <main
            id="main-content"
            tabindex="-1"
            class="mx-auto w-full max-w-7xl flex-1 px-4 md:px-8"
        >
            <slot />
        </main>

        <AppFooter />
        <BottomNav />
        <LiveRegion />
        <Toaster :offset="toastOffset" :mobile-offset="toastOffset" />
    </div>
</template>
