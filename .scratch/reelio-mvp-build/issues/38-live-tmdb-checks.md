# 38: Live TMDB checks

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** Drift between Reelio's fixtures and the real TMDB API or IMDb dataset is caught weekly, without ever running in normal CI. See the spec's "Testing Decisions" (live) and "Operations" sections.

**Blocked by:** [06: Open a Movie from its TMDB id](./06-open-a-movie-from-its-tmdb-id.md), [11: IMDb ratings import and display](./11-imdb-ratings-import-and-display.md)

**Status:** ready-for-agent

- [ ] A Pest `live` group, excluded by default, runs the TMDB adapter against the real API and checks the IMDb dataset URL and header
- [ ] A weekly scheduled GitHub Action runs it with a read-only TMDB token stored as a repository secret and emails the owner on failure
