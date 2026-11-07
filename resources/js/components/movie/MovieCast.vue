<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import CastGrid from '@/components/movie/CastGrid.vue';
import type { CastMember } from '@/components/movie/CastGrid.vue';

export type { CastMember };

defineProps<{
    imageBaseUrl: string;
    cast: CastMember[];
    unavailable?: boolean;
    /** Links the full cast page when it lists more than the members shown. */
    fullCast?: { href: string; count: number } | null;
}>();
</script>

<template>
    <section
        v-if="unavailable || cast.length > 0"
        aria-labelledby="cast-heading"
    >
        <div class="mb-4 flex items-baseline justify-between gap-4">
            <h2 id="cast-heading" class="text-xl font-semibold">Cast</h2>
            <Link
                v-if="!unavailable && fullCast && fullCast.count > cast.length"
                data-slot="full-cast-link"
                :href="fullCast.href"
                class="rounded-lg text-sm text-accent underline-offset-4 hover:underline"
            >
                Full cast ({{ fullCast.count }})
            </Link>
        </div>
        <p
            v-if="unavailable"
            data-slot="cast-unavailable"
            class="rounded-xl border border-line bg-surface p-5 text-sm text-muted"
        >
            Cast is temporarily unavailable.
        </p>
        <CastGrid v-else :image-base-url="imageBaseUrl" :cast="cast" />
    </section>
</template>
