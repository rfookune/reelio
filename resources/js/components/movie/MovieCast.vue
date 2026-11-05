<script setup lang="ts">
import { User } from '@lucide/vue';
import { isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';

export type CastMember = {
    id: number;
    name: string;
    character: string | null;
    profilePath: string | null;
};

defineProps<{
    imageBaseUrl: string;
    cast: CastMember[];
    unavailable?: boolean;
}>();
</script>

<template>
    <section
        v-if="unavailable || cast.length > 0"
        aria-labelledby="cast-heading"
    >
        <h2 id="cast-heading" class="mb-4 text-xl font-semibold">Cast</h2>
        <p
            v-if="unavailable"
            data-slot="cast-unavailable"
            class="rounded-xl border border-line bg-surface p-5 text-sm text-muted"
        >
            Cast is temporarily unavailable.
        </p>
        <ul v-else class="grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-6">
            <li v-for="member in cast" :key="member.id" class="min-w-0">
                <div
                    class="aspect-[2/3] overflow-hidden rounded-lg bg-raised text-muted"
                >
                    <img
                        v-if="isSafeTmdbPath(member.profilePath)"
                        :src="
                            tmdbImageUrl(imageBaseUrl, 185, member.profilePath)
                        "
                        alt=""
                        width="185"
                        height="278"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover"
                    />
                    <div
                        v-else
                        data-slot="person-fallback"
                        aria-hidden="true"
                        class="flex size-full items-center justify-center"
                    >
                        <User class="size-8" :stroke-width="1.5" />
                    </div>
                </div>
                <p class="mt-2 text-sm leading-tight font-medium">
                    {{ member.name }}
                </p>
                <p v-if="member.character" class="text-xs text-muted">
                    {{ member.character }}
                </p>
            </li>
        </ul>
    </section>
</template>
