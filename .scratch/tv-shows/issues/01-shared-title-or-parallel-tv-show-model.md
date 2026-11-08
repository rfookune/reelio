# Shared title model or a parallel TV Show model

Type: grilling
Blocked by: none
Status: resolved

## Question

Should TV Shows and Movies share one catalog entity (a generalised "Title" with a kind), or should TV Shows be a parallel model with their own tables, with the shared parts (genres, Availability, ratings, user facts) factored where it pays? Weigh against the glossary, which avoids "Title" for Movie, the single ratings table ([ADR 0001](../../../docs/adr/0001-one-ratings-table-for-every-rating-source.md)), `user_movies`, the Catalog source interface, and the later ratings, likes and filters work. Offer an ADR if the answer is hard to reverse.

## Answer

A shared base, recorded in [ADR 0002](../../../docs/adr/0002-shared-title-base-for-movies-and-tv-shows.md).

- One `titles` table with a `kind`, unique on `(kind, tmdb_id)`; kind-specific columns in one-to-one `movie_details` and `tv_show_details`; Seasons and Episodes in TV-only tables keyed to a Title.
- `Title` is the base Eloquent model; `Movie` and `TvShow` are scoped subtypes, and `Movie` keeps its name in code and UI copy.
- The glossary term is **Title** (a Movie or a TV Show), with **Name** for its display text. People never see "Title".
- Existing Movie URLs and routes do not change.
- Nothing is live, so the initial migrations are edited in place and the local database is rebuilt with `migrate:fresh`; the spec's first slice is the behaviour-preserving Movie to Title generalisation.
