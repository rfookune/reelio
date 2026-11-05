/**
 * PROTOTYPE, throwaway. MOCK DATA for the visual language and app shell prototype.
 *
 * Poster and backdrop paths are real TMDB image paths (hotlinked, never stored).
 * TMDB scores were read off themoviedb.org on 2026-11-04; IMDb scores are
 * approximate and hand-typed. Edge cases are deliberately fake: see `edgeCase`.
 */

export type MockMovie = {
    id: number;
    title: string;
    year: number | null;
    posterPath: string | null;
    backdropPath: string | null;
    tmdbRating: number | null;
    imdbRating: number | null;
    runtime: string;
    genres: string[];
    overview: string;
    edgeCase?: string;
};

const IMAGE_BASE = 'https://image.tmdb.org/t/p';

export function posterUrl(
    movie: MockMovie,
    size: 'w185' | 'w342' | 'w500' = 'w342',
): string | null {
    return movie.posterPath ? `${IMAGE_BASE}/${size}${movie.posterPath}` : null;
}

export function backdropUrl(
    movie: MockMovie,
    size: 'w780' | 'w1280' = 'w1280',
): string | null {
    return movie.backdropPath
        ? `${IMAGE_BASE}/${size}${movie.backdropPath}`
        : null;
}

export const movies: Record<string, MockMovie> = {
    duneTwo: {
        id: 693134,
        title: 'Dune: Part Two',
        year: 2024,
        posterPath: '/6izwz7rsy95ARzTR3poZ8H6c5pp.jpg',
        backdropPath: '/eZ239CUp1d6OryZEBPnO2n87gMG.jpg',
        tmdbRating: 8.1,
        imdbRating: 8.5,
        runtime: '2h 47m',
        genres: ['Science Fiction', 'Adventure'],
        overview:
            'Paul Atreides unites with Chani and the Fremen while on a path of revenge against the conspirators who destroyed his family.',
    },
    oppenheimer: {
        id: 872585,
        title: 'Oppenheimer',
        year: 2023,
        posterPath: '/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg',
        backdropPath: '/neeNHeXjMF5fXoCJRsOmkNGC7q.jpg',
        tmdbRating: 8.0,
        imdbRating: 8.3,
        runtime: '3h 1m',
        genres: ['Drama', 'History'],
        overview:
            'The story of J. Robert Oppenheimer and his role in the development of the atomic bomb.',
    },
    parasite: {
        id: 496243,
        title: 'Parasite',
        year: 2019,
        posterPath: '/7IiTTgloJzvGI1TAYymCfbfl3vT.jpg',
        backdropPath: '/TU9NIjwzjoKPwQHoHshkFcQUCG.jpg',
        tmdbRating: 8.5,
        imdbRating: 8.5,
        runtime: '2h 13m',
        genres: ['Comedy', 'Thriller', 'Drama'],
        overview:
            'All unemployed, Ki-taek’s family takes a peculiar interest in the wealthy Park family.',
    },
    spiritedAway: {
        id: 129,
        title: 'Spirited Away',
        year: 2001,
        posterPath: '/39wmItIWsg5sZMyRUHLkWBcuVCM.jpg',
        backdropPath: '/m4TUa2ciEWSlk37rOsjiSIvZDXE.jpg',
        tmdbRating: 8.5,
        imdbRating: 8.6,
        runtime: '2h 5m',
        genres: ['Animation', 'Family', 'Fantasy'],
        overview:
            'A young girl wanders into a world ruled by gods, witches and spirits, where humans are changed into beasts.',
    },
    eeaao: {
        id: 545611,
        title: 'Everything Everywhere All at Once',
        year: 2022,
        posterPath: '/u68AjlvlutfEIcpmbYpKcdi09ut.jpg',
        backdropPath: '/fIwiFha3WPu5nHkBeMQ4GzEk0Hv.jpg',
        tmdbRating: 7.7,
        imdbRating: 7.8,
        runtime: '2h 20m',
        genres: ['Action', 'Adventure', 'Science Fiction'],
        overview:
            'An aging Chinese immigrant is swept up in an insane adventure where she alone can save existence.',
    },
    barbie: {
        id: 346698,
        title: 'Barbie',
        year: 2023,
        posterPath: '/iuFNMS8U5cb6xfzi51Dbkovj7vM.jpg',
        backdropPath: '/1esAE8sLJRWWFsLLeh5r3g2WanI.jpg',
        tmdbRating: 6.9,
        imdbRating: 6.8,
        runtime: '1h 54m',
        genres: ['Comedy', 'Adventure'],
        overview:
            'Barbie and Ken are having the time of their lives in Barbie Land, until they get a chance to go to the real world.',
    },
    spiderVerse: {
        id: 324857,
        title: 'Spider-Man: Into the Spider-Verse',
        year: 2018,
        posterPath: '/iiZZdoQBEYBv6id8su7ImL0oCbD.jpg',
        backdropPath: '/1ntePsIqeklfmrQJqZPncCydsqY.jpg',
        tmdbRating: 8.4,
        imdbRating: 8.4,
        runtime: '1h 57m',
        genres: ['Animation', 'Action'],
        overview:
            'Miles Morales becomes the Spider-Man of his reality and crosses paths with counterparts from other dimensions.',
    },
    bladeRunner: {
        id: 335984,
        title: 'Blade Runner 2049',
        year: 2017,
        posterPath: '/gajva2L0rPYkEWjzgFlBXCAVBE5.jpg',
        backdropPath: '/gNdLJU9TxrpGx4dkZidjys3fyy0.jpg',
        tmdbRating: 7.6,
        imdbRating: 8.0,
        runtime: '2h 44m',
        genres: ['Science Fiction', 'Drama'],
        overview:
            'A new blade runner unearths a long-buried secret that could plunge what’s left of society into chaos.',
    },
    madMax: {
        id: 76341,
        title: 'Mad Max: Fury Road',
        year: 2015,
        posterPath: '/ulcAi4dKpAjHwYGS08vNyx9H6I9.jpg',
        backdropPath: '/gqrnQA6Xppdl8vIb2eJc58VC1tW.jpg',
        tmdbRating: 7.6,
        imdbRating: 8.1,
        runtime: '2h',
        genres: ['Action', 'Adventure', 'Science Fiction'],
        overview:
            'In a post-apocalyptic wasteland, Max teams up with Furiosa to flee a tyrant and his army.',
    },
    budapest: {
        id: 120467,
        title: 'The Grand Budapest Hotel',
        year: 2014,
        posterPath: '/eWdyYQreja6JGCzqHWXpWHDrrPo.jpg',
        backdropPath: '/jK65srQczOKTpW62wPxwwKztGgE.jpg',
        tmdbRating: 8.0,
        imdbRating: 8.1,
        runtime: '1h 40m',
        genres: ['Comedy', 'Drama'],
        overview:
            'The adventures of a legendary concierge and the lobby boy who becomes his most trusted friend.',
    },
    amelie: {
        id: 194,
        title: 'Amélie',
        year: 2001,
        posterPath: '/nSxDa3M9aMvGVLoItzWTepQ5h5d.jpg',
        backdropPath: '/6n53UI4mX9QMfe2S0Pgt8mGebY1.jpg',
        tmdbRating: 7.9,
        imdbRating: 8.3,
        runtime: '2h 2m',
        genres: ['Comedy', 'Romance'],
        overview:
            'A shy waitress in Montmartre decides to change the lives of those around her for the better.',
    },
    godfather: {
        id: 238,
        title: 'The Godfather',
        year: 1972,
        posterPath: '/3bhkrj58Vtu7enYsRolD1fZdja1.jpg',
        backdropPath: '/ejdD20cdHNFAYAN2DlqPToXKyzx.jpg',
        tmdbRating: 8.7,
        imdbRating: 9.2,
        runtime: '2h 55m',
        genres: ['Drama', 'Crime'],
        overview:
            'The aging patriarch of an organized crime dynasty transfers control of his empire to his reluctant son.',
    },
    interstellar: {
        id: 157336,
        title: 'Interstellar',
        year: 2014,
        posterPath: '/yQvGrMoipbRoddT0ZR8tPoR7NfX.jpg',
        backdropPath: '/8sNiAPPYU14PUepFNeSNGUTiHW.jpg',
        tmdbRating: 8.5,
        imdbRating: 8.7,
        runtime: '2h 49m',
        genres: ['Adventure', 'Drama', 'Science Fiction'],
        overview:
            'Explorers travel through a wormhole in an attempt to ensure humanity’s survival.',
    },
    darkKnight: {
        id: 155,
        title: 'The Dark Knight',
        year: 2008,
        posterPath: '/qJ2tW6WMUDux911r6m7haRef0WH.jpg',
        backdropPath: '/cfT29Im5VDvjE0RpyKOSdCKZal7.jpg',
        tmdbRating: 8.5,
        imdbRating: 9.0,
        runtime: '2h 32m',
        genres: ['Drama', 'Action', 'Crime'],
        overview:
            'Batman raises the stakes in his war on crime, until a criminal mastermind known as the Joker appears.',
    },
    matrix: {
        id: 603,
        title: 'The Matrix',
        year: 1999,
        posterPath: '/dXNAPwY7VrqMAo51EKhhCJfaGb5.jpg',
        backdropPath: '/tlm8UkiQsitc8rSuIAscQDCnP8d.jpg',
        tmdbRating: 8.3,
        imdbRating: 8.7,
        runtime: '2h 16m',
        genres: ['Action', 'Science Fiction'],
        overview:
            'A hacker learns the world he lives in is a simulation and joins a rebellion against its machine controllers.',
    },
    pulpFiction: {
        id: 680,
        title: 'Pulp Fiction',
        year: 1994,
        posterPath: '/vQWk5YBFWF4bZaofAbv0tShwBvQ.jpg',
        backdropPath: '/suaEOtk1N1sgg2MTM7oZd2cfVp3.jpg',
        tmdbRating: 8.5,
        imdbRating: 8.9,
        runtime: '2h 34m',
        genres: ['Thriller', 'Crime'],
        overview:
            'The lives of two mob hitmen, a boxer, a gangster and his wife intertwine in four tales of violence and redemption.',
    },
    dune: {
        id: 438631,
        title: 'Dune',
        year: 2021,
        posterPath: '/v1tRXZ4JtD2Iv6fjkPvT4GiwslV.jpg',
        backdropPath: '/jYEW5xZkZk2WTrdbMGAPFuBqbDc.jpg',
        tmdbRating: 7.8,
        imdbRating: 8.0,
        runtime: '2h 35m',
        genres: ['Science Fiction', 'Adventure'],
        overview:
            'Paul Atreides travels to the most dangerous planet in the universe to ensure the future of his family and people.',
    },
    inception: {
        id: 27205,
        title: 'Inception',
        year: 2010,
        posterPath: '/xlaY2zyzMfkhk0HSC5VUwzoZPU1.jpg',
        backdropPath: '/8ZTVqvKDQ8emSGUEMjsS4yHAwrp.jpg',
        tmdbRating: 8.4,
        imdbRating: 8.8,
        runtime: '2h 28m',
        genres: ['Action', 'Science Fiction', 'Adventure'],
        overview:
            'A thief who steals corporate secrets through dream-sharing is given the task of planting an idea instead.',
    },
    insideOutTwo: {
        id: 1022789,
        title: 'Inside Out 2',
        year: 2024,
        posterPath: '/vpnVM9B6NMmQpWeZvzLvDESb2QY.jpg',
        backdropPath: '/p5ozvmdgsmbWe0H8Xk7Rc8SCwAB.jpg',
        tmdbRating: 7.5,
        imdbRating: null,
        runtime: '1h 37m',
        genres: ['Animation', 'Family'],
        overview:
            'Teenager Riley’s mind headquarters is undergoing a sudden demolition to make room for new Emotions.',
        edgeCase: 'IMDb rating missing (new release not yet in the dataset)',
    },
    godzilla: {
        id: 940721,
        title: 'Godzilla Minus One',
        year: 2023,
        posterPath: '/2E2WTX0TJEflAged6kzErwqX1kt.jpg',
        backdropPath: '/oHBhHoNe59VH6bUMazDtJmWOFSS.jpg',
        tmdbRating: 7.6,
        imdbRating: 7.7,
        runtime: '2h 5m',
        genres: ['Science Fiction', 'Horror', 'Action'],
        overview:
            'Post-war Japan is at its lowest point when a new crisis emerges in the form of a giant monster.',
    },
    jesseJames: {
        id: 4512,
        title: 'The Assassination of Jesse James by the Coward Robert Ford',
        year: 2007,
        posterPath: '/xMKn6EQS7eR5ubhPJbw5pQSBZMw.jpg',
        backdropPath: '/3HvQ5PaxwQJEiRSZD5AyVuqEiNn.jpg',
        tmdbRating: 7.1,
        imdbRating: 7.5,
        runtime: '2h 40m',
        genres: ['Western', 'Drama'],
        overview:
            'Robert Ford, who idolized Jesse James, joins the gang only to become resentful of its leader.',
        edgeCase: 'Very long title',
    },
    noPoster: {
        id: 999001,
        title: 'The Lighthouse Keeper’s Daughter',
        year: 2026,
        posterPath: null,
        backdropPath: null,
        tmdbRating: null,
        imdbRating: null,
        runtime: '1h 52m',
        genres: ['Drama'],
        overview: 'FAKE: an upcoming film with no artwork and no ratings yet.',
        edgeCase: 'No poster, no backdrop, no ratings (fake title)',
    },
};

export type MockSection = {
    key: string;
    title: string;
    movies: MockMovie[];
};

const m = movies;

export const sections: MockSection[] = [
    {
        key: 'trending',
        title: 'Trending this week',
        movies: [
            m.duneTwo,
            m.oppenheimer,
            m.insideOutTwo,
            m.godzilla,
            m.barbie,
            m.eeaao,
            m.bladeRunner,
            m.spiderVerse,
        ],
    },
    {
        key: 'popular',
        title: 'Popular right now',
        movies: [m.noPoster, m.jesseJames, m.dune, m.madMax, m.amelie],
    },
    {
        key: 'top-rated',
        title: 'Top rated',
        movies: [
            m.godfather,
            m.darkKnight,
            m.pulpFiction,
            m.spiritedAway,
            m.parasite,
            m.interstellar,
            m.inception,
            m.matrix,
        ],
    },
    {
        key: 'sci-fi',
        title: 'Science fiction',
        movies: [
            m.interstellar,
            m.matrix,
            m.bladeRunner,
            m.dune,
            m.inception,
            m.madMax,
            m.godzilla,
            m.eeaao,
        ],
    },
];

export const featured: MockMovie = m.duneTwo;

/** Movies pre-seeded on the Watchlist so the Watchlist screen is never blank on first load. */
export const initialWatchlistIds: number[] = [
    m.parasite.id,
    m.jesseJames.id,
    m.noPoster.id,
    m.spiritedAway.id,
];

/** Movies pre-marked as Watched, with their Watched date (most recent first). */
export const initialWatched: [number, string][] = [
    [m.inception.id, '2026-10-31'],
    [m.amelie.id, '2026-10-19'],
    [m.godfather.id, '2026-09-02'],
    [m.madMax.id, '2026-08-14'],
    [m.interstellar.id, '2026-06-27'],
];

export const allMovies: MockMovie[] = Object.values(movies);
