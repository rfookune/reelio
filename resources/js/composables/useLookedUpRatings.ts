import { useHttp } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import type { ExternalRating } from '@/components/ExternalRatings.vue';
import { cardKey } from '@/lib/searchKinds';
import type { CardKind } from '@/lib/searchKinds';
import { ratings as ratingsRoute } from '@/routes/search';

type SearchCard = {
    kind: CardKind;
    tmdbId: number;
    ratings: ExternalRating[];
    /** The page of the search the card is on. */
    page: number;
};

/**
 * The IMDb ratings of search results, which the server can only show for Movies and TV Shows whose IMDb id it already knows. For
 * the rest it is asked, once for each page of results as it appears, after the cards are on screen: a card keeps
 * its TMDB rating until the answer arrives, and shows a placeholder where the rating goes while it waits. Ratings
 * belong to a title, not to a query, so what is found is kept for every search of the session. A request that fails
 * leaves its cards with the rating they came with, and isn't repeated until the page is loaded again.
 */
export function useLookedUpRatings(
    query: () => string,
    cards: () => SearchCard[],
    /** Whether the server looks IMDb ratings up at all; it doesn't while IMDb is switched off. */
    enabled: () => boolean,
) {
    const lookedUp = reactive<Record<string, ExternalRating[]>>({});
    const settled = reactive(new Set<string>());
    const asked = new Set<string>();
    const http = useHttp<
        { q: string; page: number },
        { ratings: Record<string, ExternalRating[]> }
    >({ q: '', page: 1 });
    let queue: Promise<void> = Promise.resolve();

    const needsLookup = (card: SearchCard): boolean =>
        !settled.has(cardKey(card)) &&
        !card.ratings.some((rating) => rating.source === 'imdb');

    /**
     * Requests are made one at a time, since they share one request object. One whose query has been replaced by
     * the time its turn comes is dropped, and may be asked for again if its query comes back.
     */
    function queueLookup(q: string, page: number, ids: string[]): void {
        queue = queue.then(async () => {
            if (q !== query()) {
                asked.delete(`${q}\n${page}`);

                return;
            }

            try {
                http.q = q;
                http.page = page;
                const response = await http.get(ratingsRoute.url());

                for (const [key, ratings] of Object.entries(
                    response?.ratings ?? {},
                )) {
                    lookedUp[key] = ratings;
                }
            } catch {
                // The cards keep the rating they came with.
            } finally {
                ids.forEach((id) => settled.add(id));
            }
        });
    }

    watch(
        [query, cards],
        ([q, shown]) => {
            if (q === '' || !enabled()) {
                return;
            }

            const pages = new Map<number, string[]>();

            for (const card of shown.filter(needsLookup)) {
                pages.set(card.page, [
                    ...(pages.get(card.page) ?? []),
                    cardKey(card),
                ]);
            }

            for (const [page, ids] of pages) {
                const key = `${q}\n${page}`;

                if (!asked.has(key)) {
                    asked.add(key);
                    queueLookup(q, page, ids);
                }
            }
        },
        { immediate: true },
    );

    return {
        /** The card with the rating found for it, if one was. */
        withLookedUpRatings: <T extends SearchCard>(card: T): T => ({
            ...card,
            ratings: lookedUp[cardKey(card)] ?? card.ratings,
        }),
        /** Whether the card is still waiting for an answer about its IMDb rating. */
        isLookingUp: (card: SearchCard): boolean =>
            enabled() && needsLookup(card) && !lookedUp[cardKey(card)],
    };
}
