import { usePage } from '@inertiajs/vue3';
import {
    Bookmark,
    CircleCheckBig,
    CircleUserRound,
    Compass,
    Search,
} from '@lucide/vue';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { home, login, register, search as searchRoute } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

export type ShellNavItem = Required<
    Pick<NavItem, 'title' | 'href' | 'icon'>
> & {
    /** Paths under which this item marks the current page. */
    activePaths: string[];
};

export type UseShellNavigationReturn = {
    desktopItems: ShellNavItem[];
    mobileItems: ComputedRef<ShellNavItem[]>;
    isActive: (item: ShellNavItem) => boolean;
};

/*
 * Watchlist and Watched have no routes yet; their paths are fixed by the spec
 * and move to Wayfinder routes when those pages land.
 */
const discover: ShellNavItem = {
    title: 'Discover',
    href: home(),
    icon: Compass,
    activePaths: ['/'],
};

const search: ShellNavItem = {
    title: 'Search',
    href: searchRoute(),
    icon: Search,
    activePaths: ['/search'],
};

const watchlist: ShellNavItem = {
    title: 'Watchlist',
    href: '/watchlist',
    icon: Bookmark,
    activePaths: ['/watchlist'],
};

const watched: ShellNavItem = {
    title: 'Watched',
    href: '/watched',
    icon: CircleCheckBig,
    activePaths: ['/watched'],
};

function matchesPath(currentPath: string, path: string): boolean {
    if (path === '/') {
        return currentPath === '/';
    }

    return currentPath === path || currentPath.startsWith(`${path}/`);
}

/**
 * The shell's primary navigation. Desktop shows all four sections; mobile
 * swaps Watched (reached through the Watchlist / Watched switch) for Profile.
 */
export function useShellNavigation(): UseShellNavigationReturn {
    const page = usePage();
    const { currentUrl } = useCurrentUrl();

    const mobileItems = computed<ShellNavItem[]>(() => [
        discover,
        search,
        { ...watchlist, activePaths: ['/watchlist', '/watched'] },
        {
            title: 'Profile',
            href: page.props.auth.user ? editProfile() : login(),
            icon: CircleUserRound,
            activePaths: ['/settings', login.url(), register.url()],
        },
    ]);

    function isActive(item: ShellNavItem): boolean {
        return item.activePaths.some((path) =>
            matchesPath(currentUrl.value, path),
        );
    }

    return {
        desktopItems: [discover, search, watchlist, watched],
        mobileItems,
        isActive,
    };
}
