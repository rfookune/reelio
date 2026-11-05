<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useShellNavigation } from '@/composables/useShellNavigation';

const { desktopItems, isActive } = useShellNavigation();
</script>

<template>
    <nav aria-label="Primary">
        <ul class="flex rounded-full bg-surface p-1 ring-1 ring-line">
            <li v-for="item in desktopItems" :key="item.title">
                <Link
                    :href="item.href"
                    prefetch
                    class="flex h-8 items-center rounded-full px-4 text-sm font-medium transition-colors"
                    :class="
                        isActive(item)
                            ? 'bg-accent-fill text-accent-fill-foreground'
                            : 'text-muted hover:text-foreground'
                    "
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    {{ item.title }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
