<script setup lang="ts">
import { User } from '@lucide/vue';
import { castCaption } from '@/lib/cast';
import { isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';

export type CastMember = {
    id: number;
    name: string;
    character: string | null;
    profilePath: string | null;
    /** A TV Show cast member's total Episode count across the whole run. */
    episodeCount?: number | null;
};

defineProps<{
    imageBaseUrl: string;
    cast: CastMember[];
}>();
</script>

<template>
    <ul class="grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-6">
        <li v-for="member in cast" :key="member.id" class="min-w-0">
            <div
                class="aspect-[2/3] overflow-hidden rounded-lg bg-raised text-muted"
            >
                <img
                    v-if="isSafeTmdbPath(member.profilePath)"
                    :src="tmdbImageUrl(imageBaseUrl, 185, member.profilePath)"
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
            <p v-if="castCaption(member)" class="text-xs text-muted">
                {{ castCaption(member) }}
            </p>
        </li>
    </ul>
</template>
