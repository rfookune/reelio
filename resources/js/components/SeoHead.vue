<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { serializeJsonLd } from '@/lib/seo';

/**
 * The one `<Head>` helper for public pages: the title, the description, the Open Graph and Twitter tags, the
 * canonical URL and, for a Movie, its JSON-LD, which the component serializes so it can't close its own tag. The page and image default to the shared current URL and static card.
 * The title is the page's own, and the app adds " · {site name}" to the document title.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
        image?: string | null;
        canonicalUrl?: string | null;
        type?: 'website' | 'video.movie';
        jsonLd?: Record<string, unknown> | null;
    }>(),
    { image: null, canonicalUrl: null, type: 'website', jsonLd: null },
);

const page = usePage();
const siteName = computed(() => page.props.name);
const url = computed(() => props.canonicalUrl ?? page.props.seo.url);
const image = computed(() => props.image ?? page.props.seo.card);
const jsonLdText = computed(() =>
    props.jsonLd === null ? null : serializeJsonLd(props.jsonLd),
);
</script>

<template>
    <Head :title="title">
        <meta
            head-key="description"
            name="description"
            :content="description"
        />
        <link head-key="canonical" rel="canonical" :href="url" />
        <meta
            head-key="og:site_name"
            property="og:site_name"
            :content="siteName"
        />
        <meta head-key="og:type" property="og:type" :content="type" />
        <meta head-key="og:title" property="og:title" :content="title" />
        <meta
            head-key="og:description"
            property="og:description"
            :content="description"
        />
        <meta head-key="og:url" property="og:url" :content="url" />
        <meta head-key="og:image" property="og:image" :content="image" />
        <meta
            head-key="twitter:card"
            name="twitter:card"
            content="summary_large_image"
        />
        <meta head-key="twitter:title" name="twitter:title" :content="title" />
        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="description"
        />
        <meta head-key="twitter:image" name="twitter:image" :content="image" />
        <component
            :is="'script'"
            v-if="jsonLd"
            head-key="json-ld"
            type="application/ld+json"
            >{{ jsonLd }}</component
        >
    </Head>
</template>
