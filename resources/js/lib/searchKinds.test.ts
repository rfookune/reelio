import { describe, expect, it } from 'vitest';
import { cardKey, tvBadgeLabel } from '@/lib/searchKinds';

const cards = [
    { kind: 'movie' as const, tmdbId: 1 },
    { kind: 'tv' as const, tmdbId: 1 },
    { kind: 'tv' as const, tmdbId: 2 },
];

describe('search cards', () => {
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
