<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { updateAppearance } = useAppearance();

/**
 * Flips whatever theme is showing. The icon and label follow the `dark`
 * class the root view sets before first paint, so they never flash or
 * mismatch between the server and client renders.
 */
function toggleTheme(): void {
    const isDark = document.documentElement.classList.contains('dark');

    updateAppearance(isDark ? 'light' : 'dark');
}
</script>

<template>
    <button
        type="button"
        class="flex size-11 items-center justify-center rounded-full text-muted transition-colors hover:bg-raised hover:text-foreground md:size-9"
        @click="toggleTheme"
    >
        <Sun class="hidden size-5 dark:block" aria-hidden="true" />
        <Moon class="size-5 dark:hidden" aria-hidden="true" />
        <span class="sr-only dark:hidden">Switch to dark theme</span>
        <span class="sr-only hidden dark:inline">Switch to light theme</span>
    </button>
</template>
