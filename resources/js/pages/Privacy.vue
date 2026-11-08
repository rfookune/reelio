<script setup lang="ts">
import LegalPage from '@/components/legal/LegalPage.vue';
import LegalTable from '@/components/legal/LegalTable.vue';
import Placeholder from '@/components/legal/Placeholder.vue';
import { CONTACT_EMAIL, OWNER_NAME, POLICY_LAST_UPDATED } from '@/lib/legal';

const cookies = [
    [
        'Session cookie',
        'Keeps you signed in and holds your session',
        'Session; 2 hours idle',
    ],
    ['XSRF-TOKEN', 'Protects forms against forgery', '2 hours'],
    [
        'Remember me',
        'Keeps you signed in, only if you tick it',
        'Up to 400 days or until you sign out',
    ],
    [
        'appearance',
        'Remembers your theme, only when you choose one',
        'One year',
    ],
    [
        'country',
        'Remembers the country you picked in Where to watch, only when you choose one; encrypted and HttpOnly. It holds one supported country code and never your detected country',
        'One year',
    ],
    [
        'YouTube cookies',
        'Set by YouTube, only after you press play on a trailer',
        'Set by YouTube',
    ],
];
const retention = [
    [
        'Account, Watchlist, Watched',
        'Until you delete your account (deleted immediately), then up to 7 days in backups',
    ],
    [
        'Unverified accounts with no activity',
        'Deleted 30 days after registration',
    ],
    [
        'Sessions',
        '2 hours idle; remember-me up to 400 days or until you sign out',
    ],
    ['Password-reset tokens', '60 minutes'],
    [
        'A pending guest action, the latest Undo',
        'In your session: 30 minutes, latest only',
    ],
    [
        'Analytics events',
        '13 months, then anonymous counts; deleted with your account',
    ],
    ['Failed background jobs', '7 days'],
    ['App logs, Cloud access logs, CSP reports', '7 days'],
    ['Rate-limit counters', 'Up to 1 hour'],
    ['Nightwatch monitoring', '14 days'],
    ['Sentry and Resend', "Each service's plan retention"],
    ['Catalog data', 'Contains no personal data'],
];
const thirdParties = [
    ['Laravel Cloud (AWS, Cloudflare)', 'Every request and all stored data'],
    ['Resend', 'Your email address and the emails we send'],
    ['Nightwatch', 'Redacted server data with your user id'],
    ['Sentry', 'Browser errors, without your IP address'],
    [
        'image.tmdb.org',
        'Every visitor’s IP address, to load posters and photos',
    ],
    ['YouTube', 'Only after you press play on a trailer'],
    ['TMDB’s API, IMDb, JustWatch', 'No personal data'],
];
</script>

<template>
    <LegalPage
        title="Privacy"
        description="What Reelio collects, why, who receives it and how long it's kept."
        :last-updated="POLICY_LAST_UPDATED"
    >
        <h2>In plain English</h2>
        <p>
            Reelio collects as little as it can: your name, email and password
            if you make an account, and your Watchlist and Watched lists. There
            is no consent banner, because we set only the cookies needed to run
            the site or remember your choices. You can edit your details or
            delete your account yourself, whenever you like. This policy is a
            draft written by the owner, not reviewed by a lawyer, and doesn't
            claim compliance with any particular law.
        </p>

        <h2>Who is responsible</h2>
        <p>
            The controller of your data is
            <Placeholder>{{ OWNER_NAME }}</Placeholder
            >, an individual in the United States. Contact:
            <Placeholder>{{ CONTACT_EMAIL }}</Placeholder
            >.
        </p>

        <h2>Where your data is kept</h2>
        <p>
            Hosting and Nightwatch monitoring are in the United States, and
            Sentry is in the European Union. If you use Reelio from elsewhere,
            your data is transferred to the United States; transfers rely on
            each vendor's data processing agreement. Reelio has no
            representative in the EU or UK during the beta.
        </p>

        <h2>Why we may use your data</h2>
        <ul>
            <li>
                Contract: your account, Watchlist and Watched lists, so the
                service works for you.
            </li>
            <li>
                Legitimate interests: analytics, security logs, monitoring and
                rate limiting, to keep Reelio working and safe.
            </li>
        </ul>

        <h2>Cookies</h2>
        <LegalTable
            caption="Cookies Reelio sets"
            :headings="['Cookie', 'What it does', 'Kept']"
            :rows="cookies"
        />
        <p>
            All are first-party except YouTube's, which is set only after you
            press play. We show no cookie banner because every other cookie is
            needed to run the site or to remember a choice you made.
        </p>

        <h2>How long we keep it</h2>
        <LegalTable
            caption="How long Reelio keeps data"
            :headings="['Data', 'Kept']"
            :rows="retention"
        />

        <h2>Your rights</h2>
        <p>
            You can change your name, email, password and preferred country, and
            delete your account, in Settings. To ask for a copy of your data,
            write to
            <Placeholder>{{ CONTACT_EMAIL }}</Placeholder
            >; we'll answer within a month with a JSON export. If this policy
            changes materially, we'll email verified users 14 days ahead.
        </p>

        <h2>Who else receives data</h2>
        <LegalTable
            caption="Third parties and what they receive"
            :headings="['Third party', 'Receives']"
            :rows="thirdParties"
        />

        <h2>Your country</h2>
        <p>
            Where to watch picks a country from your connection at the edge, so
            we can show the right streaming services. We don't store it. If you
            choose a country yourself, we remember that choice (in your account,
            or in the country cookie if you're signed out).
        </p>

        <h2>YouTube trailers</h2>
        <p>
            Nothing loads from YouTube until you press play. After that, YouTube
            may set its own cookies and receives your IP address under its own
            policy.
        </p>

        <h2>Backups</h2>
        <p>
            When you delete your account, it's removed immediately. Copies may
            remain in backups for up to 7 days.
        </p>

        <h2>Access logs</h2>
        <p>
            Our host, Laravel Cloud, keeps access logs for 7 days. They may
            include the web address you requested, which can include the words
            you searched for.
        </p>
    </LegalPage>
</template>
