<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CastFilter from '@/components/movie/CastFilter.vue';
import type { CastMember } from '@/components/movie/CastGrid.vue';
import CastMatches from '@/components/movie/CastMatches.vue';
import CastTiers from '@/components/movie/CastTiers.vue';
import CrewGroups from '@/components/movie/CrewGroups.vue';
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

const props = defineProps<{
    canonicalUrl: string;
    movieUrl: string;
    imageBaseUrl: string;
    movie: {
        title: string | null;
        year: number | null;
        cast: CastMember[];
        crew: CrewMember[];
        detailsUnavailable: boolean;
    };
}>();

/** The filter's text. Filtering replaces the tiers with the list of matches. */
const filter = ref('');
const hasCast = computed(
    () => !props.movie.detailsUnavailable && props.movie.cast.length > 0,
);
const filtering = computed(() => filter.value.trim() !== '');
const matches = computed(() => castMatching(props.movie.cast, filter.value));
</script>

<template>
    <div class="flex flex-col gap-8 py-8">
        <Head :title="`Full cast · ${movie.title ?? 'Movie'}`">
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
                                    :href="movieUrl"
                                    data-slot="back-to-movie"
                                >
                                    {{ movie.title ?? 'Movie'
                                    }}<template v-if="movie.year">
                                        ({{ movie.year }})</template
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
                    <span v-if="movie.cast.length > 0" class="text-muted"
                        >· {{ movie.cast.length }}</span
                    >
                </h1>
            </div>
            <CastFilter v-if="hasCast" v-model="filter" />
        </div>

        <p
            v-if="movie.detailsUnavailable"
            data-slot="cast-unavailable"
            class="rounded-xl border border-line bg-surface p-5 text-sm text-muted"
        >
            Cast is temporarily unavailable.
        </p>
        <p
            v-else-if="movie.cast.length === 0"
            data-slot="cast-empty"
            class="rounded-xl border border-line bg-surface p-5 text-sm text-muted"
        >
            No cast is listed for this movie.
        </p>
        <CastMatches
            v-else-if="filtering"
            :image-base-url="imageBaseUrl"
            :matches="matches"
            :query="filter"
        />
        <CastTiers v-else :image-base-url="imageBaseUrl" :cast="movie.cast" />

        <CrewGroups
            v-if="!filtering && !movie.detailsUnavailable"
            :crew="movie.crew"
        />
    </div>
</template>
