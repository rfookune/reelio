<script setup lang="ts">
import { computed } from 'vue';
import { formatDate, formatRuntime } from '@/lib/tmdb';

const props = defineProps<{
    directors: string[];
    writers: string[];
    releaseDate: string | null;
    usReleaseDate: string | null;
    runtime: number | null;
    certification: string | null;
    genres: string[];
    title: string | null;
    originalTitle: string | null;
    originalLanguage: string | null;
    unavailable?: boolean;
}>();

const rows = computed(() => {
    const released = props.usReleaseDate ?? props.releaseDate;
    const showOriginalTitle =
        props.originalTitle !== null &&
        props.originalTitle !== '' &&
        props.originalTitle !== props.title;

    const fallbackText = props.unavailable ? 'Temporarily unavailable' : '';

    return [
        {
            label: props.directors.length > 1 ? 'Directors' : 'Director',
            value: props.directors.join(', ') || fallbackText,
        },
        { label: 'Writers', value: props.writers.join(', ') || fallbackText },
        {
            label: props.usReleaseDate ? 'Released (US)' : 'Released',
            value: formatDate(released) ?? '',
        },
        { label: 'Runtime', value: formatRuntime(props.runtime) ?? '' },
        { label: 'Rated', value: props.certification ?? fallbackText },
        { label: 'Genres', value: props.genres.join(', ') },
        {
            label: 'Original title',
            value: showOriginalTitle ? (props.originalTitle ?? '') : '',
            lang: props.originalLanguage ?? undefined,
        },
    ].filter((row) => row.value !== '');
});
</script>

<template>
    <section
        v-if="rows.length > 0"
        data-slot="movie-details"
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
