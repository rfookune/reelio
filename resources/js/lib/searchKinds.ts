export type CardKind = 'movie' | 'tv';
export type KindFilter = 'all' | CardKind;

type Kinded = { kind: CardKind; tmdbId: number };

/** The count behind each chip. */
export function kindCounts(
    cards: { kind: CardKind }[],
): Record<KindFilter, number> {
    return {
        all: cards.length,
        movie: cards.filter((card) => card.kind === 'movie').length,
        tv: cards.filter((card) => card.kind === 'tv').length,
    };
}

export function filterByKind<T extends { kind: CardKind }>(
    cards: T[],
    filter: KindFilter,
): T[] {
    return filter === 'all'
        ? cards
        : cards.filter((card) => card.kind === filter);
}

/** The catalog id for a Movie, and `tv:` and it for a TV Show, as the server keys ratings and results. */
export function cardKey(card: Kinded): string {
    return card.kind === 'tv' ? `tv:${card.tmdbId}` : String(card.tmdbId);
}

/** The badge on a TV Show's poster. */
export function tvBadgeLabel(seasonCount: number | null | undefined): string {
    if (seasonCount === null || seasonCount === undefined) {
        return 'TV';
    }

    return `TV · ${seasonCount} ${seasonCount === 1 ? 'Season' : 'Seasons'}`;
}
