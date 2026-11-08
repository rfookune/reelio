export function tvShowYearRange(
    first: string | null,
    last: string | null,
    status: string | null,
): string | null {
    if (!first) return null;
    const start = first.slice(0, 4);
    if (['Returning Series', 'In Production', 'Pilot'].includes(status ?? ''))
        return `${start}–present`;
    const end = last?.slice(0, 4);
    return end && end !== start ? `${start}–${end}` : start;
}

export function tvShowStatus(status: string | null): string | null {
    if (status === null) return null;
    return (
        (
            {
                'Returning Series': 'Returning',
                'In Production': 'In production',
                Canceled: 'Canceled',
                Cancelled: 'Canceled',
            } as Record<string, string>
        )[status] ?? status
    );
}

export function tvShowTitle(
    name: string | null,
    firstAirDate: string | null,
): string {
    const year = firstAirDate?.slice(0, 4);
    return `${name ?? 'TV show'}${year ? ` (${year})` : ''}: where to watch, cast and ratings`;
}

export function tvShowJsonLd(tvShow: {
    name: string;
    firstAirDate: string | null;
    seasonCount: number;
    episodeCount: number;
    creators: string[];
    genres: string[];
    image: string;
    description: string | null;
    url: string;
}): Record<string, unknown> {
    return {
        '@context': 'https://schema.org',
        '@type': 'TVSeries',
        name: tvShow.name,
        ...(tvShow.firstAirDate ? { startDate: tvShow.firstAirDate } : {}),
        numberOfSeasons: tvShow.seasonCount,
        numberOfEpisodes: tvShow.episodeCount,
        ...(tvShow.creators.length
            ? {
                  creator: tvShow.creators.map((name) => ({
                      '@type': 'Person',
                      name,
                  })),
              }
            : {}),
        ...(tvShow.genres.length ? { genre: tvShow.genres } : {}),
        image: tvShow.image,
        ...(tvShow.description ? { description: tvShow.description } : {}),
        url: tvShow.url,
    };
}
