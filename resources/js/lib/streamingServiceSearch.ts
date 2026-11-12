/** The most services a search of them lists. */
export const FOUND_STREAMING_SERVICES = 20;

/** The text as it is compared: lower case, with accents taken off, so "mediatheque" finds "Médiathèque". */
function fold(text: string): string {
    return text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
}

/**
 * How well a name matches what was typed: 0 for not at all, up to 100 for exactly. Both are already folded.
 * Better matches are the whole name, then its start, then the start of a word in it, then the text anywhere in it, and
 * last the letters in order, which forgives a typo or a missing letter.
 */
function matchScore(name: string, query: string): number {
    if (name === query) {
        return 100;
    }

    if (name.startsWith(query)) {
        return 80;
    }

    if (name.split(/[^\p{L}\p{N}]+/u).some((word) => word.startsWith(query))) {
        return 60;
    }

    if (name.includes(query)) {
        return 40;
    }

    let next = 0;

    for (const letter of name) {
        if (letter === query[next]) {
            next++;
        }

        if (next === query.length) {
            return 20;
        }
    }

    return 0;
}

/**
 * The services that best match the text, best first, at most `limit`. Services that match equally keep the order they
 * came in, which is the source's display order; text with nothing in it matches them all.
 */
export function rankServices<T extends { name: string }>(
    services: T[],
    text: string,
    limit: number = FOUND_STREAMING_SERVICES,
): T[] {
    const query = fold(text.trim());

    if (query === '') {
        return services.slice(0, limit);
    }

    return services
        .map((service, index) => ({
            service,
            index,
            score: matchScore(fold(service.name), query),
        }))
        .filter((match) => match.score > 0)
        .sort((a, b) => b.score - a.score || a.index - b.index)
        .slice(0, limit)
        .map((match) => match.service);
}
