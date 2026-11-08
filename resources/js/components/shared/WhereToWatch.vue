<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import StreamingServiceLogo from '@/components/shared/StreamingServiceLogo.vue';
import { update as updateCountry } from '@/routes/country';
import type { CountryChoice } from '@/types';

export type AvailabilityGroup = {
    type: 'stream' | 'free' | 'rent' | 'buy';
    label: string;
    caption: string | null;
    services: { id: number; name: string; logoPath: string | null }[];
};

export type WhereToWatchBlock = {
    country: string;
    countryName: string;
    countryUnsupported: boolean;
    countries: CountryChoice[];
    groups: AvailabilityGroup[];
    updated: string | null;
    outOfDate: boolean;
};

const props = withDefaults(
    defineProps<{
        imageBaseUrl: string;
        watchUrl: string;
        kind?: 'movie' | 'tv';
        block: WhereToWatchBlock;
    }>(),
    { kind: 'movie' },
);

const firstGroup = computed(() => props.block.groups[0]?.type);
/**
 * Saves the choice, then reloads only this block, so changing the country isn't counted as a new view.
 */
function chooseCountry(country: unknown): void {
    if (typeof country !== 'string' || country === props.block.country) {
        return;
    }

    router.put(
        updateCountry.url(),
        { country },
        { only: ['whereToWatch'], preserveScroll: true },
    );
}
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
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        data-slot="country-selector"
                        :aria-label="`Showing ${block.countryName}, change country`"
                        class="inline-flex items-center gap-1 rounded-md text-sm text-muted transition-colors hover:text-foreground"
                    >
                        Showing {{ block.countryName }}
                        <ChevronDown class="size-4" aria-hidden="true" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuLabel>Country</DropdownMenuLabel>
                    <DropdownMenuRadioGroup
                        :model-value="block.country"
                        @update:model-value="chooseCountry"
                    >
                        <DropdownMenuRadioItem
                            v-for="choice in block.countries"
                            :key="choice.code"
                            :value="choice.code"
                        >
                            {{ choice.name }}
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <p
            v-if="block.countryUnsupported"
            data-slot="country-unsupported"
            class="mb-4 text-sm"
        >
            Showing {{ block.country }} availability. Your country isn't
            supported yet.
        </p>

        <TabsRoot
            v-if="block.groups.length > 0"
            :key="block.country"
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
                        <StreamingServiceLogo
                            :image-base-url="imageBaseUrl"
                            :service="service"
                            name-shown
                        />
                        <span class="text-sm">{{ service.name }}</span>
                    </li>
                </ul>
            </TabsContent>
        </TabsRoot>

        <p v-else data-slot="availability-empty" class="text-sm">
            We don't have streaming information for this
            {{ kind === 'tv' ? 'TV show' : 'movie' }} in
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
                :href="watchUrl"
                target="_blank"
                rel="nofollow noopener noreferrer"
                class="w-fit text-sm text-accent underline-offset-4 hover:underline"
            >
                See all watch options on TMDB
            </a>
        </div>
    </section>
</template>
