<script setup lang="ts">
import { User } from '@lucide/vue';
import type { CastMember } from '@/components/movie/CastGrid.vue';
import MatchHighlight from '@/components/movie/MatchHighlight.vue';
import { castCaption } from '@/lib/cast';
import type { CastTier } from '@/lib/cast';
import { isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';

/**
 * The cast members who match the filter, as one list in billing order with the tier each is in, in place of the
 * tiers. The count is announced as it changes.
 */
defineProps<{
    imageBaseUrl: string;
    matches: { member: CastMember; tier: CastTier }[];
    query: string;
}>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <p
            data-slot="cast-match-count"
            class="text-sm text-muted tabular-nums"
            aria-live="polite"
        >
            <template v-if="matches.length === 0"
                >No one matches "{{ query.trim() }}".</template
            >
            <template v-else
                >{{ matches.length }}
                {{ matches.length === 1 ? 'match' : 'matches' }}</template
            >
        </p>
        <ol
            v-if="matches.length > 0"
            class="grid gap-x-8 gap-y-4 lg:grid-cols-2"
        >
            <li
                v-for="{ member, tier } in matches"
                :key="member.id"
                class="flex gap-4"
            >
                <span
                    class="aspect-[2/3] w-16 shrink-0 overflow-hidden rounded-lg bg-raised text-muted"
                >
                    <img
                        v-if="isSafeTmdbPath(member.profilePath)"
                        :src="
                            tmdbImageUrl(imageBaseUrl, 185, member.profilePath)
                        "
                        alt=""
                        width="64"
                        height="96"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover"
                    />
                    <span
                        v-else
                        data-slot="person-fallback"
                        aria-hidden="true"
                        class="grid size-full place-items-center"
                    >
                        <User class="size-6" :stroke-width="1.5" />
                    </span>
                </span>
                <span class="flex min-w-0 flex-col justify-center">
                    <span class="text-base leading-tight font-semibold">
                        <MatchHighlight :text="member.name" :query="query" />
                    </span>
                    <span
                        v-if="castCaption(member)"
                        class="mt-0.5 text-sm text-muted"
                    >
                        <MatchHighlight
                            :text="castCaption(member) ?? ''"
                            :query="query"
                        />
                    </span>
                    <span
                        class="mt-1 text-xs tracking-wide text-muted uppercase"
                        >{{ tier }}</span
                    >
                </span>
            </li>
        </ol>
    </div>
</template>
