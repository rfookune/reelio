export type CardKind = 'movie' | 'tv';

type Kinded = { kind: CardKind; tmdbId: number };

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
