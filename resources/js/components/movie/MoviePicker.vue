<script setup lang="ts">
import { Link, useHttp } from '@inertiajs/vue3';
import { Dices, LoaderCircle, X } from '@lucide/vue';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
    DialogTrigger,
} from 'reka-ui';
import { computed, ref } from 'vue';
import ExternalRatings from '@/components/ExternalRatings.vue';
import type { ExternalRating } from '@/components/ExternalRatings.vue';
import StreamingServiceLogo from '@/components/movie/StreamingServiceLogo.vue';
import ShimmerSkeleton from '@/components/ShimmerSkeleton.vue';
import TmdbImage from '@/components/TmdbImage.vue';
import { Button } from '@/components/ui/button';
import { announce } from '@/composables/useAnnouncer';
import { formatRuntime } from '@/lib/tmdb';
import { markAsWatched } from '@/lib/userMovieActions';
import { home } from '@/routes';
import { pick as pickRoute } from '@/routes/watchlist';

type PickedMovie = {
    tmdbId: number;
    title: string;
    year: number | null;
    runtime: number | null;
    genres: string[];
    overview: string | null;
    posterPath: string | null;
    ratings: ExternalRating[];
    href: string;
    whereToWatch: {
        country: string;
        countryName: string;
        groups: {
            type: 'stream' | 'free';
            label: string;
            caption: string | null;
            services: { id: number; name: string; logoPath: string | null }[];
        }[];
        updated: string | null;
        outOfDate: boolean;
    };
};

type Pick = {
    /** How many candidates matched the genres, the previous pick included. */
    poolSize: number;
    /** The genres at least one candidate has. */
    genres: { id: number; name: string }[];
    movie: PickedMovie | null;
};

const props = defineProps<{
    imageBaseUrl: string;
    /** Whether the Watchlist holds anything to pick from; "Pick for me" is hidden otherwise. */
    available: boolean;
}>();
const emit = defineEmits<{
    /** The dialog closed after "Pick for me" left the page, so focus needs somewhere else to go. */
    triggerRemoved: [];
}>();

const TOO_FAST = "You're going too fast. Try again in a moment.";
const CANT_PICK = "We couldn't pick a movie. Try again.";

const open = ref(false);
const http = useHttp<{ genres: number[]; previous: number | null }, Pick>({
    genres: [],
    previous: null,
});
const selectedGenres = ref<number[]>([]);
/** The genres the shown pick was made with, which a failed genre change returns to. */
let answeredGenres: number[] = [];
const result = ref<Pick | null>(null);
const loading = ref(false);
const failure = ref<string | null>(null);
const movie = computed(() => result.value?.movie ?? null);
const stage = computed(() => {
    if (movie.value) {
        return `movie-${movie.value.tmdbId}`;
    }

    if (result.value === null) {
        return failure.value === null ? 'loading' : 'failed';
    }

    return selectedGenres.value.length > 0 ? 'no-match' : 'empty';
});
let latestRequest = 0;

/**
 * Asks the server for a pick. A newer request cancels an older one, so a late answer never replaces a newer one.
 * A failure keeps the current pick and the genres it was made with, and explains inline, through the live region
 * too.
 */
async function requestPick(previous: number | null): Promise<void> {
    const request = ++latestRequest;
    let status: number | null = null;
    http.cancel();
    const genres = [...selectedGenres.value];
    http.genres = genres;
    http.previous = previous;
    loading.value = true;
    failure.value = null;

    try {
        const response = await http.get(pickRoute.url(), {
            onHttpException: (httpResponse) => {
                status = httpResponse.status;
            },
        });

        if (request !== latestRequest || response === undefined) {
            return;
        }

        result.value = response;
        answeredGenres = genres;

        if (response.movie) {
            const { title, year } = response.movie;
            announce(`Picked: ${title}${year === null ? '' : ` (${year})`}`);
        }
    } catch {
        if (request === latestRequest) {
            failure.value = status === 429 ? TOO_FAST : CANT_PICK;
            selectedGenres.value = [...answeredGenres];
            announce(failure.value);
        }
    } finally {
        if (request === latestRequest) {
            loading.value = false;
        }
    }
}

/** Each opening starts afresh: no genres, and a new pick. */
function changeOpen(isOpen: boolean): void {
    open.value = isOpen;

    if (isOpen) {
        selectedGenres.value = [];
        answeredGenres = [];
        result.value = null;
        void requestPick(null);
    } else {
        latestRequest++;
        http.cancel();
        loading.value = false;
    }
}

function toggleGenre(id: number): void {
    selectedGenres.value = selectedGenres.value.includes(id)
        ? selectedGenres.value.filter((genre) => genre !== id)
        : [...selectedGenres.value, id];
    void requestPick(null);
}

function clearGenres(): void {
    selectedGenres.value = [];
    void requestPick(null);
}

/** Closes the dialog; the card leaves the Watchlist and the toast's Undo restores it without reopening the dialog. */
function markPickAsWatched(): void {
    if (!movie.value) {
        return;
    }

    const tmdbId = movie.value.tmdbId;
    changeOpen(false);
    markAsWatched(tmdbId, 'picker', null);
}

/** Focus returns to "Pick for me", unless marking the last candidate as watched took it off the page. */
function returnFocus(event: Event): void {
    if (!props.available) {
        event.preventDefault();
        emit('triggerRemoved');
    }
}
</script>

<template>
    <DialogRoot :open="open" @update:open="changeOpen">
        <DialogTrigger v-if="available" as-child>
            <Button
                data-slot="pick-for-me"
                variant="outline"
                class="h-11 md:h-9"
            >
                <Dices aria-hidden="true" />Pick for me
            </Button>
        </DialogTrigger>
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-black/60 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none"
            />
            <DialogContent
                data-slot="movie-picker"
                class="fixed inset-x-0 bottom-0 z-50 flex max-h-[90dvh] flex-col gap-5 overflow-y-auto rounded-t-2xl border-t border-line bg-background p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-xl data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:slide-out-to-bottom-8 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:slide-in-from-bottom-8 motion-reduce:animate-none sm:inset-0 sm:m-auto sm:h-fit sm:max-h-[85dvh] sm:w-[calc(100%-2rem)] sm:max-w-xl sm:rounded-xl sm:border sm:p-6"
                @close-auto-focus="returnFocus"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <DialogTitle class="text-xl font-semibold"
                            >Pick for me</DialogTitle
                        >
                        <DialogDescription class="text-sm text-muted"
                            >A random movie from your
                            Watchlist.</DialogDescription
                        >
                    </div>
                    <DialogClose
                        class="-mt-1 -mr-1 flex size-11 shrink-0 items-center justify-center rounded-full text-muted hover:bg-raised hover:text-foreground md:size-9"
                    >
                        <X class="size-5" aria-hidden="true" />
                        <span class="sr-only">Close</span>
                    </DialogClose>
                </div>

                <fieldset
                    v-if="result && result.genres.length > 0"
                    class="flex flex-col gap-2"
                >
                    <legend class="mb-2 text-sm font-medium">
                        Genres
                        <span class="font-normal text-muted">(optional)</span>
                    </legend>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="genre in result.genres"
                            :key="genre.id"
                            type="button"
                            data-slot="picker-genre"
                            :data-genre-id="genre.id"
                            :aria-pressed="selectedGenres.includes(genre.id)"
                            class="min-h-9 rounded-full border border-control px-3 text-sm transition-colors aria-pressed:border-foreground aria-pressed:bg-foreground aria-pressed:text-background"
                            @click="toggleGenre(genre.id)"
                        >
                            {{ genre.name }}
                        </button>
                    </div>
                </fieldset>

                <div class="grid">
                    <Transition
                        enter-active-class="transition-opacity duration-200 ease-out motion-reduce:transition-none"
                        leave-active-class="transition-opacity duration-200 ease-in motion-reduce:transition-none"
                        enter-from-class="opacity-0"
                        leave-to-class="opacity-0"
                    >
                        <article
                            v-if="movie"
                            :key="stage"
                            data-slot="picked-movie"
                            class="col-start-1 row-start-1 grid grid-cols-[6rem_1fr] gap-x-4 gap-y-3 sm:grid-cols-[9rem_1fr]"
                        >
                            <div
                                class="self-start overflow-hidden rounded-lg sm:row-span-3"
                            >
                                <TmdbImage
                                    :base-url="imageBaseUrl"
                                    :path="movie.posterPath"
                                    alt=""
                                    :placeholder="movie.title"
                                    :widths="[185, 342]"
                                    sizes="(min-width: 640px) 144px, 96px"
                                    loading="eager"
                                />
                            </div>
                            <div class="flex min-w-0 flex-col gap-1.5">
                                <h3 class="text-lg leading-tight font-semibold">
                                    {{ movie.title }}
                                </h3>
                                <p
                                    v-if="
                                        movie.year !== null ||
                                        formatRuntime(movie.runtime)
                                    "
                                    class="text-sm text-muted tabular-nums"
                                >
                                    {{
                                        [
                                            movie.year,
                                            formatRuntime(movie.runtime),
                                        ]
                                            .filter((part) => part)
                                            .join(' · ')
                                    }}
                                </p>
                                <p
                                    v-if="movie.genres.length > 0"
                                    class="text-sm text-muted"
                                >
                                    {{ movie.genres.join(', ') }}
                                </p>
                                <ExternalRatings :ratings="movie.ratings" />
                            </div>
                            <p
                                v-if="movie.overview"
                                class="col-span-2 line-clamp-3 text-sm sm:col-span-1 sm:col-start-2"
                            >
                                {{ movie.overview }}
                            </p>
                            <section
                                aria-label="Where to watch"
                                class="col-span-2 flex flex-wrap gap-x-6 gap-y-3 sm:col-span-1 sm:col-start-2"
                            >
                                <div
                                    v-for="group in movie.whereToWatch.groups"
                                    :key="group.type"
                                    :data-group="group.type"
                                    class="flex flex-col gap-1.5"
                                >
                                    <p class="text-xs font-medium text-muted">
                                        {{ group.caption ?? group.label }}
                                    </p>
                                    <ul class="flex flex-wrap gap-2">
                                        <li
                                            v-for="service in group.services"
                                            :key="service.id"
                                            data-slot="streaming-service"
                                        >
                                            <StreamingServiceLogo
                                                :image-base-url="imageBaseUrl"
                                                :service="service"
                                            />
                                        </li>
                                    </ul>
                                </div>
                                <p
                                    v-if="
                                        movie.whereToWatch.groups.length === 0
                                    "
                                    class="w-full text-sm"
                                >
                                    We don't have streaming information for this
                                    movie in
                                    {{ movie.whereToWatch.countryName }}.
                                </p>
                                <p
                                    data-slot="availability-credit"
                                    class="w-full text-xs text-muted"
                                >
                                    Streaming data from JustWatch<template
                                        v-if="movie.whereToWatch.updated"
                                    >
                                        ·
                                        {{
                                            movie.whereToWatch.updated
                                        }}</template
                                    ><template
                                        v-if="movie.whereToWatch.outOfDate"
                                    >
                                        ·
                                        <span
                                            class="font-medium text-foreground"
                                            >may be out of date</span
                                        ></template
                                    >
                                </p>
                            </section>
                        </article>
                        <div
                            v-else-if="stage === 'loading'"
                            key="loading"
                            aria-hidden="true"
                            class="col-start-1 row-start-1 flex gap-4"
                        >
                            <ShimmerSkeleton
                                class="aspect-[2/3] w-24 shrink-0 rounded-lg sm:w-36"
                            />
                            <div class="flex flex-1 flex-col gap-3">
                                <ShimmerSkeleton class="h-6 w-3/4 rounded" />
                                <ShimmerSkeleton class="h-4 w-1/2 rounded" />
                                <ShimmerSkeleton class="h-4 w-2/3 rounded" />
                            </div>
                        </div>
                        <div
                            v-else-if="stage === 'empty'"
                            key="empty"
                            class="col-start-1 row-start-1 flex flex-col items-start gap-2"
                        >
                            <p class="text-sm">
                                Your Watchlist is empty. Save a few Movies to
                                pick from.
                            </p>
                            <Link
                                :href="home()"
                                class="text-sm text-accent underline-offset-4 hover:underline"
                                >Discover movies</Link
                            >
                        </div>
                        <div
                            v-else-if="stage === 'no-match'"
                            key="no-match"
                            class="col-start-1 row-start-1 flex flex-col items-start gap-2"
                        >
                            <p class="text-sm">
                                No Movies on your Watchlist match these genres
                            </p>
                            <Button
                                variant="outline"
                                data-slot="picker-genre-clear"
                                @click="clearGenres"
                                >Clear genres</Button
                            >
                        </div>
                    </Transition>
                </div>

                <div
                    v-if="failure"
                    class="flex flex-wrap items-center gap-x-3 gap-y-2"
                >
                    <p class="text-sm text-muted">{{ failure }}</p>
                    <Button
                        v-if="!movie"
                        variant="outline"
                        data-slot="picker-retry"
                        :disabled="loading"
                        @click="requestPick(null)"
                        >Try again</Button
                    >
                </div>
                <p
                    v-else-if="movie && result?.poolSize === 1"
                    class="text-sm text-muted"
                >
                    This is the only match
                </p>

                <div
                    v-if="movie"
                    class="grid grid-cols-2 gap-2 sm:flex sm:justify-end"
                >
                    <Button
                        variant="outline"
                        class="h-11 md:h-9"
                        data-slot="picker-mark-as-watched"
                        @click="markPickAsWatched"
                        >Mark as watched</Button
                    >
                    <Button
                        variant="outline"
                        class="h-11 md:h-9"
                        data-slot="pick-again"
                        :disabled="loading || result?.poolSize === 1"
                        @click="requestPick(movie.tmdbId)"
                        ><LoaderCircle
                            v-if="loading"
                            class="animate-spin"
                            aria-hidden="true"
                        />Pick again</Button
                    >
                    <Button
                        as-child
                        class="order-first col-span-2 h-11 sm:order-last md:h-9"
                    >
                        <Link :href="movie.href">View details</Link>
                    </Button>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
