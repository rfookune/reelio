/** The viewer's facts about one Movie. */
export type UserMovieFacts = {
    watchlisted: boolean;
    watched: boolean;
};

/** The `userMovies` page prop: facts for each Movie on the page, keyed by catalog id. */
export type UserMovies = Record<string, UserMovieFacts>;

/** The surface a Watchlist or Watched control was used on, recorded with the action. */
export type UserMovieActionSource =
    | 'discover'
    | 'search'
    | 'movie'
    | 'watchlist'
    | 'watched'
    | 'picker';
