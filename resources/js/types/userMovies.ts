import type { PosterCardMovie } from '@/components/movie/PosterCard.vue';

export type UserMovieList = 'watchlist' | 'watched';

export type UserMovieListPageProps = {
    imageBaseUrl: string;
    sort: string;
    movies: { data: PosterCardMovie[] };
    trending: PosterCardMovie[];
    userMovies: UserMovies;
};

/** The viewer's facts about one Movie. */
export type UserMovieFacts = {
    watchlisted: boolean;
    watched: boolean;
    /** The Watched date, as a UTC timestamp. */
    watchedDate: string | null;
};

/** The `userMovies` page prop: facts for each Movie on the page, keyed by catalog id. */
export type UserMovies = Record<string, UserMovieFacts>;

/** A Watchlist or Watched action, as the Undo request names it. */
export type UserMovieAction =
    | 'add_to_watchlist'
    | 'remove_from_watchlist'
    | 'mark_as_watched'
    | 'clear_watched';

/** The surface a Watchlist or Watched control was used on, recorded with the action. */
export type UserMovieActionSource =
    | 'discover'
    | 'search'
    | 'movie'
    | 'watchlist'
    | 'watched'
    | 'picker';

/** A guest's pending action, applied when they signed in, as the server flashes it to the page they return to. */
export type AppliedUserMovieAction = {
    tmdbId: number;
    action: UserMovieAction;
    /** The Movie's facts before the action, which its Undo restores. */
    previous: UserMovieFacts;
};
