<script setup lang="ts">
/**
 * PROTOTYPE, throwaway. Footer with the TMDB logo and notice required by
 * the TMDB terms ticket. The logo is TMDB's official asset, hotlinked.
 */
</script>

<template>
    <footer
        class="mt-16 border-t border-(--rp-line) pt-8 pb-28 text-sm text-(--rp-muted) md:pb-10"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col gap-4 px-4 sm:flex-row sm:items-center sm:justify-between md:px-8"
        >
            <div class="flex items-center gap-4">
                <img
                    src="https://www.themoviedb.org/assets/v4/logos/v2/blue_short-8e7b30f73a4020692ccca9c88bafe5dcb6f8a62a4c6bc55cd9ba82bb2cd95f6c.svg"
                    alt="TMDB"
                    class="h-3 w-auto shrink-0"
                />
                <p class="max-w-md">
                    This product uses the TMDB API but is not endorsed or
                    certified by TMDB.
                </p>
            </div>
            <nav class="flex gap-5" aria-label="Footer">
                <a href="#" class="hover:text-(--rp-text)">About</a>
                <a href="#" class="hover:text-(--rp-text)">Privacy</a>
                <a href="#" class="hover:text-(--rp-text)">Terms</a>
            </nav>
        </div>
    </footer>
</template>
