<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Variant B: "Starter-kit sidebar + grid".
 *
 * Keeps the starter kit's collapsible inset sidebar (ui/sidebar), re-themed,
 * with the five nav items; Profile sits in the sidebar footer where NavUser is
 * today. Discover is a grid per section, not shelves. On mobile the sidebar is
 * hidden and the bottom nav takes over.
 */
import { Search } from '@lucide/vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import MobileBottomNav from './MobileBottomNav.vue';
import MovieCardB from './MovieCardB.vue';
import type { MockMovie } from './mockMovies';
import { allMovies, sections } from './mockMovies';
import ScreenStub from './ScreenStub.vue';
import { desktopNav } from './shared';
import SkeletonBlock from './SkeletonBlock.vue';
import TmdbAttribution from './TmdbAttribution.vue';
import { usePrototypeState } from './usePrototypeState';
import WatchlistTabs from './WatchlistTabs.vue';
import Wordmark from './Wordmark.vue';

defineProps<{ loading: boolean }>();

const { state, isOnWatchlist, toggleWatchlist, go } = usePrototypeState();

const sidebarNav = desktopNav.filter((item) => item.screen !== 'profile');

const titles: Record<string, string> = {
    discover: 'Discover',
    search: 'Search',
    watchlist: 'Watchlist',
    watched: 'Watched',
    profile: 'Profile',
};

function savedMovies(): MockMovie[] {
    return allMovies.filter((movie) => state.watchlist.has(movie.id));
}
</script>

<template>
    <SidebarProvider :default-open="true" class="bg-(--rp-bg) text-(--rp-text)">
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            class="group-data-[collapsible=icon]:!p-1"
                            @click="go('discover')"
                        >
                            <Wordmark />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarMenu>
                        <SidebarMenuItem
                            v-for="item in sidebarNav"
                            :key="item.screen"
                        >
                            <SidebarMenuButton
                                :is-active="state.screen === item.screen"
                                :tooltip="item.label"
                                @click="go(item.screen)"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.label }}</span>
                            </SidebarMenuButton>
                            <SidebarMenuBadge
                                v-if="item.screen === 'watchlist'"
                                class="tabular-nums"
                                >{{ state.watchlist.size }}</SidebarMenuBadge
                            >
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>
            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            :is-active="state.screen === 'profile'"
                            tooltip="Profile"
                            @click="go('profile')"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-(--rp-raised) text-xs font-semibold"
                                >JD</span
                            >
                            <span class="grid text-left text-sm leading-tight">
                                <span class="font-medium">Jordan Doe</span>
                                <span class="text-xs text-(--rp-muted)"
                                    >Profile and settings</span
                                >
                            </span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>

        <SidebarInset class="min-w-0 overflow-x-clip">
            <header
                class="flex h-14 shrink-0 items-center gap-3 border-b border-(--rp-line) px-4 md:h-16 md:px-6"
            >
                <SidebarTrigger class="-ml-1 hidden md:inline-flex" />
                <span class="md:hidden"><Wordmark /></span>
                <h1
                    class="hidden text-base font-semibold tracking-tight md:block"
                >
                    {{ titles[state.screen] }}
                </h1>
                <label
                    class="ml-auto hidden h-9 w-72 items-center gap-2 rounded-lg bg-(--rp-surface) px-3 text-sm text-(--rp-muted) ring-1 ring-(--rp-line) focus-within:ring-2 focus-within:ring-(--rp-accent) lg:flex"
                >
                    <Search class="size-4 shrink-0" />
                    <span class="sr-only">Search movies</span>
                    <input
                        type="search"
                        placeholder="Search movies"
                        class="w-full bg-transparent text-(--rp-text) outline-none placeholder:text-(--rp-muted)"
                    />
                </label>
            </header>

            <div class="px-4 md:px-6">
                <template v-if="state.screen === 'discover'">
                    <section
                        v-for="section in sections"
                        :key="section.key"
                        class="mt-8"
                    >
                        <div class="mb-4 flex items-baseline justify-between">
                            <h2 class="text-lg font-semibold tracking-tight">
                                {{ section.title }}
                            </h2>
                            <a
                                href="#"
                                class="text-sm font-medium text-(--rp-accent) hover:underline"
                                >See all</a
                            >
                        </div>
                        <ul
                            class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
                        >
                            <template v-if="loading">
                                <li v-for="n in 6" :key="n">
                                    <SkeletonBlock
                                        class="aspect-[2/3] w-full rounded-xl"
                                    />
                                    <div class="mt-2 flex gap-1.5">
                                        <SkeletonBlock
                                            class="h-5 w-16 rounded-md"
                                        />
                                        <SkeletonBlock
                                            class="h-5 w-16 rounded-md"
                                        />
                                    </div>
                                    <SkeletonBlock
                                        class="mt-2 h-9 w-full rounded-lg"
                                    />
                                </li>
                            </template>
                            <template v-else>
                                <li
                                    v-for="(movie, index) in section.movies"
                                    :key="movie.id"
                                    :class="index >= 6 ? 'hidden' : ''"
                                >
                                    <MovieCardB
                                        :movie="movie"
                                        :on-watchlist="isOnWatchlist(movie)"
                                        @toggle="toggleWatchlist(movie)"
                                    />
                                </li>
                            </template>
                        </ul>
                    </section>
                </template>

                <template v-else-if="state.screen === 'watchlist'">
                    <div class="mt-6 flex items-center justify-between gap-4">
                        <p class="text-sm text-(--rp-muted)">
                            {{ state.watchlist.size }} movies
                        </p>
                        <WatchlistTabs />
                    </div>
                    <p
                        v-if="savedMovies().length === 0"
                        class="mt-16 text-center text-(--rp-muted)"
                    >
                        Your Watchlist is empty. Save movies from Discover and
                        they’ll show up here.
                    </p>
                    <ul
                        v-else
                        class="mt-6 grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
                    >
                        <li v-for="movie in savedMovies()" :key="movie.id">
                            <MovieCardB
                                :movie="movie"
                                :on-watchlist="isOnWatchlist(movie)"
                                @toggle="toggleWatchlist(movie)"
                            />
                        </li>
                    </ul>
                </template>

                <template v-else>
                    <div
                        v-if="state.screen === 'watched'"
                        class="mt-8 flex justify-center"
                    >
                        <WatchlistTabs />
                    </div>
                    <ScreenStub />
                </template>
            </div>

            <TmdbAttribution />
        </SidebarInset>
        <MobileBottomNav />
    </SidebarProvider>
</template>
