import { router } from '@inertiajs/vue3';
import { nextTick } from 'vue';

function focusPageHeading(): void {
    const heading = document.querySelector<HTMLElement>('h1');

    if (!heading) {
        return;
    }

    heading.setAttribute('tabindex', '-1');
    heading.focus({ preventScroll: true });
}

/**
 * Moves focus to the page's `<h1>` after each page visit and back/forward
 * navigation, so screen reader and keyboard users start at the new content.
 * The first load is left alone so "Skip to content" stays the first stop, and
 * Inertia doesn't fire `navigate` for same-URL visits (partial reloads and
 * actions that redirect back), so those keep focus where it is.
 */
export function initializePageFocus(): void {
    router.on('navigate', (event) => {
        if (event.detail.type === 'initial') {
            return;
        }

        void nextTick(focusPageHeading);
    });
}
