<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div class="inline-flex gap-1 rounded-full bg-surface p-1 ring-1 ring-line">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            :aria-pressed="appearance === value"
            @click="updateAppearance(value)"
            :class="[
                'flex items-center rounded-full px-3.5 py-1.5 transition-colors',
                appearance === value
                    ? 'bg-accent-fill text-accent-fill-foreground'
                    : 'text-muted hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="-ml-1 h-4 w-4" aria-hidden="true" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
