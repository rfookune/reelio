# A shared Title base for Movies and TV Shows

Movies and TV Shows share one `titles` table keyed by `unique(kind, tmdb_id)`, with a one-to-one detail table per kind (`movie_details`, `tv_show_details`) for the columns only that kind has. Seasons and Episodes are TV-only tables hanging off a Title. `Title` is the base Eloquent model; `Movie` and `TvShow` are subtypes that scope to their own kind, so code that only deals with Movies keeps working. Everything the user and the catalog share (genres, Availability, the user's Watchlist facts, and the later ratings, likes and filters) points at a Title.

We chose this over parallel TV tables because the planned features all act on "a thing a user has opinions about", and a shared base builds each of them once: one Watchlist query with one keyset paging path, one genre and Streaming service filter, one place for a user's rating or like.

## Considered Options

- **Parallel tables** (`tv_shows`, `seasons`, `episodes`, plus parallel user, Availability and genre tables): cheapest and safest to build now, Movies untouched. Every list, filter and sort then needs two code paths, a combined Watchlist needs a UNION across two tables with keyset paging, and each later feature is built twice.
- **Polymorphic links** (keep `movies`, add `tv_shows`, point the shared tables at either through a morph): drops foreign keys and referential integrity, keeps queries awkward, and has no upside over the shared base.
- **One wide table with nullable kind-specific columns**: no join on a Movie page, but kind-invalid columns exist and need CHECK constraints. Detail tables keep `titles` honest as the table every list, filter and sort reads.

## Consequences

- **Existing Movie URLs stay**: `movies/{slug}-{id}` and `movies/tmdb/{tmdbId}` do not change. TV Shows get their own URL space.
- **Nothing is live**, so the migrations are edited in place rather than added to: `movies` becomes `titles`, `movie_id` columns become `title_id`, and the local database is rebuilt with `migrate:fresh`. No backward-compatible migration is needed.
- The first build slice is the behaviour-preserving generalisation of Movie to Title, with the test suite green and no user-visible change, before any TV work.
- The TMDB id is only unique within a kind, so identity everywhere is `(kind, tmdb_id)`. The `ratings` table's `(source, source_id)` key has to be made kind-aware for TMDB scores; that is decided with Availability and External ratings.
- Every query that means "Movies only" (the Movie picker, Movie pages) goes through the `Movie` subtype or filters on `kind`; a query on `Title` returns both.
