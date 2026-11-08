<script setup lang="ts">
import { computed } from 'vue';
import { formatDate } from '@/lib/tmdb';
import { tvShowStatus } from '@/lib/tvShows';
const props = defineProps<{
    title: string | null;
    originalTitle: string | null;
    originalLanguage: string | null;
    creators: string[];
    networks: string[];
    status: string | null;
    firstAirDate: string | null;
    lastAirDate: string | null;
    seasonCount: number;
    episodeCount: number;
    certification: string | null;
    genres: string[];
    unavailable: boolean;
}>();
const rows = computed(() => {
    const fallback = props.unavailable ? 'Temporarily unavailable' : '';
    return [
        { label: 'Created by', value: props.creators.join(', ') || fallback },
        { label: 'Network', value: props.networks.join(', ') || fallback },
        { label: 'Status', value: tvShowStatus(props.status) ?? '' },
        { label: 'First aired', value: formatDate(props.firstAirDate) ?? '' },
        { label: 'Last aired', value: formatDate(props.lastAirDate) ?? '' },
        { label: 'Seasons', value: String(props.seasonCount) },
        { label: 'Episodes', value: String(props.episodeCount) },
        { label: 'Rated (US)', value: props.certification ?? fallback },
        { label: 'Genres', value: props.genres.join(', ') },
        {
            label: 'Original title',
            value:
                props.originalTitle !== props.title
                    ? (props.originalTitle ?? '')
                    : '',
            lang: props.originalLanguage ?? undefined,
        },
    ].filter((row) => row.value !== '');
});
</script>
<template>
    <section
        data-slot="tv-show-details"
        aria-labelledby="details-heading"
        class="rounded-xl border border-line bg-surface p-5"
    >
        <h2 id="details-heading" class="mb-3 text-lg font-semibold">Details</h2>
        <dl class="flex flex-col gap-3 text-sm">
            <div v-for="row in rows" :key="row.label">
                <dt class="text-muted">{{ row.label }}</dt>
                <dd :lang="row.lang">{{ row.value }}</dd>
            </div>
        </dl>
    </section>
</template>
