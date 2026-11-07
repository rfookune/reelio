<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useShortDate } from '@/composables/useShortDate';
import { removedMovieData } from '@/lib/errorPages';
import { clearWatched, setWatchlist } from '@/lib/userMovieActions';
import { home, watchlist } from '@/routes';

const props = defineProps<{
    status: number;
    title: string;
    message: string;
    exceptionClass: string;
    data?: Record<string, unknown>;
}>();
const removedMovie = computed(() => removedMovieData(props));
const facts = computed(
    () => removedMovie.value?.userMovies[removedMovie.value.tmdbId],
);
const formatShortDate = useShortDate({ localized: true });
</script>

<template>
    <div class="flex flex-col items-start gap-4 py-16">
        <Head :title="title">
            <meta name="robots" content="noindex" />
        </Head>
        <p class="text-sm font-medium text-muted">{{ status }}</p>
        <h1 class="text-3xl font-semibold tracking-tight">{{ title }}</h1>
        <p class="max-w-prose text-muted">{{ message }}</p>
        <section
            v-if="removedMovie && (facts?.watchlisted || facts?.watched)"
            :data-tmdb-id="removedMovie.tmdbId"
            aria-labelledby="removed-lists-heading"
            class="flex flex-col gap-4 rounded-xl border border-line bg-surface p-5"
        >
            <h2 id="removed-lists-heading" class="text-lg font-semibold">
                It's still in your lists
            </h2>
            <div
                v-if="facts.watchlisted"
                class="flex items-center justify-between gap-6"
            >
                <span class="text-sm">On your Watchlist</span>
                <Button
                    variant="outline"
                    data-slot="remove-watchlist"
                    @click="
                        setWatchlist(
                            removedMovie.tmdbId,
                            false,
                            'movie',
                            $event.currentTarget as HTMLElement,
                        )
                    "
                    >Remove</Button
                >
            </div>
            <div
                v-if="facts.watched && facts.watchedDate"
                class="flex items-center justify-between gap-6"
            >
                <span class="text-sm tabular-nums"
                    >Watched {{ formatShortDate(facts.watchedDate) }}</span
                >
                <Button
                    variant="outline"
                    data-slot="clear-watched"
                    @click="
                        clearWatched(
                            removedMovie.tmdbId,
                            'movie',
                            $event.currentTarget as HTMLElement,
                        )
                    "
                    >Clear</Button
                >
            </div>
        </section>
        <Link
            v-if="removedMovie && $page.props.auth.user"
            :href="watchlist()"
            class="rounded-lg text-accent underline-offset-4 hover:underline"
            >Your Watchlist</Link
        >
        <Link
            :href="home()"
            class="rounded-lg text-accent underline-offset-4 hover:underline"
        >
            Back to Discover
        </Link>
    </div>
</template>
