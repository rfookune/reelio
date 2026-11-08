<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LegalPage from '@/components/legal/LegalPage.vue';
import { privacy } from '@/routes';
import tmdbLogo from '../../images/tmdb-logo.svg';
</script>

<template>
    <LegalPage
        title="About"
        description="Reelio helps you discover movies, see where they stream and keep a Watchlist and a Watched list."
    >
        <p>
            Reelio is a free, non-commercial site for finding something to
            watch. Discover what's trending, read about a movie, see where it
            streams in your country, and keep a Watchlist and a Watched list.
            It's a beta run by one person.
        </p>

        <h2>Where the data comes from</h2>
        <div class="flex flex-col gap-3">
            <a
                href="https://www.themoviedb.org"
                class="flex flex-col items-start gap-3 no-underline"
            >
                <img
                    :src="tmdbLogo"
                    alt="TMDB"
                    width="92"
                    height="12"
                    class="h-4 w-auto"
                />
            </a>
            <p>
                This website uses TMDB and the TMDB APIs but is not endorsed,
                certified, or otherwise approved by TMDB. Movie information,
                posters and cast photos come from
                <a href="https://www.themoviedb.org">TMDB</a>.
            </p>
        </div>
        <p>
            Streaming availability is provided by JustWatch, through TMDB.
            Reelio is not affiliated with JustWatch.
        </p>
        <p>
            Ratings marked IMDb come from
            <a href="https://www.imdb.com">IMDb</a>. Information courtesy of
            IMDb (<a href="https://www.imdb.com">imdb.com</a>), used with its
            non-commercial dataset terms.
        </p>
        <p>
            Trailers play from YouTube only after you press play. See the
            <Link :href="privacy()">Privacy page</Link> for what that means.
        </p>
    </LegalPage>
</template>
