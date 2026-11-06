<script setup lang="ts">
import { Tv } from '@lucide/vue';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { computed } from 'vue';
import { isSafeTmdbPath, tmdbImageUrl } from '@/lib/tmdb';
import { watch as watchPage } from '@/routes/movies';

export type AvailabilityGroup = {
    type: 'stream' | 'free' | 'rent' | 'buy';
    label: string;
    caption: string | null;
    services: { id: number; name: string; logoPath: string | null }[];
};

export type WhereToWatchBlock = {
    country: string;
    countryName: string;
    groups: AvailabilityGroup[];
    updated: string | null;
    outOfDate: boolean;
};

const props = defineProps<{
    imageBaseUrl: string;
    movieId: number;
    block: WhereToWatchBlock;
}>();

const firstGroup = computed(() => props.block.groups[0]?.type);
const watchPageUrl = computed(
    () =>
        watchPage(
            { id: props.movieId },
            { query: { country: props.block.country } },
        ).url,
);
</script>

<template>
    <section
        data-slot="where-to-watch"
        aria-labelledby="where-to-watch-heading"
        class="rounded-xl border border-line bg-surface p-5"
    >
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="where-to-watch-heading" class="text-lg font-semibold">
                Where to watch
            </h2>
            <p class="text-sm text-muted">Showing {{ block.countryName }}</p>
        </div>

        <TabsRoot
            v-if="block.groups.length > 0"
            :default-value="firstGroup"
            activation-mode="automatic"
        >
            <TabsList
                aria-label="Ways to watch"
                class="mb-3 flex flex-wrap gap-1 rounded-lg bg-raised p-1"
            >
                <TabsTrigger
                    v-for="group in block.groups"
                    :key="group.type"
                    :value="group.type"
                    class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium text-muted transition-colors data-[state=active]:bg-surface data-[state=active]:text-foreground data-[state=active]:shadow-sm"
                >
                    {{ group.label }}
                    <span class="ml-1 text-xs">{{
                        group.services.length
                    }}</span>
                </TabsTrigger>
            </TabsList>

            <TabsContent
                v-for="group in block.groups"
                :key="group.type"
                :value="group.type"
                :data-group="group.type"
            >
                <p v-if="group.caption" class="mb-2 text-xs text-muted">
                    {{ group.caption }}
                </p>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="service in group.services"
                        :key="service.id"
                        data-slot="streaming-service"
                        class="flex items-center gap-3"
                    >
                        <img
                            v-if="isSafeTmdbPath(service.logoPath)"
                            :src="
                                tmdbImageUrl(imageBaseUrl, 92, service.logoPath)
                            "
                            :alt="service.name"
                            width="40"
                            height="40"
                            loading="lazy"
                            decoding="async"
                            class="size-10 shrink-0 rounded-lg bg-raised object-cover"
                        />
                        <span
                            v-else
                            aria-hidden="true"
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-raised text-muted"
                        >
                            <Tv class="size-5" :stroke-width="1.5" />
                        </span>
                        <span class="text-sm">{{ service.name }}</span>
                    </li>
                </ul>
            </TabsContent>
        </TabsRoot>

        <p v-else data-slot="availability-empty" class="text-sm">
            We don't have streaming information for this movie in
            {{ block.countryName }}.
        </p>

        <div class="mt-4 flex flex-col gap-2 text-xs text-muted">
            <p>
                Availability changes often and may be incomplete. Not listed
                doesn't mean unavailable.
            </p>
            <p data-slot="availability-credit">
                Streaming data from JustWatch<template v-if="block.updated">
                    · {{ block.updated }}</template
                ><template v-if="block.outOfDate">
                    ·
                    <span class="font-medium text-foreground"
                        >may be out of date</span
                    ></template
                >
            </p>
            <a
                :href="watchPageUrl"
                target="_blank"
                rel="nofollow noopener noreferrer"
                class="w-fit text-sm text-accent underline-offset-4 hover:underline"
            >
                See all watch options on TMDB
            </a>
        </div>
    </section>
</template>
