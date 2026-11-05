<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import tmdbLogo from '../../images/tmdb-logo.svg';

/*
 * These pages land with the public pages ticket; the links keep TMDB's
 * required placement and their fixed order until then.
 */
const links = [
    { title: 'About', href: '/about' },
    { title: 'Privacy', href: '/privacy' },
    { title: 'Terms', href: '/terms' },
    { title: 'Accessibility', href: '/accessibility' },
];
</script>

<template>
    <footer class="mt-16 border-t border-line py-8 text-sm text-muted md:py-10">
        <div
            class="mx-auto flex max-w-7xl flex-col gap-6 px-4 md:flex-row md:items-center md:justify-between md:px-8"
        >
            <a
                href="https://www.themoviedb.org"
                class="group flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:gap-4"
            >
                <img
                    :src="tmdbLogo"
                    alt="TMDB"
                    width="92"
                    height="12"
                    class="h-3 w-auto shrink-0"
                />
                <span class="max-w-md group-hover:text-foreground">
                    This website uses TMDB and the TMDB APIs but is not
                    endorsed, certified, or otherwise approved by TMDB.
                </span>
            </a>
            <nav aria-label="Footer">
                <ul class="flex flex-wrap gap-x-5 gap-y-2">
                    <li v-for="link in links" :key="link.title">
                        <Link
                            :href="link.href"
                            class="inline-flex min-h-6 items-center hover:text-foreground"
                        >
                            {{ link.title }}
                        </Link>
                    </li>
                </ul>
            </nav>
        </div>
    </footer>
</template>
