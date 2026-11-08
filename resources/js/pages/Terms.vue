<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LegalPage from '@/components/legal/LegalPage.vue';
import Placeholder from '@/components/legal/Placeholder.vue';
import { about } from '@/routes';
import {
    CONTACT_EMAIL,
    GOVERNING_STATE,
    POLICY_LAST_UPDATED,
} from '@/lib/legal';
</script>

<template>
    <LegalPage
        title="Terms"
        description="The terms for using Reelio: free, non-commercial and in beta."
        :last-updated="POLICY_LAST_UPDATED"
    >
        <h2>In plain English</h2>
        <p>
            Reelio is free and permanently non-commercial. It's a beta, so
            things may change. Please don't scrape it. Ratings and streaming
            information may be out of date. You must be 13 or older. These terms
            are a draft written by the owner, not reviewed by a lawyer.
        </p>

        <h2>Free and non-commercial</h2>
        <p>Reelio is free to use and is permanently non-commercial.</p>

        <h2>Beta</h2>
        <p>
            Reelio is a beta. Features may change or be removed. If Reelio
            closes, we'll email you at least 30 days beforehand, and you can ask
            for your data.
        </p>

        <h2>Who can use Reelio</h2>
        <p>You must be 13 or older to create an account.</p>

        <h2>Acceptable use</h2>
        <p>
            Don't scrape, bulk-extract or otherwise access Reelio by automated
            means. We may close accounts that abuse the service.
        </p>

        <h2>No guarantees</h2>
        <p>
            We don't guarantee that streaming availability or ratings are
            current or correct. Check the streaming service before you rely on
            them.
        </p>

        <h2>Data and attribution</h2>
        <p>
            Movie data and images belong to TMDB, IMDb and the rights holders,
            under their own notices. Streaming data is provided by JustWatch.
            Reelio's name belongs to its owner. See the
            <Link :href="about()">About page</Link> for the notices.
        </p>

        <h2>Limited liability</h2>
        <p>
            Reelio is provided as is, without warranties. To the fullest extent
            the law allows, the owner isn't liable for losses arising from using
            it.
        </p>

        <h2>Governing law</h2>
        <p>
            These terms are governed by the laws of
            <Placeholder>{{ GOVERNING_STATE }}</Placeholder
            >, without overriding any consumer rights you have where you live.
        </p>

        <h2>Changes and contact</h2>
        <p>
            For material changes we'll email verified users 14 days ahead.
            Questions: <Placeholder>{{ CONTACT_EMAIL }}</Placeholder
            >.
        </p>
    </LegalPage>
</template>
