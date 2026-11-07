/**
 * A user's own date, such as the Watched date, in short form ("12 Mar 2026"), in the given time zone or the
 * browser's. The form is built from parts, because locales disagree on it (en-GB writes "Sept").
 */
export function formatShortDate(timestamp: string, timeZone?: string): string {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-US', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            timeZone,
        })
            .formatToParts(new Date(timestamp))
            .map((part) => [part.type, part.value]),
    );

    return `${parts.day} ${parts.month} ${parts.year}`;
}

/**
 * A calendar date (Y-m-d) as day and short month ("21 Nov"), read in UTC so that it is the same day everywhere.
 */
export function formatDayMonth(date: string): string {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-US', {
            day: 'numeric',
            month: 'short',
            timeZone: 'UTC',
        })
            .formatToParts(new Date(`${date}T00:00:00Z`))
            .map((part) => [part.type, part.value]),
    );

    return `${parts.day} ${parts.month}`;
}
