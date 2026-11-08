import { describe, expect, it } from 'vitest';
import {
    tvShowJsonLd,
    tvShowStatus,
    tvShowTitle,
    tvShowYearRange,
} from '@/lib/tvShows';

describe('TV Show year range', () => {
    it.each(['Returning Series', 'In Production', 'Pilot'])(
        'shows present for %s',
        (status) => {
            expect(tvShowYearRange('2023-01-01', '2025-01-01', status)).toBe(
                '2023–present',
            );
        },
    );
    it('shows the end year, a single year, or no year when dates are unknown', () => {
        expect(tvShowYearRange('2023-01-01', '2025-01-01', 'Ended')).toBe(
            '2023–2025',
        );
        expect(tvShowYearRange('2019-05-06', '2019-06-03', 'Ended')).toBe(
            '2019',
        );
        expect(tvShowYearRange(null, null, null)).toBeNull();
    });
});

it('uses readable status labels and a first-air-year SEO title', () => {
    expect(tvShowStatus('Returning Series')).toBe('Returning');
    expect(tvShowStatus('Cancelled')).toBe('Canceled');
    expect(tvShowStatus('In Production')).toBe('In production');
    expect(tvShowTitle('Chernobyl', '2019-05-06')).toBe(
        'Chernobyl (2019): where to watch, cast and ratings',
    );
});

it('describes a TVSeries without inventing dates, creators or ratings', () => {
    expect(
        tvShowJsonLd({
            name: 'New TV show',
            firstAirDate: null,
            seasonCount: 0,
            episodeCount: 0,
            creators: [],
            genres: [],
            image: '/card.png',
            description: null,
            url: '/tv-shows/new-1',
        }),
    ).toEqual({
        '@context': 'https://schema.org',
        '@type': 'TVSeries',
        name: 'New TV show',
        numberOfSeasons: 0,
        numberOfEpisodes: 0,
        image: '/card.png',
        url: '/tv-shows/new-1',
    });
});
