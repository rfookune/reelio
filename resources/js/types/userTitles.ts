import type { PosterCardTitle } from '@/components/shared/PosterCard.vue';

export type UserList = 'watchlist' | 'watched';

interface UnavailableTitle extends PosterCardTitle {
    date: string;
}

/** Which kinds a Watchlist or Watched page shows; `filter` in the URL, left out for `all`. */
export type ListFilter = 'all' | 'movies' | 'tv-shows';

export type UserListPageProps = {
    imageBaseUrl: string;
    sort: string;
    filter: ListFilter;
    titles: { data: PosterCardTitle[] };
    unavailableTitles: UnavailableTitle[];
    trending: PosterCardTitle[];
    userMovies: UserMovies;
    userTvShows: UserTvShows;
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

/**
 * The viewer's facts about one TV Show: on the Watchlist, with the latest Episode Watched date and whether any
 * Episode is watched, and where its Watched progress stands.
 */
export type UserTvShowFacts = {
    watchlisted: boolean;
    watched: boolean;
    watchedDate: string | null;
    state: 'Not started' | 'In progress' | 'Completed';
    /** The aired Episodes of the numbered Seasons watched, and how many there are. */
    watchedCount: number;
    total: number;
};

/** The `userTvShows` page prop: facts for each TV Show the viewer holds one about, keyed by catalog id. */
export type UserTvShows = Record<string, UserTvShowFacts>;

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
