<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { BookmarkCheck, BookmarkPlus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import SeasonAccordion from '@/components/tv-show/SeasonAccordion.vue';
import UserRating from '@/components/shared/UserRating.vue';
import UpNextCard from '@/components/tv-show/UpNextCard.vue';
import TvShowMarkDialog from '@/components/tv-show/TvShowMarkDialog.vue';
import { useTvShowActions } from '@/composables/useTvShowActions';
import type { WatchedProgress } from '@/composables/useTvShowActions';
import type { ExternalRating } from '@/components/ExternalRatings.vue';
import WhereToWatch from '@/components/shared/WhereToWatch.vue';
import type { WhereToWatchBlock } from '@/components/shared/WhereToWatch.vue';
import { watch as watchPage } from '@/routes/tv-shows';
import SeoHead from '@/components/SeoHead.vue';
import TitleHero from '@/components/shared/TitleHero.vue';
import TitleCast from '@/components/casts/TitleCast.vue';
import type { CastMember } from '@/components/casts/TitleCast.vue';
import TrailerPlayer from '@/components/shared/TrailerPlayer.vue';
import type { Trailer } from '@/components/shared/TrailerPlayer.vue';
import TvShowDetailsCard from '@/components/tv-show/TvShowDetailsCard.vue';
import { describeMovie, shareImage } from '@/lib/seo';
import {
    nextUp,
    tvShowJsonLd,
    tvShowTitle,
    tvShowYearRange,
} from '@/lib/tvShows';
const props = defineProps<{
    progress: WatchedProgress;
    watchlisted: boolean;
    /** The signed-in viewer's own rating of the TV Show, or null. */
    userRating: number | null;
    canonicalUrl: string;
    castUrl: string;
    whereToWatch: WhereToWatchBlock;
    imageBaseUrl: string;
    catalogStatus: 'fresh' | 'stale';
    tvShow: {
        id: number;
        tmdbId: number;
        ratings: ExternalRating[];
        title: string | null;
        originalTitle: string | null;
        originalLanguage: string | null;
        overview: string | null;
        tagline: string | null;
        posterPath: string | null;
        backdropPath: string | null;
        firstAirDate: string | null;
        lastAirDate: string | null;
        status: string | null;
        seasonCount: number;
        episodeCount: number;
        creators: string[];
        networks: string[];
        genres: string[];
        certification: string | null;
        cast: CastMember[];
        castCount: number;
        trailer: Trailer | null;
        detailsUnavailable: boolean;
    };
}>();
const page = usePage();
const user = computed(() => page.props.auth.user);
const {
    progress,
    watchlisted,
    busy,
    toggle,
    toggleWatchlist,
    markSeason,
    clearSeason,
    markTvShow,
    clearTvShow,
} = useTvShowActions(
    () => props.progress,
    () => props.watchlisted,
    () => props.tvShow,
);
const upNext = computed(() => nextUp(progress.value));
const confirmingMark = ref(false);
async function confirmMark() {
    await markTvShow();
    confirmingMark.value = false;
}
const yearRange = computed(() =>
    tvShowYearRange(
        props.tvShow.firstAirDate,
        props.tvShow.lastAirDate,
        props.tvShow.status,
    ),
);
const metadata = computed(() =>
    [
        yearRange.value,
        `${props.tvShow.seasonCount} ${props.tvShow.seasonCount === 1 ? 'Season' : 'Seasons'}`,
    ].filter((value): value is string => value !== null),
);
const image = computed(() =>
    shareImage(
        props.imageBaseUrl,
        props.tvShow.backdropPath,
        props.tvShow.posterPath,
        page.props.seo.card,
    ),
);
const description = computed(() =>
    describeMovie(
        props.tvShow.overview,
        `Find out where to watch ${props.tvShow.title ?? 'this TV show'}, with cast and ratings on Reelio.`,
    ),
);
const jsonLd = computed(() =>
    tvShowJsonLd({
        name: props.tvShow.title ?? 'TV show',
        firstAirDate: props.tvShow.firstAirDate,
        seasonCount: props.tvShow.seasonCount,
        episodeCount: props.tvShow.episodeCount,
        creators: props.tvShow.creators,
        genres: props.tvShow.genres,
        image: image.value,
        description: props.tvShow.overview,
        url: props.canonicalUrl,
    }),
);
</script>
<template>
    <div class="pb-12">
        <SeoHead
            :title="tvShowTitle(tvShow.title, tvShow.firstAirDate)"
            :description="description"
            :canonical-url="canonicalUrl"
            :image="image"
            type="video.tv_show"
            :json-ld="jsonLd"
        />
        <TitleHero
            :image-base-url="imageBaseUrl"
            :title="tvShow.title ?? 'TV show'"
            :original-title="tvShow.originalTitle"
            :original-language="tvShow.originalLanguage"
            :year="null"
            :runtime="null"
            :metadata="metadata"
            :certification="tvShow.certification"
            :genres="tvShow.genres"
            :poster-path="tvShow.posterPath"
            :backdrop-path="tvShow.backdropPath"
            :ratings="tvShow.ratings"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        size="lg"
                        data-slot="watchlist-toggle"
                        :aria-pressed="watchlisted"
                        :disabled="busy"
                        :variant="
                            watchlisted || progress.state === 'Not started'
                                ? 'default'
                                : 'outline'
                        "
                        @click="toggleWatchlist"
                    >
                        <BookmarkCheck
                            v-if="watchlisted"
                            class="fill-current"
                            aria-hidden="true"
                        />
                        <BookmarkPlus v-else aria-hidden="true" />
                        {{
                            watchlisted
                                ? 'On your Watchlist'
                                : 'Add to Watchlist'
                        }}
                    </Button>
                    <Button
                        v-if="progress.state !== 'Completed'"
                        type="button"
                        size="lg"
                        variant="outline"
                        data-slot="tv-show-mark"
                        :disabled="busy"
                        @click="confirmingMark = true"
                    >
                        Mark as watched
                    </Button>
                    <UserRating
                        v-if="user"
                        :title-id="tvShow.id"
                        :title="tvShow.title"
                        :rating="userRating"
                    />
                </div>
                <UpNextCard
                    v-if="upNext"
                    :season="upNext.season"
                    :episode="upNext.episode"
                    :busy="busy"
                    @mark="toggle(upNext.season, upNext.episode)"
                />
                <div
                    data-slot="tv-show-progress"
                    class="flex flex-col gap-2"
                    aria-live="polite"
                >
                    <p
                        :data-slot="
                            progress.state === 'Completed'
                                ? 'completed-chip'
                                : undefined
                        "
                        class="font-semibold"
                        :class="
                            progress.state === 'Completed'
                                ? 'w-fit rounded-full bg-raised px-3 py-1 text-sm'
                                : ''
                        "
                    >
                        {{ progress.state }}
                    </p>
                    <Button
                        v-if="progress.state === 'Completed'"
                        type="button"
                        size="sm"
                        variant="outline"
                        data-slot="tv-show-clear"
                        class="w-fit"
                        :disabled="busy"
                        @click="clearTvShow"
                    >
                        Clear
                    </Button>
                    <p class="text-sm text-muted">
                        {{ progress.watched }} of {{ progress.total }} Episodes
                        watched
                    </p>
                    <progress
                        :value="progress.watched"
                        :max="Math.max(1, progress.total)"
                        aria-label="Episodes watched"
                        class="h-2 w-full max-w-sm overflow-hidden rounded-full bg-raised accent-primary [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-primary [&::-webkit-progress-bar]:rounded-full [&::-webkit-progress-bar]:bg-raised [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-primary"
                    />
                </div>
            </template>
        </TitleHero>
        <div
            data-slot="tv-show-body"
            class="relative -mt-28 grid gap-x-12 gap-y-10 md:-mt-40 lg:grid-cols-[minmax(0,1fr)_20rem]"
        >
            <section
                v-if="tvShow.tagline || tvShow.overview"
                data-slot="tv-show-overview"
                class="order-1 flex max-w-prose flex-col gap-3 lg:col-start-1"
            >
                <p v-if="tvShow.tagline" class="text-lg text-muted italic">
                    {{ tvShow.tagline }}
                </p>
                <h2 class="sr-only">Overview</h2>
                <p v-if="tvShow.overview" class="leading-relaxed">
                    {{ tvShow.overview }}
                </p>
                <p
                    v-if="catalogStatus === 'stale'"
                    role="status"
                    class="text-sm text-muted"
                >
                    Details may be out of date.
                </p>
            </section>
            <aside
                data-slot="tv-show-details-column"
                class="order-2 flex flex-col gap-6 lg:sticky lg:top-24 lg:col-start-2 lg:row-span-4 lg:row-start-1 lg:self-start"
            >
                <WhereToWatch
                    :image-base-url="imageBaseUrl"
                    :watch-url="
                        watchPage(
                            { tmdbId: tvShow.tmdbId },
                            { query: { country: whereToWatch.country } },
                        ).url
                    "
                    kind="tv"
                    :block="whereToWatch"
                />
                <TvShowDetailsCard
                    :title="tvShow.title"
                    :original-title="tvShow.originalTitle"
                    :original-language="tvShow.originalLanguage"
                    :creators="tvShow.creators"
                    :networks="tvShow.networks"
                    :status="tvShow.status"
                    :first-air-date="tvShow.firstAirDate"
                    :last-air-date="tvShow.lastAirDate"
                    :season-count="tvShow.seasonCount"
                    :episode-count="tvShow.episodeCount"
                    :certification="tvShow.certification"
                    :genres="tvShow.genres"
                    :unavailable="tvShow.detailsUnavailable"
                />
            </aside>
            <TrailerPlayer
                v-if="tvShow.trailer"
                :image-base-url="imageBaseUrl"
                :title="tvShow.title ?? 'TV show'"
                :trailer="tvShow.trailer"
                :cover-path="tvShow.backdropPath"
                class="order-3 lg:col-start-1"
            />
            <section
                v-else-if="tvShow.detailsUnavailable"
                data-slot="trailer-unavailable"
                aria-labelledby="trailer-heading"
                class="order-3 lg:col-start-1"
            >
                <h2 id="trailer-heading" class="mb-4 text-xl font-semibold">
                    Trailer
                </h2>
                <p
                    class="rounded-xl border border-line bg-surface p-5 text-sm text-muted"
                >
                    The trailer is temporarily unavailable.
                </p>
            </section>
            <SeasonAccordion
                :progress="progress"
                :busy="busy"
                class="order-5 lg:col-start-1"
                @toggle="toggle"
                @mark-season="markSeason"
                @clear-season="clearSeason"
            />
            <TitleCast
                :image-base-url="imageBaseUrl"
                :cast="tvShow.cast"
                :unavailable="tvShow.detailsUnavailable"
                :full-cast="{ href: castUrl, count: tvShow.castCount }"
                class="order-4 lg:col-start-1"
            />
        </div>
        <TvShowMarkDialog
            v-model:open="confirmingMark"
            :total="progress.total"
            :busy="busy"
            @confirm="confirmMark"
        />
    </div>
</template>
