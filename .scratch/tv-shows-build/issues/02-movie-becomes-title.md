# 02: Movie becomes Title, no visible change

**What to build:** Generalise Movie to Title as in the shared-base decision (ADR 0002): one `titles` table with a `kind`, unique on `(kind, tmdb_id)`, with one-to-one movie and TV Show detail tables; `Title` as the base model with `Movie` and `TvShow` subtypes scoped to their kind; genre links, Availability, Streaming service links and user facts pointing at a Title; factories that build a Title of either kind with `Movie` and `TvShow` states. Nothing is live, so the existing migrations are edited in place and the local database rebuilt. Movie URLs, pages and behaviour do not change. Read the spec's Data model section first.

**Spec:** [TV Shows](../../tv-shows/spec.md)

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [x] The `titles` base, detail tables and models exist, with `Movie` and `TvShow` scoping to their own kind
- [x] Genre, Availability, Streaming service and user-fact tables reference a Title
- [x] Factories build either kind with states
- [x] The full existing Movie test suite passes with no assertion changes (only table or column names in setup may change)
- [x] Every query that means Movies-only goes through the Movie subtype or filters on kind
- [x] No TV Show behaviour exists yet and no user-visible change
