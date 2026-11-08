<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CastFilter from '@/components/casts/CastFilter.vue';
import type { CastMember } from '@/components/casts/CastGrid.vue';
import CastMatches from '@/components/casts/CastMatches.vue';
import CastTiers from '@/components/casts/CastTiers.vue';
import CrewGroups from '@/components/casts/CrewGroups.vue';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { castMatching } from '@/lib/cast';
import type { CrewMember } from '@/lib/crew';
import { home } from '@/routes';

/**
 * A Title's Full Cast page: the whole billed cast in tiers with a name filter, then the crew. Shared by the Movie and
 * TV Show pages, which differ only in the noun and where the breadcrumb leads back to.
 */
const props = defineProps<{
    canonicalUrl: string;
    /** The Title's own page, where the breadcrumb leads back to. */
    titleUrl: string;
    /** What the Title is called in copy: "Movie" or "TV show". */
    noun: string;
    imageBaseUrl: string;
    title: {
        name: string | null;
        year: number | null;
        cast: CastMember[];
        crew: CrewMember[];
        detailsUnavailable: boolean;
    };
}>();

/** The filter's text. Filtering replaces the tiers with the list of matches. */
const filter = ref('');
const hasCast = computed(
    () => !props.title.detailsUnavailable && props.title.cast.length > 0,
);
const filtering = computed(() => filter.value.trim() !== '');
const matches = computed(() => castMatching(props.title.cast, filter.value));
</script>

<template>
    <div class="flex flex-col gap-8 py-8">
        <Head :title="`Full cast · ${title.name ?? noun}`">
            <link rel="canonical" :href="canonicalUrl" />
        </Head>

        <div
            class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
        >
            <div class="flex flex-col gap-3">
                <Breadcrumb>
                    <BreadcrumbList>
                        <BreadcrumbItem>
                            <BreadcrumbLink as-child>
                                <Link :href="home()">Discover</Link>
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem>
                            <BreadcrumbLink as-child>
                                <Link
                                    :href="titleUrl"
                                    data-slot="back-to-title"
                                >
                                    {{ title.name ?? noun
                                    }}<template v-if="title.year">
                                        ({{ title.year }})</template
                                    >
                                </Link>
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem>
                            <BreadcrumbPage>Full cast</BreadcrumbPage>
                        </BreadcrumbItem>
                    </BreadcrumbList>
                </Breadcrumb>
                <h1 class="text-3xl font-semibold tracking-tight tabular-nums">
                    Full cast
                    <span v-if="title.cast.length > 0" class="text-muted"
                        >· {{ title.cast.length }}</span
                    >
                </h1>
            </div>
            <CastFilter v-if="hasCast" v-model="filter" />
        </div>

        <p
            v-if="title.detailsUnavailable"
            data-slot="cast-unavailable"
            class="rounded-xl border border-line bg-surface p-5 text-sm text-muted"
        >
            Cast is temporarily unavailable.
        </p>
        <p
            v-else-if="title.cast.length === 0"
            data-slot="cast-empty"
            class="rounded-xl border border-line bg-surface p-5 text-sm text-muted"
        >
            No cast is listed for this {{ noun.toLowerCase() }}.
        </p>
        <CastMatches
            v-else-if="filtering"
            :image-base-url="imageBaseUrl"
            :matches="matches"
            :query="filter"
        />
        <CastTiers v-else :image-base-url="imageBaseUrl" :cast="title.cast" />

        <CrewGroups
            v-if="!filtering && !title.detailsUnavailable"
            :crew="title.crew"
        />
    </div>
</template>
