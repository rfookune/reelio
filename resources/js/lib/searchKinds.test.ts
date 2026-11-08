import { describe, expect, it } from 'vitest';
import {
    cardKey,
    filterByKind,
    kindCounts,
    tvBadgeLabel,
} from '@/lib/searchKinds';

const cards = [
    { kind: 'movie' as const, tmdbId: 1 },
    { kind: 'tv' as const, tmdbId: 1 },
    { kind: 'tv' as const, tmdbId: 2 },
];

describe('search kind chips', () => {
    it('counts every card for All and each kind for its chip', () => {
        expect(kindCounts(cards)).toEqual({ all: 3, movie: 1, tv: 2 });
        expect(kindCounts([])).toEqual({ all: 0, movie: 0, tv: 0 });
    });

    it('shows every card for All and only that kind for a chip', () => {
        expect(filterByKind(cards, 'all')).toHaveLength(3);
        expect(filterByKind(cards, 'movie')).toEqual([cards[0]]);
        expect(filterByKind(cards, 'tv')).toEqual([cards[1], cards[2]]);
    });

    it('keeps a Movie and a TV Show with one catalog id apart', () => {
        expect(cardKey(cards[0])).toBe('1');
        expect(cardKey(cards[1])).toBe('tv:1');
    });
});

describe('TV badge', () => {
    it('says how many Seasons when known, else just TV', () => {
        expect(tvBadgeLabel(1)).toBe('TV · 1 Season');
        expect(tvBadgeLabel(3)).toBe('TV · 3 Seasons');
        expect(tvBadgeLabel(null)).toBe('TV');
        expect(tvBadgeLabel(undefined)).toBe('TV');
    });
});
