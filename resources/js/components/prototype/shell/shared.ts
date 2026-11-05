/**
 * PROTOTYPE, throwaway. Navigation items shared by every shell variant.
 */
import type { Component } from 'vue';
import {
    Bookmark,
    CircleCheckBig,
    CircleUserRound,
    Compass,
    Search,
} from '@lucide/vue';
import type { Screen } from './usePrototypeState';

export type ShellNavItem = {
    screen: Screen;
    label: string;
    icon: Component;
};

/** PRD §6.3 desktop navigation: five items. */
export const desktopNav: ShellNavItem[] = [
    { screen: 'discover', label: 'Discover', icon: Compass },
    { screen: 'search', label: 'Search', icon: Search },
    { screen: 'watchlist', label: 'Watchlist', icon: Bookmark },
    { screen: 'watched', label: 'Watched', icon: CircleCheckBig },
    { screen: 'profile', label: 'Profile', icon: CircleUserRound },
];

/** PRD §6.3 mobile navigation: four items; Watched lives inside Watchlist. */
export const mobileNav: ShellNavItem[] = desktopNav.filter(
    (item) => item.screen !== 'watched',
);
