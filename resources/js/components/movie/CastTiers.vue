<script setup lang="ts">
import { User } from '@lucide/vue';
import { computed } from 'vue';
import CastGrid from '@/components/movie/CastGrid.vue';
import type { CastMember } from '@/components/movie/CastGrid.vue';
import { castCaption, STARRING_COUNT, SUPPORTING_COUNT } from '@/lib/cast';
import { isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';

/**
 * The whole billed cast in tiers by billing order: the Starring cast on the same profile cards as the Movie page,
 * the Supporting cast as small round photos, and everyone else as plain text, so a cast of a hundred loads a few
 * dozen photos, not all of them.
 */
const props = defineProps<{ imageBaseUrl: string; cast: CastMember[] }>();

const starring = computed(() => props.cast.slice(0, STARRING_COUNT));
const supporting = computed(() =>
    props.cast.slice(STARRING_COUNT, STARRING_COUNT + SUPPORTING_COUNT),
);
const others = computed(() =>
    props.cast.slice(STARRING_COUNT + SUPPORTING_COUNT),
);
</script>

<template>
    <div class="flex flex-col gap-10">
        <section aria-labelledby="cast-starring">
            <h2
                id="cast-starring"
                class="mb-4 text-sm font-medium tracking-wide text-muted uppercase"
            >
                Starring
            </h2>
            <CastGrid :image-base-url="imageBaseUrl" :cast="starring" />
        </section>

        <section v-if="supporting.length > 0" aria-labelledby="cast-supporting">
            <h2
                id="cast-supporting"
                class="mb-4 text-sm font-medium tracking-wide text-muted uppercase"
            >
                Supporting
            </h2>
            <ul class="grid grid-cols-4 gap-3 sm:grid-cols-6 md:grid-cols-8">
                <li
                    v-for="member in supporting"
                    :key="member.id"
                    class="min-w-0"
                >
                    <div
                        class="aspect-square overflow-hidden rounded-full bg-raised text-muted"
                    >
                        <img
                            v-if="isSafeTmdbPath(member.profilePath)"
                            :src="
                                tmdbImageUrl(
                                    imageBaseUrl,
                                    185,
                                    member.profilePath,
                                )
                            "
                            alt=""
                            width="92"
                            height="92"
                            loading="lazy"
                            decoding="async"
                            class="size-full object-cover"
                        />
                        <div
                            v-else
                            data-slot="person-fallback"
                            aria-hidden="true"
                            class="grid size-full place-items-center"
                        >
                            <User class="size-6" :stroke-width="1.5" />
                        </div>
                    </div>
                    <p
                        class="mt-1.5 text-center text-xs leading-tight font-medium"
                    >
                        {{ member.name }}
                    </p>
                    <p
                        v-if="castCaption(member)"
                        class="truncate text-center text-xs text-muted"
                    >
                        {{ castCaption(member) }}
                    </p>
                </li>
            </ul>
        </section>

        <section v-if="others.length > 0" aria-labelledby="cast-others">
            <h2
                id="cast-others"
                class="mb-4 text-sm font-medium tracking-wide text-muted uppercase"
            >
                Also appearing · {{ others.length }}
            </h2>
            <ul class="columns-1 gap-8 text-sm sm:columns-2 lg:columns-3">
                <li
                    v-for="member in others"
                    :key="member.id"
                    class="break-inside-avoid py-0.5"
                >
                    <span class="font-medium">{{ member.name }}</span>
                    <span v-if="castCaption(member)" class="text-muted">
                        · {{ castCaption(member) }}</span
                    >
                </li>
            </ul>
        </section>
    </div>
</template>
