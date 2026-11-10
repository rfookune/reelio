import { describe, expect, it } from 'vitest';
import type { Season, WatchedProgress } from '@/composables/useTvShowActions';
import {
    nextUp,
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

describe('next Episode to watch', () => {
    const episode = (
        id: number,
        number: number,
        overrides: Partial<Season['episodes'][number]> = {},
    ): Season['episodes'][number] => ({
        id,
        number,
        name: `Episode ${number}`,
        overview: null,
        airDate: '2020-01-01',
        runtime: null,
        markable: true,
        watchedDate: null,
        ...overrides,
    });
    const season = (
        id: number,
        number: number,
        episodes: Season['episodes'],
    ): Season => ({
        id,
        number,
        name: null,
        total: episodes.length,
        watched: episodes.filter((e) => e.watchedDate).length,
        episodes,
    });
    const progress = (seasons: Season[]): WatchedProgress => ({
        state: 'In progress',
        total: 0,
        watched: 0,
        defaultOpenSeasonId: null,
        seasons,
    });

    it('is the first aired, unwatched Episode, skipping watched and upcoming ones', () => {
        const result = nextUp(
            progress([
                season(1, 1, [
                    episode(1, 1, { watchedDate: '2026-01-01' }),
                    episode(2, 2),
                ]),
            ]),
        );
        expect(result?.episode.id).toBe(2);
        expect(result?.season.id).toBe(1);
        const upcomingOnly = progress([
            season(1, 1, [
                episode(1, 1, { watchedDate: '2026-01-01' }),
                episode(2, 2, { markable: false }),
            ]),
        ]);
        expect(nextUp(upcomingOnly)).toBeNull();
    });

    it('moves to the next Season and never offers a Special', () => {
        const result = nextUp(
            progress([
                season(9, 0, [episode(90, 1)]),
                season(1, 1, [episode(1, 1, { watchedDate: '2026-01-01' })]),
                season(2, 2, [episode(3, 1)]),
            ]),
        );
        expect(result?.episode.id).toBe(3);
        expect(nextUp(progress([season(9, 0, [episode(90, 1)])]))).toBeNull();
    });
});
