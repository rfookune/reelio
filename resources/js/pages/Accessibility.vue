<script setup lang="ts">
import LegalPage from '@/components/legal/LegalPage.vue';
import Placeholder from '@/components/legal/Placeholder.vue';
import { ACCESSIBILITY_LAST_REVIEWED, CONTACT_EMAIL } from '@/lib/legal';
</script>

<template>
    <LegalPage
        title="Accessibility"
        description="Reelio aims to meet WCAG 2.2 level AA. Read about our review and known limitations."
    >
        <p>
            Reelio aims to meet the Web Content Accessibility Guidelines (WCAG)
            2.2 at level AA. Our checks cover keyboard use, screen readers, zoom
            and contrast in both light and dark themes.
        </p>
        <p>Last reviewed: {{ ACCESSIBILITY_LAST_REVIEWED }}.</p>

        <h2>Known limitations</h2>
        <ul>
            <li>
                The YouTube trailer player is YouTube's own, and we don't
                control its accessibility.
            </li>
            <li>
                Streaming availability comes from TMDB and JustWatch. We show it
                as given, so it can be incomplete or out of date.
            </li>
        </ul>

        <h2>Tell us about a problem</h2>
        <p>
            If something is hard to use, write to
            <Placeholder>{{ CONTACT_EMAIL }}</Placeholder>
            and tell us the page and what went wrong. We'll reply and aim to fix
            it.
        </p>
    </LegalPage>
</template>
