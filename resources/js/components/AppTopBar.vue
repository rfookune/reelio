<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import NavUser from '@/components/NavUser.vue';
import PrimaryNav from '@/components/PrimaryNav.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import Wordmark from '@/components/Wordmark.vue';
import { home, login } from '@/routes';

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <header
        data-slot="top-bar"
        class="sticky top-0 z-40 border-b border-line bg-background/90 backdrop-blur-md [:root:has([data-top-bar-glow])_&]:border-transparent [:root:has([data-top-bar-glow])_&]:bg-background/40"
    >
        <div
            class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 md:px-8"
        >
            <div class="flex flex-1">
                <Link :href="home()" class="rounded-lg">
                    <Wordmark />
                </Link>
            </div>

            <PrimaryNav class="hidden md:block" />

            <div class="flex flex-1 items-center justify-end gap-2">
                <ThemeToggle />
                <div class="hidden md:flex">
                    <NavUser v-if="user" :user="user" />
                    <Link
                        v-else
                        :href="login()"
                        class="flex h-9 items-center rounded-full px-4 text-sm font-medium text-foreground ring-1 ring-control transition-colors hover:bg-raised"
                    >
                        Log in
                    </Link>
                </div>
            </div>
        </div>
    </header>
</template>
