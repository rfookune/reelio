import type { CastMember } from '@/components/movie/CastGrid.vue';

/** How many of the top-billed cast are Starring, and how many after them are Supporting. The rest are "Also appearing". */
export const STARRING_COUNT = 6;
export const SUPPORTING_COUNT = 24;

export type CastTier = 'Starring' | 'Supporting' | 'Also appearing';

/** The tier a cast member at this place in the billing order belongs to. */
export function castTier(index: number): CastTier {
    if (index < STARRING_COUNT) {
        return 'Starring';
    }

    return index < STARRING_COUNT + SUPPORTING_COUNT
        ? 'Supporting'
        : 'Also appearing';
}

/** The cast members whose name or character contains the text, in billing order, each with their tier. */
export function castMatching(
    cast: CastMember[],
    text: string,
): { member: CastMember; tier: CastTier }[] {
    const needle = text.trim().toLowerCase();

    return cast
        .map((member, index) => ({ member, tier: castTier(index) }))
        .filter(
            ({ member }) =>
                member.name.toLowerCase().includes(needle) ||
                (member.character ?? '').toLowerCase().includes(needle),
        );
}
