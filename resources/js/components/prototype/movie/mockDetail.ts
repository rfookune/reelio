/**
 * PROTOTYPE, throwaway. MOCK DATA for the Movie detail page prototype.
 *
 * Generated from the live TMDB fixture `.scratch/reelio-mvp/research/tmdb-fixtures/movie_157336.json`
 * (Interstellar, fetched 2026-11-04): cast, crew, trailer key and US/CA watch providers are real.
 * The trailer is the one Reelio's ranking rule picks (Trailer > Teaser, official, language, earliest).
 * The IMDb score and vote count are hand-typed approximations.
 */

export type ProviderEntry = { name: string; logo: string };

export type CountryAvailability = {
    link: string | null;
    stream: ProviderEntry[];
    free: ProviderEntry[];
    rent: ProviderEntry[];
    buy: ProviderEntry[];
};

export type CastMember = {
    name: string;
    character: string;
    profilePath: string | null;
};

export type MockDetail = {
    tmdbId: number;
    title: string;
    originalTitle: string;
    tagline: string;
    overview: string;
    releaseDate: string;
    runtime: number;
    genres: string[];
    posterPath: string | null;
    backdropPath: string | null;
    tmdbRating: number;
    tmdbVotes: number;
    imdbRating: number;
    imdbVotes: number;
    certification: string;
    directors: string[];
    writers: string[];
    cast: CastMember[];
    trailer: { key: string; name: string; type: string };
    availability: Record<string, CountryAvailability>;
};

export const interstellar: MockDetail = {
    tmdbId: 157336,
    title: 'Interstellar',
    originalTitle: 'Interstellar',
    tagline: 'Mankind was born on Earth. It was never meant to die here.',
    overview:
        'The adventures of a group of explorers who make use of a newly discovered wormhole to surpass the limitations on human space travel and conquer the vast distances involved in an interstellar voyage.',
    releaseDate: '2014-11-04',
    runtime: 169,
    genres: ['Adventure', 'Drama', 'Science Fiction'],
    posterPath: '/yQvGrMoipbRoddT0ZR8tPoR7NfX.jpg',
    backdropPath: '/8sNiAPPYU14PUepFNeSNGUTiHW.jpg',
    tmdbRating: 8.5,
    tmdbVotes: 41780,
    imdbRating: 8.7,
    imdbVotes: 2400000,
    certification: 'PG-13',
    directors: ['Christopher Nolan'],
    writers: ['Christopher Nolan', 'Jonathan Nolan'],
    cast: [
        {
            name: 'Matthew McConaughey',
            character: 'Cooper',
            profilePath: '/lCySuYjhXix3FzQdS4oceDDrXKI.jpg',
        },
        {
            name: 'Anne Hathaway',
            character: 'Brand',
            profilePath: '/s6tflSD20MGz04ZR2R1lZvhmC4Y.jpg',
        },
        {
            name: 'Michael Caine',
            character: 'Professor Brand',
            profilePath: '/bVZRMlpjTAO2pJK6v90buFgVbSW.jpg',
        },
        {
            name: 'Jessica Chastain',
            character: 'Murph',
            profilePath: '/mR1ELpP0T2Q1ZRSaADPgtrbPGla.jpg',
        },
        {
            name: 'Casey Affleck',
            character: 'Tom',
            profilePath: '/304ilSygaCRWykoBWAL67TOw8g9.jpg',
        },
        {
            name: 'Wes Bentley',
            character: 'Doyle',
            profilePath: '/voD93lzFZrr9xfAggwFcPRBi84i.jpg',
        },
        {
            name: 'Topher Grace',
            character: 'Getty',
            profilePath: '/89sF57Br5lq8nrepS7HLzJ41QD1.jpg',
        },
        {
            name: 'Mackenzie Foy',
            character: 'Murph (10 Yrs.)',
            profilePath: '/ga2HkX7gBjjXooegTu9cLgXy9nE.jpg',
        },
        {
            name: 'Ellen Burstyn',
            character: 'Murph (older)',
            profilePath: '/zGihSIVRzwmCqilnA4PmNM4C2RT.jpg',
        },
        {
            name: 'John Lithgow',
            character: 'Donald',
            profilePath: '/ajfuBSm1HuVqFJlbTmAlza62Xxr.jpg',
        },
        {
            name: 'Bill Irwin',
            character: 'TARS (voice)',
            profilePath: '/bAV5qsljgiHQkn3QluB5clVYC13.jpg',
        },
        {
            name: 'David Gyasi',
            character: 'Romilly',
            profilePath: '/1382u2e4jiGhuDKgeK9UZtXXqfh.jpg',
        },
        {
            name: 'Timoth\u00e9e Chalamet',
            character: 'Tom (15 Yrs.)',
            profilePath: '/dFxpwRpmzpVfP1zjluH68DeQhyj.jpg',
        },
        {
            name: 'Matt Damon',
            character: 'Mann',
            profilePath: '/aCvBXTAR9B1qRjIRzMBYhhbm1fR.jpg',
        },
        {
            name: 'Josh Stewart',
            character: 'CASE (voice)',
            profilePath: '/prgoT9fq5Z2ZCGmC1rrSZkq4wH9.jpg',
        },
        {
            name: 'Leah Cairns',
            character: 'Lois',
            profilePath: '/pdPcPWuPcheSRPwJFmnMI66XTn5.jpg',
        },
        {
            name: 'Liam Dickinson',
            character: 'Coop',
            profilePath: '/4qXWvTQ2dC4369FnV4Ac2JoYk3p.jpg',
        },
        {
            name: 'Francis X. McCarthy',
            character: 'Boots',
            profilePath: '/4DyZhRmxGrwhr2UNc1CTZHdvZ0R.jpg',
        },
    ],
    trailer: {
        key: '827FNDpQWrQ',
        name: 'Teaser Trailer',
        type: 'Trailer',
    },
    availability: {
        US: {
            link: 'https://www.themoviedb.org/movie/157336-interstellar/watch?locale=US',
            stream: [
                {
                    name: 'Apple TV',
                    logo: '/9icYBfYFcwgCbky5VdGUIKJ4C5i.png',
                },
                {
                    name: 'Paramount Plus Premium',
                    logo: '/4N4BMd0Mm0kHAmF7RZgL5lW3cwc.png',
                },
                {
                    name: 'Paramount Plus Essential',
                    logo: '/uPWQPyF4nqwtfPNmGcFcenU57HG.png',
                },
                {
                    name: 'Paramount+ Amazon Channel',
                    logo: '/bxX0YVOCY7bI85BKRlJAAQ2EjUR.png',
                },
                {
                    name: 'Paramount+ Roku Premium Channel',
                    logo: '/tnZCMxbcKdxuVmjxefyXCaip7q3.png',
                },
                {
                    name: 'Apple TV Amazon Channel',
                    logo: '/rerVCu9auZrgxTTiZu1criqrQzg.png',
                },
            ],
            free: [],
            rent: [
                {
                    name: 'Amazon Video',
                    logo: '/jn6TLbtaTZntTRX9UYucHJvpQx1.png',
                },
                {
                    name: 'Apple TV Store',
                    logo: '/qdEGArH3lKfFnAtYXMkSYk5wxuG.png',
                },
                {
                    name: 'JustWatch TV Store',
                    logo: '/aaFIYn2NGPCo0rpLiWokbvNqNJd.png',
                },
                {
                    name: 'Google Play Movies',
                    logo: '/aZRENwYILujqs0RVOZutTh0BVGV.png',
                },
                {
                    name: 'YouTube',
                    logo: '/5Maob4o5w8oZnNeYpCDyVFD3M7X.png',
                },
                {
                    name: 'Fandango At Home',
                    logo: '/wpOt6x0dRxTrIzuBosto4LGd7E9.png',
                },
                {
                    name: 'FlixFling',
                    logo: '/hkw2s2Su8EyifB9EbbQP64Fj1xA.png',
                },
                {
                    name: 'Plex',
                    logo: '/blAEhvx3XX8sV0fFVFk88FFjubs.png',
                },
            ],
            buy: [
                {
                    name: 'Amazon Video',
                    logo: '/jn6TLbtaTZntTRX9UYucHJvpQx1.png',
                },
                {
                    name: 'Apple TV Store',
                    logo: '/qdEGArH3lKfFnAtYXMkSYk5wxuG.png',
                },
                {
                    name: 'Google Play Movies',
                    logo: '/aZRENwYILujqs0RVOZutTh0BVGV.png',
                },
                {
                    name: 'YouTube',
                    logo: '/5Maob4o5w8oZnNeYpCDyVFD3M7X.png',
                },
                {
                    name: 'Fandango At Home',
                    logo: '/wpOt6x0dRxTrIzuBosto4LGd7E9.png',
                },
            ],
        },
        CA: {
            link: 'https://www.themoviedb.org/movie/157336-interstellar/watch?locale=CA',
            stream: [
                {
                    name: 'Paramount Plus',
                    logo: '/pkx3klJlwW5JdtaulvDx6hDNtch.png',
                },
                {
                    name: 'Paramount+ Amazon Channel',
                    logo: '/bxX0YVOCY7bI85BKRlJAAQ2EjUR.png',
                },
                {
                    name: 'Crave',
                    logo: '/a2o4bE63LR71m8jHrkx9BnBucN0.png',
                },
                {
                    name: 'StackTV Amazon Channel',
                    logo: '/63tEiSxrgEAufhx2jfxzt2zPtVL.png',
                },
                {
                    name: 'Paramount Plus Premium',
                    logo: '/4N4BMd0Mm0kHAmF7RZgL5lW3cwc.png',
                },
                {
                    name: 'Paramount Plus Basic with Ads',
                    logo: '/ylBHMFuVsJqQ8JUzmfmPsEn97Ez.png',
                },
                {
                    name: 'Crave Amazon Channel',
                    logo: '/6EuODonAziRYWv90wAtEmUjk78R.png',
                },
            ],
            free: [
                {
                    name: 'Global TV',
                    logo: '/v53oSfNUIyC1gKy8ICKb2KguUGr.png',
                },
            ],
            rent: [
                {
                    name: 'Apple TV Store',
                    logo: '/qdEGArH3lKfFnAtYXMkSYk5wxuG.png',
                },
                {
                    name: 'JustWatch TV Store',
                    logo: '/aaFIYn2NGPCo0rpLiWokbvNqNJd.png',
                },
                {
                    name: 'CosmoGo',
                    logo: '/fyUUKmDdzpJjSwsXPN4Rpi7jTu5.png',
                },
                {
                    name: 'FlixFling',
                    logo: '/hkw2s2Su8EyifB9EbbQP64Fj1xA.png',
                },
                {
                    name: 'YouTube',
                    logo: '/5Maob4o5w8oZnNeYpCDyVFD3M7X.png',
                },
                {
                    name: 'Amazon Video',
                    logo: '/jn6TLbtaTZntTRX9UYucHJvpQx1.png',
                },
            ],
            buy: [
                {
                    name: 'Apple TV Store',
                    logo: '/qdEGArH3lKfFnAtYXMkSYk5wxuG.png',
                },
                {
                    name: 'CosmoGo',
                    logo: '/fyUUKmDdzpJjSwsXPN4Rpi7jTu5.png',
                },
                {
                    name: 'YouTube',
                    logo: '/5Maob4o5w8oZnNeYpCDyVFD3M7X.png',
                },
                {
                    name: 'Amazon Video',
                    logo: '/jn6TLbtaTZntTRX9UYucHJvpQx1.png',
                },
            ],
        },
    },
};
