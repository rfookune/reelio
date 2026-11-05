import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

/** The theme used until the visitor chooses one; matches `HandleAppearance`. */
const defaultAppearance: Appearance = 'dark';

function isAppearance(value: unknown): value is Appearance {
    return value === 'light' || value === 'dark' || value === 'system';
}

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    const isDark = value === 'system' ? prefersDark() : value === 'dark';

    document.documentElement.classList.toggle('dark', isDark);
}

/**
 * The `appearance` cookie is the single source of truth: the server reads it
 * to render the right theme on first paint, so the client reads it too.
 */
function storedAppearance(): Appearance {
    if (typeof document === 'undefined') {
        return defaultAppearance;
    }

    const value = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('appearance='))
        ?.slice('appearance='.length);

    return isAppearance(value) ? value : defaultAppearance;
}

function storeAppearance(value: Appearance, days = 365): void {
    const maxAge = days * 24 * 60 * 60;

    document.cookie = `appearance=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    updateTheme(storedAppearance());

    window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => updateTheme(storedAppearance()));
}

const appearance = ref<Appearance>(defaultAppearance);

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        appearance.value = storedAppearance();
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance): void {
        appearance.value = value;
        storeAppearance(value);
        updateTheme(value);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
