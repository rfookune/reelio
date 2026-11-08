import { describe, expect, it } from 'vitest';
import { castCaption } from '@/lib/cast';

const actor = { id: 1, name: 'Actor', profilePath: null };

describe('castCaption', () => {
    it('is the role alone for a Movie cast member', () => {
        expect(castCaption({ ...actor, character: 'Dr. Smith' })).toBe(
            'Dr. Smith',
        );
        expect(castCaption({ ...actor, character: null })).toBeNull();
    });

    it('adds the total Episode count for a TV Show cast member', () => {
        expect(
            castCaption({ ...actor, character: 'Dr. Smith', episodeCount: 42 }),
        ).toBe('Dr. Smith · 42 episodes');
    });

    it('is singular for one Episode and still shown without a role', () => {
        expect(
            castCaption({ ...actor, character: 'Guard', episodeCount: 1 }),
        ).toBe('Guard · 1 episode');
        expect(
            castCaption({ ...actor, character: null, episodeCount: 3 }),
        ).toBe('3 episodes');
    });
});
