<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useShellNavigation } from '@/composables/useShellNavigation';

const { mobileItems, isActive } = useShellNavigation();
</script>

<template>
    <nav
        aria-label="Primary"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md md:hidden"
    >
        <ul class="grid grid-cols-4">
            <li v-for="item in mobileItems" :key="item.title">
                <Link
                    :href="item.href"
                    prefetch
                    class="flex h-16 flex-col items-center justify-center gap-1 text-xs font-medium"
                    :class="isActive(item) ? 'text-foreground' : 'text-muted'"
                    :aria-current="isActive(item) ? 'page' : undefined"
                >
                    <span
                        class="flex h-7 w-14 items-center justify-center rounded-full transition-colors"
                        :class="
                            isActive(item)
                                ? 'bg-accent-fill text-accent-fill-foreground'
                                : ''
                        "
                    >
                        <component
                            :is="item.icon"
                            class="size-5"
                            aria-hidden="true"
                        />
                    </span>
                    {{ item.title }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
