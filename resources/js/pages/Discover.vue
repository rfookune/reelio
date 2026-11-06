<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { RotateCw } from '@lucide/vue';
import { ref } from 'vue';
import DiscoverHero from '@/components/discover/DiscoverHero.vue';
import type { DiscoverHeroMovie } from '@/components/discover/DiscoverHero.vue';
import DiscoverSection from '@/components/discover/DiscoverSection.vue';
import type { DiscoverSection as Section } from '@/components/discover/DiscoverSection.vue';
import { Button } from '@/components/ui/button';
import type { UserMovies } from '@/types';

defineProps<{
    imageBaseUrl: string;
    hero: DiscoverHeroMovie | null;
    sections: Section[];
    userMovies: UserMovies;
}>();

const retrying = ref(false);

function retry(): void {
    router.reload({
        onStart: () => (retrying.value = true),
        onFinish: () => (retrying.value = false),
    });
}
</script>

<template>
    <div class="pb-12">
        <Head title="Discover" />
        <h1 class="sr-only">Discover</h1>

        <DiscoverHero
            v-if="hero"
            :image-base-url="imageBaseUrl"
            :movie="hero"
            :facts="userMovies[hero.tmdbId]"
        />

        <div
            v-if="sections.length > 0"
            class="flex flex-col gap-12"
            :class="{ 'pt-10': !hero }"
        >
            <DiscoverSection
                v-for="section in sections"
                :key="section.kind"
                :image-base-url="imageBaseUrl"
                :section="section"
                :user-movies="userMovies"
            />
        </div>

        <section
            v-else
            data-slot="discover-unavailable"
            class="mx-auto mt-16 flex max-w-md flex-col items-center gap-4 rounded-xl border border-line bg-surface p-8 text-center"
        >
            <h2 class="text-lg font-semibold">
                We can't reach our movie data right now
            </h2>
            <p class="text-sm text-muted">
                Please try again in a moment. You can still search for a movie.
            </p>
            <div class="flex flex-wrap justify-center gap-3">
                <Button type="button" :disabled="retrying" @click="retry">
                    <RotateCw aria-hidden="true" />
                    Retry
                </Button>
                <Button as-child variant="outline">
                    <Link href="/search">Search</Link>
                </Button>
            </div>
        </section>
    </div>
</template>
