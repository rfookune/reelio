<script setup lang="ts">
/** PROTOTYPE, throwaway. Mobile bottom navigation: four items (PRD §6.3). */
import { mobileNav } from './shared';
import { usePrototypeState } from './usePrototypeState';

const { state, go } = usePrototypeState();

function isActive(screen: string): boolean {
    return (
        state.screen === screen ||
        (screen === 'watchlist' && state.screen === 'watched')
    );
}
</script>

<template>
    <nav
        aria-label="Primary"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-(--rp-line) bg-(--rp-bg)/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-md md:hidden"
    >
        <ul class="grid grid-cols-4">
            <li v-for="item in mobileNav" :key="item.screen">
                <button
                    type="button"
                    class="flex h-16 w-full flex-col items-center justify-center gap-1 text-[11px] font-medium"
                    :class="
                        isActive(item.screen)
                            ? 'text-(--rp-accent)'
                            : 'text-(--rp-muted)'
                    "
                    :aria-current="isActive(item.screen) ? 'page' : undefined"
                    @click="go(item.screen)"
                >
                    <component
                        :is="item.icon"
                        class="size-5"
                        :stroke-width="isActive(item.screen) ? 2.25 : 1.75"
                    />
                    {{ item.label }}
                </button>
            </li>
        </ul>
    </nav>
</template>
