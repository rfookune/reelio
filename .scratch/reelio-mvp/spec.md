# Reelio MVP spec

Status: needs-triage
Map: [Reelio MVP](./map.md)
Source: [PRD v1.0](./prd.md), as amended by every decision on the map

**Status of this draft:** assembled from the resolved tickets by [Spec assembly](./issues/31-spec-assembly.md). The owner reviews it in [Spec review](./issues/32-spec-review.md), settles the "Owner to confirm" list in Further Notes, and then sets `Status: ready-for-agent` for `/to-tickets`.

**Name:** "Reelio" is the working name throughout. The product will be called **Houselight** once [Clear Houselight on the official registers](./issues/21-houselight-register-clearance.md) passes; the rename is its own implementation ticket (see Further Notes).

**Vocabulary:** terms follow `GLOSSARY.md` (Movie, Removed Movie, Catalog source, Rating source, External rating, Discover, Streaming service, Availability, Watchlist, Date added, Watched, Watched date, Movie picker). "Provider" appears only in TMDB-specific code and field names.

Each decision below links the ticket that holds its reasoning and the alternatives it rejected. Where a later ticket amended an earlier one, this spec states the final answer.

## Problem Statement

People who like movies juggle several sites to answer simple questions:

- What's worth watching?
- Where did I note the film a friend recommended?
- Which of my streaming services has it?
- What did I already see?

Watchlists end up scattered across streaming apps. Ratings live on different sites. Choosing a film for tonight can take longer than watching the trailer. Existing services are comprehensive but cluttered, social, or tied to one platform.

## Solution

Reelio is a free, permanently non-commercial website for one person's movie habits:

- **Discover:** a curated homepage, the same for everyone, plus title search. No account needed.
- **Movie pages:** the overview, cast, a click-to-play trailer, TMDB and IMDb ratings with their credits, and where it streams in the visitor's country, credited to JustWatch.
- **Save:** a private Watchlist and a Watched record, which survive across devices once signed in. A guest who presses save is asked to sign in, and the action completes afterwards.
- **Decide:** the Movie picker chooses a random Movie from the Watchlist, optionally narrowed by genre, with "Pick again".
- **Trust:** nothing is sold or advertised. Data comes only from licensed sources, with their attribution. Users can delete their account and everything with it.

It is a responsive web app with a dark-first, cinematic look. It aims for WCAG 2.2 AA and Core Web Vitals "good" on mobile, and it degrades gracefully when TMDB is down.

## User Stories

### Discover

1. As a visitor, I want the homepage to show a featured Movie with its backdrop, ratings and overview, so that I have an immediate suggestion.
2. As a visitor, I want sections for Trending this week, Popular, Coming soon, Top rated and three genre collections, so that I can browse without searching.
3. As a visitor, I want Trending numbered by rank, so that I can see what is most talked about.
4. As a visitor, I want Coming soon to show each Movie's US opening date ("Opens 21 Nov"), so that I know when it arrives.
5. As a visitor, I want each Movie to appear only once on the homepage, so that the sections don't repeat each other.
6. As a visitor, I want the genre collections to change daily, so that returning visits feel fresh.
7. As a visitor, I want each row to show the poster, title, year, runtime and both External ratings, so that I can judge a Movie at a glance.
8. As a visitor, I want the homepage to keep working when one section fails, so that one outage doesn't blank the page.
9. As a visitor, I want a clear message and Retry when no movie data can be reached, so that I know it's temporary and Search is still there.

### Search

10. As a visitor, I want a Search page whose results update as I type (from 2 characters), so that I find a Movie quickly.
11. As a visitor, I want Enter to search immediately, so that I don't wait for the debounce.
12. As a visitor, I want the query and page in the URL, so that I can share, bookmark and go Back to my results.
13. As a visitor, I want results as poster cards with title, year and one rating, so that I can recognise the right Movie.
14. As a visitor, I want a "Show more results" button, so that I can go deeper without losing the footer.
15. As a visitor, I want to see how many results matched, so that I know whether to refine my query.
16. As a visitor, I want Trending suggestions when I haven't typed anything or nothing matched, so that I always have somewhere to go.
17. As a visitor, I want a clear inline error that keeps my query when search is down, so that I can retry without retyping.
18. As a screen-reader user, I want the result count announced when results settle, so that I know the search finished.
19. As a visitor, I want my search text never stored or logged, so that what I look for stays private.

### Movie page

20. As a visitor, I want a Movie page with backdrop, poster, title, original title (when different), year, runtime, certification and genres, so that I get the essentials first.
21. As a visitor, I want the TMDB and IMDb ratings with vote counts and their credits, so that I can compare trusted sources.
22. As a visitor, I want "Not yet rated" when no source has a rating, so that a missing score never looks like zero.
23. As a visitor, I want the overview, tagline, Director(s), Writers, US release date and US rating, so that I can decide whether it's for me.
24. As a visitor, I want the top-billed cast with photos and characters, so that I can see who is in it.
25. As a visitor, I want the Movie's official trailer, so that I can watch it before deciding.
26. As a privacy-minded visitor, I want nothing loaded from YouTube until I press play, so that viewing a page doesn't contact Google.
27. As a visitor, I want a "Watch on YouTube" link, so that I can still watch when embedding is blocked.
28. As a visitor, I want Where to watch grouped into Stream, Free, Rent and Buy tabs for my country, so that I can see how to watch it.
29. As a visitor, I want to change the country Where to watch uses, so that I can check another region or correct a wrong guess.
30. As a visitor, I want to be told when my country isn't supported and US Availability is shown instead, so that I'm not misled.
31. As a visitor, I want to see when Availability was last updated, and a warning when it may be out of date, so that I know how far to trust it.
32. As a visitor, I want a link to TMDB's watch page for my country, clearly labelled, so that I can see every option without being misled into thinking it goes to the title.
33. As a visitor, I want the page to still show what's stored when TMDB is down, with "temporarily unavailable" panels for the rest, so that an outage doesn't hide everything.
34. As a visitor following an old link, I want a wrong or outdated slug to redirect to the right page, so that links never break.
35. As a visitor, I want a clear "no longer available" page when TMDB has removed a Movie, so that I understand why there are no details.
36. As a signed-in user, I want to remove a Removed Movie from my lists from its page, so that I can tidy up.

### Watchlist and Watched actions

37. As a user, I want one tap to add a Movie to my Watchlist from any card, row, the hero, search results or the Movie page, so that saving is effortless.
38. As a user, I want the bookmark to fill instantly and roll back with an error if saving fails, so that the UI feels fast and stays truthful.
39. As a user, I want to remove a Movie from my Watchlist the same way, so that the list stays relevant.
40. As a user, I want to mark a Movie as watched from its page, my Watchlist card or the picker, so that I record what I've seen.
41. As a user, I want marking as watched to take the Movie off my Watchlist, so that the Watchlist only holds what I still want to see.
42. As a user, I want to re-add a watched Movie to my Watchlist to watch it again, so that rewatches are possible.
43. As a user, I want marking a rewatch as watched to move its Watched date to today, so that the date reflects the last time I saw it.
44. As a user, I want to clear a Watched record without touching my Watchlist, so that I can fix mistakes.
45. As a user, I want a toast with Undo after every action that restores exactly what I had, so that mistakes cost nothing.
46. As a guest, I want pressing save or mark as watched to take me to sign in or register and then complete that action on the page I was on, so that I don't lose my intent.
47. As a new user, I want my pending action to complete before I verify my email, so that registration doesn't interrupt the flow.
48. As a user, I want the same Movie shown twice on a page (hero and row) to stay in sync, so that the page never contradicts itself.
49. As a keyboard user, I want focus to move to the next card when a card leaves my Watchlist, and back when I Undo, so that I don't lose my place.

### Watchlist and Watched pages

50. As a user, I want my Watchlist as a poster grid, newest added first, so that recent saves are at the top.
51. As a user, I want to sort my Watchlist by oldest added, title or release date, so that I can find things my way.
52. As a user, I want my Watched page newest watched first, with each card's Watched date, so that I can review what I've seen.
53. As a user, I want to sort Watched by oldest watched, title or release date, so that I can browse my history.
54. As a user, I want a quiet "Last watched {date}" on a Movie I've queued to rewatch, so that I remember I've seen it.
55. As a user, I want a count in the page header, so that I know how big my lists are.
56. As a user, I want "Show more" paging that never skips Movies, even after I remove some, so that nothing gets lost.
57. As a user, I want Movies TMDB has removed shown in a "No longer available" group with my own date, so that I can see and clear them.
58. As a user, I want my lists to load even when TMDB is down, so that my own data is always reachable.
59. As a new user, I want my empty Watchlist to suggest Trending Movies I can save on the spot, so that I can get started.
60. As a mobile user, I want a Watchlist / Watched switch at the top of both pages, so that I can reach Watched without a fifth nav item.

### Movie picker

61. As a user, I want a "Pick for me" button on my Watchlist, so that I can let Reelio choose tonight's film.
62. As a user, I want the picker to choose uniformly at random from my Watchlist, so that every saved Movie has a fair chance.
63. As a user, I want to narrow the pick to one or more genres that exist on my Watchlist, so that it fits my mood without an empty result.
64. As a user, I want the pick to show the poster, title, year, runtime, genres, ratings, a short overview and where it streams for me, so that I can decide on the spot.
65. As a user, I want "Pick again" to give me a different Movie, so that I'm not offered the same one twice in a row.
66. As a user, I want to be told when only one Movie matches, so that a disabled "Pick again" makes sense.
67. As a user, I want to mark the pick as watched straight from the picker, with Undo, so that I can record tonight's choice.
68. As a user, I want "View details" to open the Movie's page, so that I can see more before committing.
69. As a user, I want "Pick for me" hidden when nothing can be picked, so that I never open an empty picker.

### Accounts and settings

70. As a visitor, I want to register with email and password, so that my lists are saved.
71. As a user, I want to sign in and out, reset a forgotten password and verify my email, so that my account is secure and recoverable.
72. As a user, I want to keep the optional two-factor authentication and passkeys, so that I can protect my account.
73. As a user, I want to change my display name, email, password and preferred country, so that my account stays current.
74. As a user, I want to delete my account and everything in it at once, so that I'm in control of my data.
75. As a user, I want to choose light, dark or system theme, with dark as the default, so that it suits my eyes.
76. As a user on a shared device, I want Back after signing out not to show my lists, so that the next person can't see them.
77. As a visitor, I want forgot-password not to reveal whether an email has an account, so that my account's existence isn't leaked.

### Trust, legal and accessibility

78. As a visitor, I want TMDB, JustWatch and IMDb credited wherever their data appears, so that I know where it comes from.
79. As a visitor, I want About, Privacy, Terms and Accessibility pages linked from every footer, so that I can see how Reelio works and treats my data.
80. As a visitor, I want no consent banner, because nothing needs consent, so that the site respects my time.
81. As a keyboard or screen-reader user, I want every page and control to meet WCAG 2.2 AA, so that I can use Reelio fully.
82. As a user who prefers reduced motion, I want shimmer, reveals and fades to be still, so that the site is comfortable.
83. As a visitor going too fast, I want a clear "Try again in a moment" message rather than a broken page, so that I know what happened.

### Owner and operator

84. As the owner, I want a `metrics:report` command over the first-party events, so that I can check the beta's success metrics without a dashboard.
85. As the owner, I want email alerts for exception spikes, failed jobs, sustained TMDB failures, overdue refreshes and a stale IMDb table, so that I hear about problems.
86. As the owner, I want a `catalog:purge` command that clears all TMDB-derived data while keeping users' facts, so that I can comply if the TMDB licence ends.
87. As the owner, I want deploys to run only after CI passes, and rollback to be a redeploy of the previous commit, so that releases are safe.
88. As the owner, I want point-in-time backups and a tested restore runbook that re-deletes accounts deleted since the restore point, so that a restore never resurrects a deleted account.
89. As the owner, I want indexing behind one flag that's off by default, so that I can launch before TMDB confirms the classification.
90. As the owner, I want a weekly live check against TMDB, so that I learn about API drift before users do.

## Implementation Decisions

### Stack, infrastructure and environments

From [Infrastructure baseline](./issues/05-infrastructure-baseline.md), as amended by [Deployment and operations](./issues/19-deployment-and-operations.md):

- **Stack:** the existing starter kit, kept: Laravel 13, Inertia v3, Vue 3, TypeScript, Tailwind v4, reka-ui, Fortify, Wayfinder, Pest 5, Vite+ (`vp`). The optional 2FA and passkeys stay.
- **Hosting:** Laravel Cloud, Growth plan, in a US region (Ohio, `us-east-2`, if the dashboard offers it). See [Privacy, terms and retention](./issues/30-privacy-terms-and-retention.md).
    - The App cluster uses a new Flex size (sub-500 ms wake) and scales to zero in production for now. Moving to an always-on replica later is one toggle.
    - Inertia SSR uses the App cluster's "Use Inertia SSR" toggle.
- **Database:** PostgreSQL everywhere: production (serverless, scales to zero, 7-day point-in-time recovery), local development and the test suite.
- **Cache:** Laravel Valkey in production (Redis-compatible driver), with Redis from Herd locally and in tests. The cache holds only disposable data; anything that must persist, or be purged under TMDB's cap, lives in Postgres.
- **Queues:** in production, Cloud managed queues (`QUEUE_CONNECTION=cloud`):
    - `default`: Flex, 256 MiB, scales to zero, at most 3 workers. Carries analytics events, mail, Movie refresh, purge and cleanup, and the event roll-up.
    - `imports`: Flex, 512 MiB, scales to zero, 1 worker. Carries the IMDb import and the sitemap build.

    Local development uses the `database` driver, and tests fake the queue. Failed jobs are managed in Cloud's Queues dashboard (no Horizon, `queue:failed` or `queue:retry`).

- **Sessions:** the database driver, changed as set out under Privacy below.
- **Mail:** Resend, through Cloud's integration. Local development uses Herd mail or the `log` driver.
- **Environments:** local, production, and Cloud preview environments. There is no staging.
    - Preview environments get the read-only TMDB token, the `log` mailer and their own database, and no other production credentials.
- **Local setup:** Herd with Postgres and Redis. `.env.example` is updated to match.
- **No object storage:** images are hotlinked and there are no uploads.

### Modules

[Catalog and Rating source boundary](./issues/10-source-boundary.md) sets two seams with nothing shared, in the new namespaces `App\Catalog` and `App\Ratings` (approved):

- **Catalog adapter interface** (small and neutral), with the TMDB adapter and an in-memory fake:
    - `search(query, page)`;
    - `section(kind, page)`, where kind is trending, popular, top rated, upcoming/discover or genre;
    - `movie(tmdbId)`, returning one `MovieDetails`;
    - `genres()`.

    A `CatalogContext` carries language and region.

- **The TMDB adapter translates everything.** It returns neutral objects. The Director(s), Writers, the ranked trailer, cast, certification and Availability (Streaming service, country, offer type) are decided inside it. Nothing outside the adapter sees TMDB's JSON or the word "provider".
- **The `Catalog` module** is deep, and it is the only thing controllers and Actions call. It owns:
    - find-or-create of Movie rows (entry route and catalog-id actions);
    - all cache reads and writes, through one key builder;
    - refreshing stored Movies, the purge and the cap guard;
    - clamping TMDB's 500-page cap and deduplicating by id;
    - outbound throttles and the circuit breaker;
    - turning source failures into explicit results.

    Controllers never touch the adapter or the cache.

- **Rating source interface:** one batch method, `ratingsFor(Collection $movies)`, returning an `ExternalRating` per Movie id. An `ExternalRating` holds the source (an enum), the value on a 0–10 scale, the vote count and an as-of timestamp. A missing rating is **absent** from the result: never `"N/A"`, `0` or a placeholder. There is never a blended or combined score: each External rating stands alone with its source ([01](./issues/01-tmdb-terms.md)).
    - **TMDB adapter:** reads `vote_average` and `vote_count` from the Movie row. Zero votes counts as absent.
    - **IMDb adapter:** joins the IMDb ratings table through `imdb_id`. No `imdb_id`, or no entry, means absent. A missing `imdb_id` never falls back to a title search ([03](./issues/03-omdb-ratings.md)). It needs no Movie row beyond the `imdb_id`.
    - **Fake:** returns given ratings per Movie, or none.
- **A thin `Ratings` module** collects the adapters and returns External ratings in a fixed display order: IMDb, then TMDB. The IMDb display must be removable without breaking any page, because IMDb can withdraw permission.
- **User–movie actions** (set Watchlist, clear Watchlist, mark watched, clear Watched, Undo) are Actions over `user_movies`. They implement the lifecycle below and record one analytics event each.
- **The Movie picker** is a query over the user's candidates plus a criteria object (genres today, the P1 filters later).

### Data model

From [Movie identity, URLs and local storage](./issues/09-movie-identity-and-storage.md), as amended by [Search experience](./issues/23-search-experience.md), [Cache lifetimes and refresh schedule](./issues/26-cache-lifetimes-and-refresh.md), [Country and Availability presentation](./issues/11-country-and-availability.md) and [Privacy, terms and retention](./issues/30-privacy-terms-and-retention.md):

- **`users`:** gains a nullable `preferred_country` (one of the supported countries).
- **`movies`:** every TMDB-derived field falls under one `synced_at`.
    - Identity: local `id`, a unique `tmdb_id` (a plain column, not the PRD's generic `(provider, provider_movie_id)` pair), and a slug derived from the title and regenerated on refresh.
    - Metadata: `title`, `original_title`, `overview`, `tagline`, `release_date`, `runtime`, `poster_path`, `backdrop_path`, `original_language`, `vote_average`, `vote_count`, `imdb_id`.
    - Bookkeeping: `synced_at`, `last_viewed_at`, and a marker that the Movie is a Removed Movie.
    - Every TMDB-derived column is nullable, because a Removed Movie and a cap-cleared Movie (below) keep the row with those fields cleared.
    - `tagline` isn't named in the storage ticket. It's added so the tagline survives an outage, like the overview. It could live in the detail cache instead; this is a build-time choice.
- **`genres`** (TMDB genre id and name) and a **`genre_movie`** pivot, used by the picker. The genre list is refreshed weekly.
- **`streaming_services`:** TMDB's id for the service, name, logo path and fetch timestamp. Services that no Availability row references are deleted by the daily cleanup.
- **`availabilities`:** one row per Movie, Streaming service, country and offer type (`stream`, `free`, `rent`, `buy`), plus TMDB's `display_priority` for ordering.
    - Only supported countries are stored.
    - A Movie's rows are replaced wholesale on each refresh.
    - TMDB's `free` and `ads` both map to `free`.
- **IMDb ratings table:** the whole `title.ratings.tsv` dataset, keyed by IMDb id, with its rating, vote count and `imported_at`. It's loaded through a staging table and swapped (below). It isn't TMDB data, so the 6-month cap doesn't apply.
- **`user_movies`:**
    - `user_id`, foreign key with cascade delete;
    - `movie_id`, foreign key with restrict, so a Movie is never deleted while anyone holds a fact about it;
    - nullable `watchlisted_at` (the Date added) and `watched_at` (the Watched date);
    - unique on `(user_id, movie_id)`.

    A row with both timestamps empty is deleted. There is no `is_watchlisted` boolean and no `personal_rating`.

- **`events`:** analytics events, each with:
    - a unique UUID, generated when the event is dispatched, to dedupe at-least-once redeliveries;
    - `user_id`, foreign key with cascade delete;
    - the event name, a JSON properties object, and a timestamp.

    Rows older than 13 months are rolled into aggregate counts and deleted (the roll-up table's shape is open; see Further Notes).

- **`sessions`:** the `ip_address` and `user_agent` columns are dropped. `user_id` gets a foreign key with cascade delete.
- **Cache only (Valkey):**
    - the Discover payload;
    - search results per query and page;
    - each Movie's detail-only data: the top 12 cast, Director(s), Writers, the ranked trailer, the US certification and the US release date.

### Movie identity and URLs

From [Movie identity, URLs and local storage](./issues/09-movie-identity-and-storage.md) and [Search experience](./issues/23-search-experience.md):

- **Canonical URL:** `/movies/{slug}-{id}`, using the local id. The slug is decorative: a wrong or stale slug gets a 301 to the canonical URL, which also carries a canonical tag.
- **Entry route:** `/movies/tmdb/{tmdbId}`. Lists use it to open a Movie that has no row yet.
    - If the row exists, it redirects.
    - Otherwise TMDB confirms the id first (so ids can't be enumerated into rows), the row is created, and it 301-redirects to the canonical URL.
    - It is rate-limited.
    - With no row and the source down, it shows a 503 page.
    - List links point at the canonical URL whenever the row already exists.
- **Rows are created** on first view (entry route) or first save (the catalog-id action endpoints). Both stamp `last_viewed_at`.
- **Removed Movie:** a refresh that gets a 404 keeps the row, clears every TMDB-derived field along with its genre and Availability rows, and marks it removed.
    - Its Movie page is a title-less 410 for everyone, marked `noindex`.
    - Users' facts survive.
    - Merges aren't detected: the surviving TMDB id becomes a separate Movie.

### Catalog: failures, timeouts and cache keys

From [Catalog and Rating source boundary](./issues/10-source-boundary.md) and [Rate limiting and security headers](./issues/28-rate-limits-and-security.md):

| Profile     | Timeout | Attempts                     | Used for                    |
| ----------- | ------- | ---------------------------- | --------------------------- |
| Interactive | 3 s     | one retry                    | anything a visitor waits on |
| Background  | 10 s    | three, with jittered backoff | refresh and cleanup jobs    |

- **Retried:** connection errors, 5xx and 429 only. A 429 honours `Retry-After`, capped at 2 s.
- **Not retried:** a 404 is an answer (a Removed Movie). A 401 is a configuration error and alerts immediately.
- **Circuit breaker:** after five consecutive interactive failures, calls fail immediately for 30 s.
- **Outbound throttles in Valkey:**
    - every live (interactive) TMDB call shares **15 requests/s**;
    - background jobs share **20 requests/s**.

    Over the live ceiling, `Catalog` doesn't wait: it returns `stale` or `unavailable`.

- **Explicit results:** `Catalog` returns a result that is `fresh`, `stale` or `unavailable`, never an exception. When the source fails and an expired entry exists, that entry is served as `stale`.
- **Cache keys** are built in one place: `catalog:v1:{kind}:{params-hash}:{language}:{region}`.
    - Language comes from config (`en-US`) and appears in every key and adapter call.
    - Region appears only where TMDB uses it. Trending never carries one.
    - The user's country never appears, because Availability lives in Postgres.
    - Bumping `v1` invalidates a bad payload shape.
- **TMDB requests:**
    - backend only, with the Bearer read token;
    - `include_adult=false` on every list and search;
    - `include_video_language` passed explicitly on detail calls;
    - one detail call: `/movie/{id}?append_to_response=credits,videos,release_dates,watch/providers`, whose response key is `watch/providers`.
- **TMDB reference data:** the image base URL is config (`https://image.tmdb.org/t/p/`), not fetched from `/configuration`.
- **Trailer ranking**, inside the adapter:
    - YouTube videos only, with keys matching `^[A-Za-z0-9_-]{11}$`;
    - Trailer before Teaser, then official, then language match;
    - the last tiebreak is the latest published on or before the primary release date, else the earliest. ([Movie detail page](./issues/24-movie-detail-page.md))
- **Director(s):** every crew member with job Director.
- **US certification and US release date:** from `release_dates`. The release date is type 3 (theatrical), else the earliest type 2.

### Refresh, cache lifetimes, purge and the cap guard

From [Cache lifetimes and refresh schedule](./issues/26-cache-lifetimes-and-refresh.md). TMDB's terms forbid keeping anything TMDB-derived more than 6 months and ask for on-demand fetching ([TMDB terms, attribution and caching limits](./issues/01-tmdb-terms.md)).

- **One refresh operation per Movie.** One `movie()` call updates:
    - the row and its genres;
    - Availability for the supported countries;
    - the Streaming services it references (upserted);
    - the detail cache.

    It sets `synced_at`. A 404 makes it a Removed Movie.

- **When a Movie is refreshed:**

    | Trigger                        | Rule                                                                                                     |
    | ------------------------------ | -------------------------------------------------------------------------------------------------------- |
    | A Movie page view              | If `synced_at` is over 24 h old, refresh in the request (interactive). On failure, render it as `stale`. |
    | On at least one Watchlist      | Every 3 days                                                                                             |
    | Watched only (on no Watchlist) | Every 30 days                                                                                            |
    | No user facts                  | Never by schedule; purged instead                                                                        |

- **Refresh jobs:** a planner selects due Movies page by page and dispatches one job per Movie, unique per Movie, on `default`. It re-dispatches itself to continue, so each job stays under 90 s. A Movie job is one background-profile call (about 35 s worst case).
- **Cache lifetimes:**

    | Data                        | Lifetime                                                                    | Served as `stale` on failure               |
    | --------------------------- | --------------------------------------------------------------------------- | ------------------------------------------ |
    | Discover payload            | rebuilt daily by a job; the first request builds it if it's missing         | each section for up to 7 days, then hidden |
    | Search (per query and page) | 1 h                                                                         | up to 24 h, then the error state           |
    | Detail-only data per Movie  | 30 days hard, rewritten on every refresh; fetched in the request if missing | until its hard expiry                      |

    No cache entry lives more than 30 days.

- **Purge of unviewed Movies:** the daily cleanup deletes any Movie with no user facts and `last_viewed_at` more than 30 days old, along with its genre links, Availability and detail cache.
    - It's controlled by `catalog.purge_unviewed.enabled` (env `CATALOG_PURGE_UNVIEWED`, on by default everywhere) and `catalog.purge_unviewed.after_days` (default 30).
    - `last_viewed_at` is set at creation and on every Movie page view.
    - Undo by catalog id re-creates a purged row.
- **Cap guard:**
    - The owner is alerted when any Movie with user facts is more than 14 days past its due refresh.
    - At **170 days** without a successful sync, any Movie's TMDB-derived fields are cleared, whether or not the purge is on. It is _not_ marked removed.
    - It appears in the "No longer available" group as "Details temporarily unavailable", and the next successful refresh restores it.
- **Licence loss:** `catalog:purge`, run by hand, applies the same clearing to every row. Users' facts survive.
- **Freshness:** every Availability block shows "Updated today / yesterday / N days ago" from `synced_at`, plus a "may be out of date" notice when that is more than 7 days old.

### IMDb import

From [Catalog and Rating source boundary](./issues/10-source-boundary.md), [Cache lifetimes and refresh schedule](./issues/26-cache-lifetimes-and-refresh.md) and [Deployment and operations](./issues/19-deployment-and-operations.md):

- A daily job on `imports`. It skips the download when the file is unchanged (`ETag` / `Last-Modified`), and the download URL is config.
- It streams the gzipped `title.ratings.tsv` without loading it into memory and writes to a staging table.
- **Validation before the swap:**
    - the header matches;
    - malformed rows are skipped and counted;
    - the row count is at least 90% of the live table's.

    Then the staging table replaces the live table in one transaction.

- **On any failure** the live table is untouched and keeps serving. The failure reaches Nightwatch and the owner alert, and the next run retries. The owner is alerted when the live table is more than 3 days old. Ratings stay on show however old the table is.
- **Timeouts:** the job's timeout is generous, and the queue's `retry_after` is longer than it, so a slow run isn't delivered twice ([05](./issues/05-infrastructure-baseline.md)).
- **Flex jobs are killed at 90 s.** A build-time spike measures the import. If it doesn't fit, `imports` moves to a Pro queue (about $5/month) before launch.
- The IMDb credit, worded as IMDb confirms ([Confirm IMDb permission for the non-commercial ratings dataset](./issues/18-imdb-permission.md)), appears wherever the IMDb rating shows. The wording requested is "Information courtesy of IMDb (https://www.imdb.com). Used with permission."

### Scheduler

Every scheduled task runs from one daily slot at **05:00 UTC** and only dispatches jobs. Weekly tasks run on Mondays in the same slot. There are no hourly or per-minute tasks, so the app wakes once a day.

| Task                                                                    | Cadence                 | Source                                           |
| ----------------------------------------------------------------------- | ----------------------- | ------------------------------------------------ |
| IMDb import                                                             | daily                   | [26](./issues/26-cache-lifetimes-and-refresh.md) |
| Discover build                                                          | daily                   | [22](./issues/22-homepage-curation.md)           |
| Refresh planner                                                         | daily                   | [26](./issues/26-cache-lifetimes-and-refresh.md) |
| Unviewed-Movie purge, unreferenced Streaming service cleanup, cap guard | daily                   | [26](./issues/26-cache-lifetimes-and-refresh.md) |
| Sitemap build                                                           | daily, indexing on only | [14](./issues/14-public-pages-seo.md)            |
| Analytics roll-up (older than 13 months)                                | daily                   | [08](./issues/08-analytics-and-monitoring.md)    |
| Unverified-account cleanup (30 days old, no facts)                      | daily                   | [30](./issues/30-privacy-terms-and-retention.md) |
| `auth:clear-resets`                                                     | daily                   | [30](./issues/30-privacy-terms-and-retention.md) |
| `queue:prune-failed --hours=168`                                        | daily                   | [30](./issues/30-privacy-terms-and-retention.md) |
| Genre list refresh                                                      | weekly, Monday          | [26](./issues/26-cache-lifetimes-and-refresh.md) |

`queue:prune-failed` applies wherever Laravel's failed-job table is in use. On Cloud managed queues, failed jobs live in Cloud's dashboard; check the retention there at build.

### Routes

| Method       | Path                                             | Access                             | Notes                                                                                       |
| ------------ | ------------------------------------------------ | ---------------------------------- | ------------------------------------------------------------------------------------------- |
| GET          | `/`                                              | public, SSR                        | Discover                                                                                    |
| GET          | `/search?q=&page=`                               | public, client-rendered, `noindex` | Search                                                                                      |
| GET          | `/movies/tmdb/{tmdbId}`                          | public, `noindex`, rate-limited    | entry route: find or create, then 301                                                       |
| GET          | `/movies/{slug}-{id}`                            | public, SSR                        | Movie page; 301 on a wrong slug; 410 for a Removed Movie                                    |
| PUT / DELETE | `/movies/tmdb/{tmdbId}/watchlist`                | `auth` (guests: pending action)    | idempotent set / clear                                                                      |
| PUT / DELETE | `/movies/tmdb/{tmdbId}/watched`                  | `auth` (guests: pending action)    | idempotent mark / clear                                                                     |
| —            | Undo endpoint                                    | `auth`                             | path not decided                                                                            |
| —            | Movie picker endpoint                            | `auth`                             | path not decided; called with `useHttp`                                                     |
| GET          | `/watchlist?sort=`, `/watched?sort=`             | `auth`, client-rendered, `noindex` |                                                                                             |
| GET          | `/about`, `/privacy`, `/terms`, `/accessibility` | public, SSR                        | `/accessibility` is from [29](./issues/29-accessibility-verification.md); see Further Notes |
| —            | Country selection                                | public                             | path not decided; writes the guest cookie or `preferred_country`                            |
| POST         | `/csp-report`                                    | public, no CSRF, always 204        |                                                                                             |
| GET          | `/up`                                            | public                             | liveness: the app only                                                                      |
| GET          | `/health`                                        | public                             | readiness: one cheap DB query and one cache read; 200 or 503 with no detail                 |
| GET          | `/robots.txt`, sitemap index                     | public                             | follows the indexing flag                                                                   |
| —            | Fortify auth routes and the settings pages       | as the starter kit                 | client-rendered, `noindex`                                                                  |

The Watchlist and Watched actions need `auth`, not `verified`. Verification still guards account settings and email-dependent features. Every state-changing route has CSRF protection, validation and authorization to the user's own data.

### The user–movie lifecycle

From [The user–movie lifecycle](./issues/04-user-movie-lifecycle.md). Watchlist and Watched are independent, private facts, which gives four states. Neither fact is ever deleted as a side effect of catalog changes.

| Action                                          | Effect                                                                                  |
| ----------------------------------------------- | --------------------------------------------------------------------------------------- |
| Add to Watchlist                                | Sets the Date added to now. If it's already on the Watchlist, nothing changes.          |
| Remove from Watchlist                           | Removes only the Watchlist fact. Re-adding sets a new Date added.                       |
| Mark as watched (not Watched)                   | Sets the Watched date to now, and takes the Movie off the Watchlist if it was on.       |
| Mark as watched (Watched and on the Watchlist)  | A rewatch: takes it off the Watchlist and moves the Watched date to now.                |
| Mark as watched (Watched, not on the Watchlist) | No change.                                                                              |
| Clear Watched                                   | Removes only Watched. The Watchlist is untouched.                                       |
| Undo                                            | Restores the exact earlier state, including the previous Watched date or no row at all. |
| Delete account                                  | Hard-deletes everything with the user.                                                  |

Analytics records one event per action. Marking as watched doesn't also record a Watchlist removal.

### Watchlist interactions

From [Watchlist interactions](./issues/13-watchlist-interactions.md), as amended by [Search experience](./issues/23-search-experience.md) and [Movie detail page](./issues/24-movie-detail-page.md):

- **One `userMovies` page prop,** keyed by **catalog id**, maps each Movie on the page to `{watchlisted, watched}` and the dates the surface needs. Every surface reads it, so a Movie shown twice stays consistent.
- **Sending an action:** an `async` Inertia router visit with an `optimistic` callback that updates one `userMovies` entry, then a partial reload of `userMovies` only. `async` matters: a synchronous visit would cancel one in flight and roll back its optimistic state. There is no client store.
- **The catalog-id endpoints** find or create the Movie row through `Catalog`, exactly as the entry route does, under the same row-creation limit. They then set the fact. Repeating a request settles on the state last asked for.
- **Surfaces:**

    | Surface                  | Actions                                                             |
    | ------------------------ | ------------------------------------------------------------------- |
    | Discover hero and rows   | Watchlist toggle                                                    |
    | Search results           | Watchlist toggle                                                    |
    | Watchlist cards          | Watchlist toggle (floating bookmark); "⋯" menu with Mark as watched |
    | Watched cards            | Watchlist toggle; "⋯" menu with Clear Watched                       |
    | Removed Movie cards      | "⋯" with Remove (Watchlist) or Clear Watched (Watched)              |
    | Movie page               | by state; see Movie page                                            |
    | Removed Movie page (410) | signed-in users with facts: Remove and Clear                        |
    | Movie picker             | View details, Pick again, Mark as watched (no Remove)               |

- **Feedback:**
    - Every action shows a sonner toast ("Added to your Watchlist", "Marked as watched", …) with **Undo**. It lasts about 6 s, pauses on hover or focus, is announced politely, and Undo is reachable by keyboard.
    - On failure, the optimistic state rolls back and an error toast says "Couldn't save that. Try again." There's no automatic retry. A 429 uses the 429 line instead.
    - Toggles use `aria-pressed` with a changing label ("Add to Watchlist" / "Remove from Watchlist").
- **Focus:** when a card leaves the Watchlist page, focus moves to the same control on the next card, else the previous one, else the empty-state heading. Undo returns focus to the restored card.
- **Undo:**
    - Each action stores the Movie's previous state (both facts and their dates, keyed by catalog id) in the session. Only the latest action is kept.
    - Undo restores the snapshot only if the current state still matches what the action produced. Otherwise it fails with "That can't be undone any more".
    - The client never supplies dates.
    - Undo records `user_movie_action_undone` with `{movie_id, action}` and leaves the original event in place.
- **Guest intent through sign-in:**
    - A guest using a control stores a pending action `{catalog id, action}` in the session: single-use, 30-minute expiry, latest only.
    - It sets the intended URL to the page the session last recorded on a page load (never a header or request parameter) and sends the guest to login.
    - After login _or_ registration, the pending action is applied idempotently, the user lands back on that page, and the normal toast shows.
    - No GET request ever changes data.
- **Picker:** Mark as watched closes the dialog, the card leaves optimistically and the toast offers Undo. Undo restores the card but doesn't reopen the dialog.

### Discover

From [Homepage curation](./issues/22-homepage-curation.md):

| #   | Section                       | Source                                                                                                                                                                                                                                                            |
| --- | ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0   | Hero                          | The highest-ranked Trending Movie with a backdrop and an overview. "Watch trailer" appears only when a trailer exists.                                                                                                                                            |
| 1   | Trending this week (numbered) | `/trending/movie/week`: global, labelled just "Trending" in copy elsewhere                                                                                                                                                                                        |
| 2   | Popular                       | `/movie/popular`                                                                                                                                                                                                                                                  |
| 3   | Coming soon                   | `discover` with `region=US`, theatrical and digital release types, US dates from tomorrow to 60 days out. Each Movie's US date comes from `release_dates` (10 extra calls per build) and shows on the row ("Opens 21 Nov"). Past or missing US dates are dropped. |
| 4   | Top rated                     | `/movie/top_rated`                                                                                                                                                                                                                                                |
| 5–7 | Three genre collections       | Picked each day, deterministically from the date, from a config list (Action, Comedy, Drama, Science Fiction, Horror, Animation, Thriller, Romance). Uses `discover` by popularity with at least 300 votes.                                                       |

- 10 Movies per section. There are no "See all" pages.
- **Deduplicated across sections:** a Movie appears only in the earliest section, and later sections fill up from their next TMDB page. The hero is the exception, so Trending keeps its true ranks.
- Adult titles and poster-less Movies are left out.
- **The same for every visitor:** the server assembles one payload daily at 05:00 UTC (hero, sections, rotations and Coming soon lookups, fetched concurrently). Only `userMovies` differs per user. The country never affects Discover.
- **Failures:**
    - A failed section keeps its previous version as `stale` for up to 7 days, with no notice, then is hidden quietly.
    - If every section fails: "We can't reach our movie data right now", with Retry, and Search stays reachable.
- **Layout** ([Visual language and app shell](./issues/06-visual-language-and-shell.md)):
    - The hero is a full-bleed backdrop with the title set large; year, runtime and genres; both External ratings; the overview; and "Add to Watchlist" (accent fill) and "Watch trailer".
    - Sections are grids of list rows: small poster, title, year / runtime, both External ratings, and a bookmark toggle at the end.
    - The top bar floats over a blurred glow from the hero backdrop.

### Search

From [Search experience](./issues/23-search-experience.md):

- **One page, `/search`,** reached from the Search nav item. It has one autofocused input (when there's no query) and live results below it. There's no dropdown in the top bar. Guests can search.
- **Input:**
    - Searching starts at 2 characters, after a 300 ms debounce. Enter skips the debounce.
    - Each query change replaces the history entry, and a newer request cancels the one in flight.
    - `q` is trimmed and capped at 100 characters; `page` is 1–500.
- **Results:**
    - The Watchlist and Watched poster card, in the same 2–6 column grid: poster or missing-poster tile, the floating bookmark, the title clamped to 2 lines, the year (when known) and one External rating. There's no "⋯" menu or date line.
    - The rating is IMDb when the Movie has a row with an IMDb rating, else TMDB, else "Not yet rated".
    - TMDB's order, `include_adult=false`, and no filters or reranking. Poster-less Movies are kept.
- **Paging:**
    - 20 per page, using `Inertia::scroll()` with TMDB's page metadata and `<InfiniteScroll manual>` with a "Show more results" button. There's no automatic infinite scroll, which would keep the footer out of reach.
    - The page syncs to `?page=` and stops at page 500.
    - The count comes from `total_results`.
- **States:**

    | State                                   | Shows                                                                                                              |
    | --------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
    | No query, or under 2 characters         | "Trending this week" from the cached Discover payload (no extra TMDB call)                                         |
    | No results                              | "No movies match "{query}"", a tip ("Check the spelling, or try fewer or different words"), then the Trending grid |
    | First search loading                    | shimmer cards                                                                                                      |
    | Query changed while results are showing | current results stay, slightly dimmed                                                                              |
    | Show more loading                       | a spinner in the button, plus appended shimmer cards                                                               |
    | Settled                                 | live region: "{n} results for '{query}'"                                                                           |
    | `stale`                                 | shown normally                                                                                                     |
    | `unavailable`                           | "We can't search right now" with Retry. The query is kept; Trending is hidden if it also fails.                    |

- **Opening a result:** the link goes to the canonical URL if the row exists, else the entry route. It carries the `search` referrer. Back restores the query, the loaded pages and the scroll position.

### Movie page

From [Movie detail page](./issues/24-movie-detail-page.md). The throwaway prototype is on branch `prototype/movie-detail-page`, route `/prototype/movie?variant=A2`.

- **Hero:**
    - A full-bleed backdrop, with the top bar floating over it. With no backdrop, a blurred poster fills the hero.
    - The poster sits bottom-left from `sm` and isn't downloaded below that.
    - Next to it:
        - the title, and the original title (with `lang`) when it differs;
        - year · runtime · certification badge · genres;
        - the External ratings line, e.g. "IMDb 8.7 2.4M · TMDB 8.5 41.8K", each source name in its colour, with IMDb's credit under it when IMDb shows;
        - the actions.
    - The backdrop fades out (a CSS mask) over a band clear of the title block: 7rem on mobile, 10rem from `md`. A blurred copy glows behind the top of the body, which is pulled up into the band.
- **Body from 1024 px:**
    - Left column: tagline, overview, Trailer, Cast.
    - Sticky right column: the Details card, then Where to watch. It sticks 1.5rem from the top. When it's taller than the viewport, its `top` is `min(1.5rem, viewport height − column height − 1.5rem)`, re-measured on resize. That measurement is client-only; the server-rendered default is 1.5rem.
- **Mobile, below 1024 px:** one column in this order: hero (no poster), overview, Where to watch, Trailer, Cast, Details. The reordering is done with CSS grid placement or `order`, never a JS media query, which causes an SSR hydration mismatch.
- **Details card:**
    - Director(s);
    - Writers;
    - Released (US), else the primary date labelled "Released";
    - Runtime;
    - Rated (US certification);
    - Genres;
    - Original title, when it differs.
- **Cast:** the top 12 billed, in a 3/4/6-column photo grid with name and character. Photos are `w185` and lazy, with a person-icon tile when there's none.
- **Trailer: click to play.**
    - The cover is the TMDB backdrop, with a play button and "{type} · {name}".
    - Play swaps in `https://www.youtube-nocookie.com/embed/{key}?rel=0&autoplay=1` with `referrerpolicy="strict-origin-when-cross-origin"`, at least 200×200 with no overlays, and `allow="autoplay; fullscreen; encrypted-media"`.
    - The caption reads "Plays from YouTube. YouTube may set cookies once you press play."
    - A "Watch on YouTube" link is always present (new tab).
    - With no trailer, the section is hidden.
- **Actions in the hero:**

    | User's state                                    | Shown                                                                                                                             |
    | ----------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
    | Neither                                         | "Add to Watchlist" (accent) and "Mark as watched"                                                                                 |
    | On the Watchlist                                | "On your Watchlist" (pressed) and "Mark as watched"                                                                               |
    | Watched, not on the Watchlist                   | the Watchlist toggle and a "Watched {date}" chip with Clear (×)                                                                   |
    | Watched and on the Watchlist (a rewatch queued) | "On your Watchlist", "Mark as watched" (moves the date, removes it from the Watchlist), and a quiet "Last watched {date} · Clear" |

    Guests see the same controls, which start the pending-action flow.

- **States:**
    - **Loading:** one response with no deferred props, so SSR HTML carries the cast and Director for the JSON-LD. A normal visit shows the progress bar; no Movie page skeletons are built.
    - **`stale`:** the page renders normally, and Where to watch shows "may be out of date".
    - **Source unavailable, row present:**
        - stored fields render;
        - Trailer and Cast show "temporarily unavailable" panels;
        - Director, Writers and Rated read "Temporarily unavailable";
        - the certification badge is hidden.
    - **Sparse:** a blurred-poster hero, no tagline, "Not yet rated", no Trailer section, the Availability empty state, a short cast.
    - **Removed Movie:** a 410 for everyone, with `noindex`.
        - Copy: "This movie is no longer available. Our data source, TMDB, has removed this movie, so we can't show its details any more."
        - No title.
        - A signed-in user with facts sees "It's still in your lists" with Remove and Clear (toast and Undo).
        - Links to Discover, plus Your Watchlist when signed in.
- **On view:** `last_viewed_at` is updated. `movie_viewed` is recorded for signed-in users with the referrer (`discover`, `search`, `watchlist`, `watched`, `picker`, `direct`).

### Watchlist and Watched pages

From [Watchlist and Watched pages](./issues/25-watchlist-and-watched-pages.md):

- **Postgres only,** so the pages have no `stale` or `unavailable` states and are unaffected by TMDB outages. Both are behind `auth`, `noindex` and client-rendered. The nav items stay visible to guests and lead to login.
- **The poster-card grid only,** 2–6 columns (the card from [Visual language and app shell](./issues/06-visual-language-and-shell.md)).
    - The card has the poster with the floating bookmark, the title (2 lines), the year, and one External rating: IMDb, else TMDB, else "Not yet rated".
    - The card is one link target plus its controls.
- **Card date lines:**
    - Watchlist: none, except "Last watched {date}" on a rewatch-queued Movie.
    - Watched: "Watched {date}", or "Last watched {date}" when it's back on the Watchlist.
    - Dates are in the user's locale, short form ("12 Mar 2026"), with tabular numbers.
- **Sort menus,** each a fixed list with no direction toggle:

    | Page      | Options (default first)                                                     |
    | --------- | --------------------------------------------------------------------------- |
    | Watchlist | Recently added, Oldest added, Title A–Z, Newest release, Oldest release     |
    | Watched   | Recently watched, Oldest watched, Title A–Z, Newest release, Oldest release |
    - Release sorts use the full `release_date`, with undated Movies last in both directions.
    - Title sort ignores case and accents and keeps leading articles.
    - Ties break on the `user_movies` id.
    - The sort lives in `?sort=` (omitted for the default) and isn't remembered. A change replaces the history entry and resets the loaded pages. Unknown values are rejected.

- **No filters** on either page.
- **Paging:**
    - `Inertia::scroll()` over cursor (keyset) pagination, 48 per page, with `<InfiniteScroll manual>` and "Show more".
    - Keyset paging is required so optimistic removals never skip Movies.
    - Each sort uses a non-null key (for example a coalesced `release_date`) plus the id tiebreak, and expressions are aliased in the select.
    - Movies without a title are excluded from the paginated query.
- **"No longer available" group,** above the grid and hidden when empty. It holds Removed Movies and cap-cleared Movies. It isn't paginated or sorted with the grid.
    - Heading: "TMDB has removed these movies, so we can't show their details."
    - Each card is the missing-poster tile with "Removed movie" (or "Details temporarily unavailable" for a cap-cleared Movie) and the user's own date ("Added {date}" / "Watched {date}").
    - The card links to the Movie page, and its "⋯" offers Remove or Clear Watched.
- **Header:**
    - the Watchlist / Watched switch (every screen size);
    - the title with a count ("Watchlist · 42"), which includes the group and updates optimistically;
    - the sort menu;
    - on the Watchlist, "Pick for me", hidden when the picker has no candidates.
- **Empty states:**
    - **Watchlist:** "Your Watchlist is empty" / "Tap the bookmark on any movie to save it here.", then the cached Trending grid (hidden if unavailable).
    - **Watched:** "Nothing marked as watched yet" / "Mark a movie as watched from its page or your Watchlist.", with a Watchlist link.

    Removing the last card shows the empty state and focuses its heading.

- **Loading:**
    - one response with no deferred props;
    - Show more shows a spinner and appended shimmer cards;
    - a sort change dims the current grid until the new first page arrives;
    - Back restores the sort, the loaded pages and the scroll position.

### Movie picker

From [Movie picker](./issues/12-movie-picker.md), as amended by [Watchlist interactions](./issues/13-watchlist-interactions.md) and [Watchlist and Watched pages](./issues/25-watchlist-and-watched-pages.md):

- **Candidates:** the user's Watchlist, rewatches included, minus Movies without details (Removed Movies, and by derivation cap-cleared ones). There's no "exclude watched" toggle.
- **Genre filter:** an optional multi-select, any-match, listing only genres present among the candidates. It belongs to the dialog and resets on open. Nothing is stored.
- **Selection:**
    - Server-side uniform random over the candidates (Postgres `random()`), through one authenticated endpoint called with `useHttp`.
    - The response is the chosen Movie's details plus `pool_size`. The client never receives the Watchlist.
    - Pick again sends the previous id, which is excluded when two or more candidates qualify. The server keeps no state.
- **Placement:** a reka-ui `Dialog` (a bottom sheet on mobile), opened from "Pick for me" in the Watchlist header. There's no route, and guests never see it.
- **Content:**
    - poster, title, year, runtime, genres;
    - External ratings, each credited;
    - the overview cut to about three lines;
    - Streaming service logos in the Stream and Free groups for the user's country, from stored Availability, with the JustWatch credit and the freshness line;
    - actions: View details (referrer `picker`), Pick again, Mark as watched.
- **Reveal:** a crossfade of about 200 ms (instant under reduced motion). The live region announces "Picked: {title} ({year})".
- **Edge states (races only):**
    - No candidates: "Your Watchlist is empty. Save a few Movies to pick from." with a Discover link.
    - The genres match nothing: "No Movies on your Watchlist match these genres" with "Clear genres".
    - One candidate: it's shown, Pick again is disabled, and the text says "This is the only match".
- **Analytics:** `movie_picker_used` with `pool_size`.

### Country and Availability

From [Country and Availability presentation](./issues/11-country-and-availability.md), as amended by [Movie detail page](./issues/24-movie-detail-page.md) and [Cache lifetimes and refresh schedule](./issues/26-cache-lifetimes-and-refresh.md):

- **Supported countries:** a config allow-list of ten: US, CA, GB, AU, NZ, IE, DE, FR, ES, IT. Adding one is a config change plus a backfill. Unsupported countries can't be selected.
- **Precedence:**
    1. the signed-in user's `preferred_country`;
    2. the guest country cookie (only ever an explicit choice, and ignored unless supported);
    3. the edge country header (`CF-IPCountry` or Cloud's equivalent), if supported;
    4. US.

    Detection is recomputed per request and never stored, and no geo-IP database is used. On sign-in or registration, a guest's explicit choice is copied to the account only if the account has none.

- **Unsupported detected country:** "Showing US availability. Your country isn't supported yet", with the selector.
- **The Where to watch block (Movie page):**
    - the heading and a "Showing {country} ▾" selector;
    - tabs Stream, Free (captioned "Free, may include ads"), Rent and Buy, each with its count;
        - empty tabs are hidden, and the first non-empty one is selected;
        - the WAI-ARIA tabs pattern, including arrow keys;
    - entries are logo-and-name rows in TMDB's `display_priority` order, not clickable. Channel variants aren't merged;
    - the caveat: "Availability changes often and may be incomplete. Not listed doesn't mean unavailable.";
    - "Streaming data from JustWatch · Updated N days ago", plus "may be out of date" past 7 days. The JustWatch credit is text only: no JustWatch logo without JustWatch's permission ([01](./issues/01-tmdb-terms.md));
    - "See all watch options on TMDB", built from `tmdb_id` and the country, opening in a new tab. It is the only link, and nothing implies a deep link;
    - empty state: "We don't have streaming information for this movie in {country}.", with the caveat, credit and link still shown.
- **Trending stays global.** The country affects only Availability.
- **The Privacy page** says the country is inferred from the connection at the edge and not stored.

### Visual language and app shell

From [Visual language and app shell](./issues/06-visual-language-and-shell.md), with the `control` token from [Accessibility verification](./issues/29-accessibility-verification.md). The throwaway prototype is on branch `prototype/visual-language-and-shell`, route `/prototype/shell?variant=C`.

- **Palette (charcoal):**

    | Token       | Dark                       | Light     |
    | ----------- | -------------------------- | --------- |
    | background  | `#101215`                  | `#F4F4F5` |
    | surface     | `#181B20`                  | `#FFFFFF` |
    | raised      | `#20242B`                  | `#E8E8EB` |
    | line        | `#2C3139`                  | `#D9D9DE` |
    | control     | `#6B7280`                  | `#7D808A` |
    | text        | `#ECEEF2`                  | `#111216` |
    | muted       | `#9AA1AD`                  | `#565964` |
    | accent      | `#4D8DFF`                  | `#1F5BDB` |
    | accent fill | `#2860EE`, with white text | —         |
    | IMDb label  | `#F5C518`                  | `#7A5C00` |
    | TMDB label  | `#5FD4F5`                  | `#00708F` |
    - `control` is only for borders that identify a control. `line` is for dividers.
    - An active state never sits on a raised surface: accent fill on raised is 2.96:1.

- **Type:** Inter (400/500/600/700), loaded through `laravel-vite-plugin`'s font helper, which fetches it at build time so Reelio serves it itself.
    - It replaces Instrument Sans.
    - Numbers for years, scores and dates are tabular.
    - Inter 400 and 600 (Latin WOFF2) are preloaded, and `fontaine` generates metric-adjusted fallbacks.
    - **Gotcha:** set the family in `app.css`'s `@theme inline` and in the Vite `fonts` entry; overriding `--font-sans` at runtime does nothing.
- **Theme:** a user choice that defaults to **Dark**. There's a sun/moon toggle in the top bar (on mobile too), and the Appearance settings page (Light / Dark / System) stays. Both write the existing `appearance` cookie.
    - **Gotcha:** update the inline `html` background colours in the root Blade view to the charcoal values, or each theme flashes on load.
- **Shape and icons:** 8px corners on posters, 12px on rows, 16px on the hero, pill nav and toggles. Icons are Lucide. The focus ring is 2px accent with a 2px offset.
- **Skeletons:** shimmer blocks shaped like the content they replace, static under `prefers-reduced-motion`.
- **Desktop shell:** a top bar with no sidebar: the wordmark on the left; a pill nav (Discover, Search, Watchlist, Watched; the active item is filled) in the centre; the theme toggle and Profile avatar menu (settings, log out) on the right. Only the nav items prefetch on hover.
- **Mobile shell:** a fixed bottom nav (Discover, Search, Watchlist, Profile) that respects the safe area. The top bar shrinks to the wordmark and theme toggle. Watched is reached through the Watchlist / Watched switch.
- **Footer** on every page, padded clear of the bottom nav: the TMDB logo and notice ("This website uses TMDB and the TMDB APIs but is not endorsed, certified, or otherwise approved by TMDB.", linked to themoviedb.org), plus About, Privacy, Terms and Accessibility in a fixed order. Reelio's wordmark stays more prominent than the TMDB logo.
- **Missing poster:** a raised tile with a Lucide film icon and the title in muted text, used everywhere a poster would be. Failed images fall back to it (cast photos use the person tile).

### Images and performance

From [Images and performance budget](./issues/27-images-and-performance.md):

- **Images are hotlinked** from `image.tmdb.org` at TMDB's sizes. They're never resized or rehosted, and `original` is never used. Every image has `srcset` width descriptors and a matching `sizes`.

    | Surface                                      | `srcset`                                            |
    | -------------------------------------------- | --------------------------------------------------- |
    | Poster cards (Watchlist, Watched, Search)    | `w185`, `w342`, `w500`                              |
    | Discover row posters                         | `w92`, `w154`                                       |
    | Movie page hero poster (from `sm`)           | `w342`, `w500`                                      |
    | Picker poster                                | `w185`, `w342`                                      |
    | Cast photos                                  | `w185`                                              |
    | Streaming service logos                      | `w92`                                               |
    | Discover and Movie page hero backdrops, glow | `<picture>`: `w780` below 768px, `w1280` from 768px |
    | Open Graph image                             | backdrop `w1280`                                    |

- **Priority:**
    - `preconnect` to `image.tmdb.org` in the root layout.
    - The hero backdrop is the only `fetchpriority="high"` image, and it loads eagerly.
    - Grid pages load their first 6 cards eagerly.
    - Everything else is `loading="lazy"` and `decoding="async"`.
- **Layout stability:** every image sits in a fixed-ratio box filled with the raised colour (posters and profiles 2:3, backdrops 16:9, logos 1:1).
- **Targets** (mobile, p75, warm requests; cold starts after idle are exempt):
    - LCP ≤ 2.5 s, CLS ≤ 0.1, INP ≤ 200 ms;
    - server p95 ≤ 300 ms (Nightwatch) for Discover, the Watchlist and Watched pages, and a Movie page without an in-request refresh;
    - cached search ≤ 500 ms.
- **JavaScript budget:** at most 150 KB gzipped of initial JS for Discover and a Movie page. A small script reads Vite's build output in CI (no new dependency). Pages stay code-split.
- **No prefetch of Movie links** (it could create rows or set off a refresh), and no instant visits.

### Public pages, SSR and SEO

From [Public pages: SSR, SEO metadata and sitemap scope](./issues/14-public-pages-seo.md):

- **SSR only for public pages:** Discover, Movie pages, About, Privacy, Terms and Accessibility. It's off per route for Search, auth, the Watchlist and Watched pages and settings. If the SSR process is down, Inertia renders on the client.
- **Indexing flag:** one config flag, default **off**.
    - **Off:** `robots.txt` is `Disallow: /`, every page is `noindex`, and there's no sitemap.
    - **On:** it's switched on only after a favourable written answer in [Confirm TMDB's classification of Reelio](./issues/16-tmdb-classification.md).
- **Indexable when the flag is on:** Discover, Movie pages, About, Privacy, Terms (and Accessibility; see Further Notes).
    - Everything else gets `X-Robots-Tag: noindex` from middleware: Search, auth, Watchlist, Watched, settings, the picker endpoint, the entry route's 301, and 410 pages.
    - Private pages are not disallowed in `robots.txt`, because crawlers must fetch them to see the `noindex`.
- **One `<Head>` helper** handles:
    - titles: "{Title} ({year}) · Reelio", other pages "{Page} · Reelio";
    - the description: the overview cut to about 155 characters at a word boundary (Discover has a fixed line);
    - Open Graph and Twitter images: the backdrop `w1280`, else the poster, else a static card;
    - the canonical `/movies/{slug}-{id}`.
- **JSON-LD:** Movie pages carry schema.org `Movie` with `name`, `datePublished`, `director`, `genre`, `duration`, `image`, `description` and `url`. There's no `aggregateRating`.
- **Sitemap:** every Movie row that has details (no Removed or cap-cleared Movies), with `lastmod` from `synced_at`. It's a sitemap index split at 50k URLs, built daily while the flag is on.
- **About page:** the TMDB logo and notice, JustWatch's credit, IMDb's credit, and what Reelio is ([TMDB terms](./issues/01-tmdb-terms.md)).

### Accessibility

From [Accessibility verification](./issues/29-accessibility-verification.md). The target is WCAG 2.2 AA.

- **Control borders** use the `control` token (3:1). State indicators need 3:1 against their surroundings.
- **Sticky bars:** `scroll-padding-top` and `scroll-padding-bottom` match the top bar and bottom nav, and toasts sit above the bottom nav.
- **Target sizes:** at least 24×24 everywhere. On mobile, 44×44 for the bookmark toggle, the card "⋯", the nav items, the theme toggle and Pick for me. The floating bookmark sits on a solid chip.
- **Page changes:**
    - "Skip to content" is the first focusable element.
    - On each page visit (not partial reloads, prefetches or actions), focus moves to the page's single `<h1>` (`tabindex="-1"`), and the title updates.
    - Every page has exactly one `<h1>`.
- **One polite live region** in the shell, for search results and errors, picker reveals, and 429 and error lines. Toasts keep their own region. Loading isn't announced.
- **Alt text:**
    - posters on cards and rows, hero backdrops, the trailer cover, the Movie page poster and cast photos: empty;
    - Streaming service logos: the service name;
    - TMDB, IMDb and JustWatch logos: their names.

    The trailer iframe has `title="Trailer: {title}"`, and the play button is labelled "Play trailer: {title}".

- **Language:** `<html lang="en">`, and `lang="{original_language}"` on the original title.
- **Authentication:** paste is never blocked, with correct `autocomplete` values, no CAPTCHA, and passkeys kept.
- **Dialogs** trap focus and return it to the control that opened them.
- **The `/accessibility` statement** gives the WCAG 2.2 AA target, the last review date, known limits (the YouTube player; Availability data from TMDB and JustWatch) and the contact email.

### Security

From [Rate limiting and security headers](./issues/28-rate-limits-and-security.md):

- **Keying:** by user id when signed in, by IP for guests. A deploy check confirms the IP is real and can't be spoofed. If it fails, trust only Cloudflare's published ranges, never `*`.
- **Application limits:**

    | What                                                                              | Limit                                              | Key        |
    | --------------------------------------------------------------------------------- | -------------------------------------------------- | ---------- |
    | Registration                                                                      | 10/hour                                            | IP         |
    | Forgot-password                                                                   | 5/min (the broker's 60 s per-email throttle stays) | IP         |
    | Reset-password                                                                    | 5/min                                              | IP         |
    | Confirm-password, account deletion                                                | 5/min                                              | user       |
    | Profile update                                                                    | 6/min                                              | user       |
    | Search                                                                            | 60/min                                             | user or IP |
    | Movie creation (entry route and catalog-id actions, only when no row exists)      | 20/min                                             | user or IP |
    | Watchlist and Watched actions and Undo (guests storing a pending action included) | 60/min, shared                                     | user or IP |
    | Movie picker                                                                      | 30/min                                             | user       |
    | CSP reports                                                                       | 10/min                                             | IP         |

    Fortify's existing login, 2FA, passkey and verification limits stay.

- **Forgot-password** always says "If that email has an account, we've sent a link". Registration still reports a taken email.
- **429s** carry `Retry-After` and one line: "You're going too fast. Try again in a moment."
    - It appears in the error toast (with rollback) for actions, inline on Search and in the picker, and as a styled 429 page for full visits.
- **Edge:**
    - Cloud's basic rate limit (100/min per IP) is on in production only, with Throttle. Revisit it when indexing is on.
    - `X-Frame-Options: DENY` and `nosniff` stay at their defaults.
    - HSTS uses the edge toggle in production: one year, without `includeSubDomains` or `preload`.
- **One app middleware on every environment** sets:
    - the CSP;
    - `Referrer-Policy: strict-origin-when-cross-origin`;
    - Permissions-Policy: deny `camera`, `microphone`, `geolocation`, `payment`, `usb`; leave the passkey features alone; delegate `autoplay`, `fullscreen`, `encrypted-media` to `https://www.youtube-nocookie.com`;
    - `Cross-Origin-Opener-Policy: same-origin`.

    There's no COEP. The middleware is Reelio's own, with no `spatie/laravel-csp`.

- **CSP** (enforced from day one, everywhere; the Vite dev server's origin and websocket are added while it runs):

    ```
    default-src 'self';
    script-src 'self' 'nonce-{per-request}';
    style-src 'self' 'unsafe-inline';
    img-src 'self' data: https://image.tmdb.org;
    font-src 'self';
    connect-src 'self' https://<org>.ingest.de.sentry.io;
    frame-src https://www.youtube-nocookie.com;
    frame-ancestors 'none';
    form-action 'self';
    base-uri 'none';
    object-src 'none';
    report-uri /csp-report
    ```

    - The nonce is applied through `Vite::useCspNonce()` and on the root view's inline appearance script.
    - `/csp-report` needs no CSRF token or sign-in, and always returns 204.
    - It strips query strings from `document-uri`, `referrer` and `blocked-uri`, keeps only the directive, those fields and the source location, and logs a warning (no IP).

- **Outbound URLs** are built from checked ids, never passed through from TMDB:
    - the TMDB watch page from `tmdb_id` and the country;
    - YouTube URLs from a key matching `^[A-Za-z0-9_-]{11}$` (otherwise the trailer is dropped);
    - image paths matching `^/[A-Za-z0-9_-]+\.(jpg|png|svg)$` under the fixed base.

    External links open in a new tab with `rel="noopener noreferrer"`. `v-html` is a lint error, except for the server-generated 2FA QR code.

- **Intended URLs:** one guard accepts only relative paths starting with a single `/` (not `//` or `/\`), else falls back to Discover.
- **Input and cookies:**
    - `q` ≤ 100 characters, `page` 1–500, `sort` from the fixed list;
    - the country cookie must name a supported country;
    - the `appearance` cookie must be `light`, `dark` or `system` (the fallback is in Further Notes); `HandleAppearance` currently echoes it unchecked into the inline script;
    - the session cookie is `Secure`, `HttpOnly` and `SameSite=Lax` in production.
- **Audits:** `composer audit` and `npm audit --audit-level=high` run as a required CI job. Dependabot security alerts and updates are on.

### Analytics and monitoring

From [Analytics and error monitoring](./issues/08-analytics-and-monitoring.md), as amended by [Watchlist interactions](./issues/13-watchlist-interactions.md), [Deployment and operations](./issues/19-deployment-and-operations.md) and [Search experience](./issues/23-search-experience.md):

- **First-party, server-side events** in Postgres:
    - no browser script, analytics cookies or consent banner;
    - signed-in `user_id` only, with no IP, user agent or anonymous id; anonymous visitors aren't measured;
    - dispatched on the `default` queue after the response;
    - a recording failure is swallowed and reported to Nightwatch.

    | Event                                            | Properties                                                     |
    | ------------------------------------------------ | -------------------------------------------------------------- |
    | `movie_searched`                                 | `query_length`, `result_count` (each page-1 response; no text) |
    | `movie_viewed`                                   | `movie_id`, `referrer`                                         |
    | `movie_watchlisted`, `movie_unwatchlisted`       | `movie_id`, `source`                                           |
    | `movie_marked_watched`, `movie_unmarked_watched` | `movie_id`, `source`, `was_on_watchlist`                       |
    | `user_movie_action_undone`                       | `movie_id`, `action`                                           |
    | `movie_picker_used`                              | `pool_size`                                                    |
    | `availability_link_clicked`                      | `movie_id`, `country`                                          |
    | `account_registered`                             | none                                                           |

- **`metrics:report`:** an Artisan command over documented SQL, run by hand, with each metric (e.g. "weekly returning") defined in one place. Searches are counted as distinct users searching per day; there's no zero-result rate.
- **Backend monitoring:** Laravel Nightwatch through Cloud, in the US region.
    - Requests are sampled at 10%; errors, jobs and outbound requests at 100%.
    - Redaction runs in the agent, and `q` is redacted from request URLs.
    - A spend cap is set.
    - Authentication failures are a counted log line with no email or IP.
- **Frontend monitoring:** Sentry free plan, browser errors only, EU region.
    - No replay or tracing; IP collection off (and "don't store IP addresses" on); PII scrubbed; `userInfo` off.
    - `q` is redacted from URLs and breadcrumbs.
- **Owner email alerts:**
    - an exception spike;
    - any failed job (Cloud's Queues dashboard is the fallback if Nightwatch can't see `cloud` jobs);
    - a sustained TMDB failure rate;
    - a 401 from TMDB;
    - a Movie with user facts more than 14 days overdue for refresh;
    - an IMDb table more than 3 days old.

### Privacy, terms and retention

From [Privacy, terms and retention](./issues/30-privacy-terms-and-retention.md):

- **The controller** is the owner, a named individual in the USA, with a dedicated contact address on the final domain. The same address serves the Terms and the accessibility statement.
- **Where data lives:** hosting and Nightwatch are in the US, and Sentry in the EU. Transfers rely on each vendor's data processing agreement.
- **No EU or UK representative** for the beta: an accepted risk.
- **Sessions:**
    - a session handler extending Laravel's database handler stops writing `ip_address` and `user_agent`, and a migration drops both columns;
    - `sessions.user_id` cascades on delete, so deleting an account signs it out everywhere.
- **Lawful bases:** contract (the account, Watchlist and Watched) and legitimate interests (analytics, security logs, monitoring, rate limiting). There's no consent banner. The only cookies are:
    - the session cookie and `XSRF-TOKEN`;
    - remember-me, if ticked;
    - `appearance`, only when chosen;
    - the country cookie, only when chosen;
    - YouTube's cookies, only after play.
- **Published retention:**

    | Data                                                             | Kept                                                             |
    | ---------------------------------------------------------------- | ---------------------------------------------------------------- |
    | Account, Watchlist, Watched                                      | until deletion (immediate hard delete), then ≤ 7 days in backups |
    | Unverified accounts with no facts                                | deleted 30 days after registration                               |
    | Sessions                                                         | 2 h idle; remember-me up to 400 days or sign-out                 |
    | Password-reset tokens                                            | 60 min                                                           |
    | Pending guest action, Undo snapshot                              | in the session (30 min; latest only)                             |
    | Analytics events                                                 | 13 months, then anonymous counts; deleted with the account       |
    | Failed jobs                                                      | 7 days                                                           |
    | App logs, Cloud access logs, CSP reports, `account_deleted` line | 7 days                                                           |
    | Rate-limit counters                                              | ≤ 1 h                                                            |
    | Nightwatch                                                       | 14 days                                                          |
    | Sentry, Resend                                                   | each service's plan retention, confirmed at build                |
    | Catalog data                                                     | no personal data                                                 |

- **Rights:**
    - name, email, password, preferred country and deletion are self-serve;
    - access and copy requests go to the contact address and are answered within a month with a JSON export.
- **Third parties listed:**

    | Third party                     | Receives                                     |
    | ------------------------------- | -------------------------------------------- |
    | Laravel Cloud (AWS, Cloudflare) | every request and all stored data            |
    | Resend                          | the user's email address and the emails sent |
    | Nightwatch                      | redacted server data with the user id        |
    | Sentry                          | browser errors, without the IP               |
    | `image.tmdb.org`                | every visitor's IP                           |
    | YouTube                         | only after play                              |
    | TMDB's API, IMDb, JustWatch     | no personal data                             |

- **Age:** 13 or older, stated in the Terms and under the registration form. There's no age field.
- **Terms:**
    - free and permanently non-commercial;
    - a beta: features may change, and closure comes with 30 days' email notice and data on request;
    - no scraping, bulk extraction or automated access, and abusive accounts can be closed;
    - no guarantee that Availability or ratings are current;
    - data and images belong to TMDB, IMDb and the rights holders, with their notices;
    - liability limited;
    - the product name;
    - the governing law of the owner's US state, without overriding local consumer rights.
- **Agreement:** under the registration button: "By creating an account you agree to the Terms and have read the Privacy Policy", with links and no checkbox. Each page shows "Last updated". Material changes are emailed to verified users 14 days ahead, and git history is the record.
- **Pages:** `/privacy` and `/terms` are server-rendered Vue pages, not Markdown. Each starts with a plain-English summary. The copy is drafted at build from these facts, the owner reviews it, and there's no professional legal review. The pages claim no specific legal compliance.
- **Privacy page mentions:**
    - the country inference;
    - YouTube cookies after play;
    - deleted accounts persisting up to 7 days in backups;
    - Cloud's access logs (and whether they hold `?q=`, checked at build).
- **Shared devices:** Inertia `clearHistory` on sign-out and `encryptHistory` on private pages.

### Operations

From [Deployment and operations](./issues/19-deployment-and-operations.md):

- **Deploy pipeline:**
    - Push-to-deploy is off. A GitHub Actions job runs after the required checks on `main` and POSTs Cloud's deploy hook with `?commit_hash=${{ github.sha }}`.
    - Build: `composer install --no-dev && npm ci && npm run build:ssr`. Deploy: `php artisan migrate --force`.
    - A failed health check keeps the previous release serving.
- **Migrations** are backward compatible with the previous release. `migrate:rollback` is never run in production, and destructive steps need explicit owner approval. Rollback means redeploying the previous commit.
- **Job rules:**
    - jobs are idempotent (delivery is at least once);
    - delays are at most 15 minutes;
    - payloads carry ids, not models;
    - no job does more than 90 s of work.
- **Backups:** Postgres point-in-time recovery with a 7-day window, and a restore drill before launch and after any large schema change.
    - Account deletion logs an `account_deleted` line carrying only the `user_id`.
    - The restore runbook re-deletes every user deleted since the restore point.
    - Log retention (7 days on Growth) must stay equal to the backup window.
- **Logs:**
    - production at `info`;
    - path (with `q` redacted), status, `user_id` and exception class;
    - never bodies, passwords, tokens, emails, search text or IPs;
    - exception context is redacted;
    - no log drain.
- **Availability target:** the PRD's 99.5% is dropped for the prototype phase, and there's no external uptime monitor. A full outage with no traffic raises no alert (accepted).
- **Secrets:** the TMDB token, Resend key and Nightwatch and Sentry credentials live in Cloud's Secrets Manager, per environment.
- **Live checks:** a weekly scheduled GitHub Action runs the `live` test group against TMDB with a read-only token (a repo secret), and emails the owner on failure.

### Starter-kit changes

From [Visual language and app shell](./issues/06-visual-language-and-shell.md), [Test strategy and tooling](./issues/07-test-strategy.md), [Rate limiting and security headers](./issues/28-rate-limits-and-security.md) and [Privacy, terms and retention](./issues/30-privacy-terms-and-retention.md):

- **Removed:** `AppSidebarLayout`, `AppSidebar`, `AppSidebarHeader`, `NavMain`, `NavFooter`, `Breadcrumbs`, the `ui/sidebar` components, `AuthCardLayout`, `AuthSplitLayout`, the Dashboard and Welcome pages with `PlaceholderPattern` and their tests, and `sidebar_state`.
- **Replaced:**
    - `AppHeaderLayout`, `AppHeader`, `AppShell` and `AppContent` by the new shell (`AppLayout` points at it);
    - `AppLogo` and `AppLogoIcon` by the wordmark;
    - `ui/skeleton` by the shimmer;
    - the theme tokens, Blade inline colours and Instrument Sans by the charcoal tokens and Inter.
- **Kept:**
    - `NavUser`, `UserMenuContent`, `UserInfo` (behind the avatar);
    - `AppearanceTabs`, `useAppearance`, `HandleAppearance` (default `dark`, with cookie validation added);
    - the settings layout and pages (inside the new shell, plus preferred country in Profile);
    - `AuthSimpleLayout` (re-themed).
- **Fortify:** throttles on the unthrottled routes, a non-enumerating forgot-password response, the registration Terms line, and pending-action completion after login and registration.
- **Config:** `phpunit.xml` moves from in-memory SQLite and the array cache to Postgres and Redis. `.env.example` follows the Herd setup.

### Dependencies

- **Approved:**
    - `@testing-library/vue` and `pestphp/pest-plugin-browser` (plus Playwright's browser download, locally and in CI) ([07](./issues/07-test-strategy.md));
    - `fontaine` as a dev dependency ([27](./issues/27-images-and-performance.md)).
- **Needs the owner's approval** before the build adds it:
    - `aws/aws-sdk-php`, for Cloud managed queues ([19](./issues/19-deployment-and-operations.md)).
- **Implied by decisions but never explicitly approved** (confirm with the owner):
    - `resend/resend-php`: Laravel's Resend mail driver requires it;
    - Nightwatch's agent package (`laravel/nightwatch`; confirm the name and whether Cloud installs it);
    - Sentry's browser SDK (`@sentry/vue`).
- **Rejected:** `spatie/laravel-csp`, Lighthouse CI, ESLint and template-lint plugins, Horizon, OMDb.

## Testing Decisions

From [Test strategy and tooling](./issues/07-test-strategy.md), with additions from [Images and performance budget](./issues/27-images-and-performance.md), [Rate limiting and security headers](./issues/28-rate-limits-and-security.md) and [Accessibility verification](./issues/29-accessibility-verification.md). Choices per change follow the `testing-best-practices` skill.

**What makes a good test here:** it drives the app through its highest seam, the HTTP request, and asserts what a user or client sees: Inertia props, status codes, redirects, headers and database state. It never asserts how a module does its work. No feature test knows TMDB's JSON shape.

**Seams.** There are only three. All are already decided; no new ones are proposed.

1. **HTTP requests**, for every behaviour reachable through one.
2. **The Catalog adapter and Rating source interfaces.** Feature and browser tests bind in-memory fakes in the container. The fakes can return given Movies, sections and ratings, or fail per call or for every call, so `fresh`, `stale` and `unavailable` are all testable.
3. **The HTTP client**, for the TMDB adapter alone. It's tested with `Http::fake()` against recorded fixtures. The fixtures already captured in `.scratch/reelio-mvp/research/tmdb-fixtures/` (`search_inter`, `movie_157336` with every append, `trending_p2`, `upcoming_us`) move into the test suite at build.

`Http::preventStrayRequests()` is on for the whole suite.

| Layer               | Tool                                             | Covers                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| ------------------- | ------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Feature (the bulk)  | Pest 5 on Postgres with Redis                    | Every lifecycle action and Undo rule; guest pending actions; the catalog-id endpoints creating rows; the entry route (redirects, 404, 410, 503); picker candidates, genre filter, Pick again and edge states; sort and keyset paging, including after removals; Removed and cap-cleared Movies; country precedence and cookie checks; refresh triggers, purge, cap guard, `catalog:purge`; Discover assembly and dedupe; every failure state; each rate limit (429 plus `Retry-After`); headers and a fresh nonce on public, private and error pages; the intended-URL guard (`//evil.com`, `/\evil.com`); CSP report redaction; indexing flag behaviour; event recording; retention jobs; session columns and cascade. |
| TMDB adapter        | Pest with `Http::fake()` and recorded fixtures   | Parsing, Director(s), Writers, the trailer ranking (including _Interstellar_ picking "Trailer 4"), US date and certification, Availability mapping (`free` and `ads` to `free`), timeouts, bounded retries, the circuit breaker, 404 vs 401 vs 5xx and 429.                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| IMDb import         | Pest with a tiny gzipped TSV fixture             | A normal row, a missing rating, a malformed row, a header mismatch, a row count under 90% leaving the live table untouched, and an unchanged-file skip.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| Frontend logic      | Vitest via `vp test` with `@testing-library/vue` | Only client logic that makes its own decisions: search debounce and cancellation, optimistic Watchlist actions with rollback, the Undo toast flow, and theme persistence. Display-only components get no Vitest tests.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| Browser             | Pest browser plugin (Playwright)                 | Five journeys, each at desktop and mobile with `assertNoJavascriptErrors` under the real CSP: discover → details → save; pick from the Watchlist; mark as watched → see it in Watched; see Availability on a Movie page; register and log in. Each ends with `assertNoAccessibilityIssues()` at level 1. Structural image checks: aspect boxes or dimensions; exactly one `fetchpriority="high"` on Discover and the Movie page and none on grids; lazy below the fold.                                                                                                                                                                                                                                                 |
| Accessibility sweep | Pest browser plugin                              | Level 1 axe at desktop and mobile in both themes, on: Discover; Search (empty, results, no results); a Movie page and a Removed Movie; the Watchlist (empty and full) and Watched; the picker open; auth and settings pages; the 404, 429 and 500 pages.                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| Live                | Pest `live` group, excluded by default           | Real TMDB and the IMDb dataset URL, to catch fixture drift. Run weekly by a GitHub Action and by hand before launch.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |

**Prior art:** the starter kit's `tests/Feature/Auth` and `tests/Feature/Settings` show the house style for request-level Pest tests. The Dashboard and Welcome tests go with their pages.

**CI** (all required on `main`; there's no coverage threshold):

- `composer ci:check`, which runs vp check, vue-tsc, Pint, PHPStan level 7 and Pest, plus `vp test`. It uses Postgres and Redis service containers and includes the JS budget check.
- The browser job.
- The dependency audit job.

Cloud preview environments are for manual checks only. Cloud's edge settings aren't tested; they're on the deploy checklist.

## Out of Scope

**Spec follow-ups:** listed so they aren't designed now.

- **From the PRD's P1 and P2:**
    - advanced filtering (FR-003);
    - Streaming service preferences (FR-017), where merging channel variants such as Paramount+ Premium and Paramount+ Amazon Channel belongs;
    - personal ratings (FR-012);
    - manual viewing dates (FR-011), which edit the one Watched date, and a rewatch log;
    - viewing statistics (FR-013);
    - custom lists (FR-009);
    - self-serve data export (FR-021);
    - Google sign-in;
    - related Movies;
    - the advanced picker (FR-015), whose filters use the same criteria object.
- **Ratings:** Rotten Tomatoes through Fandango's licensing route.
- **Watchlist and Watched pages:** a list layout, a rating sort (with a denormalised IMDb column if needed), and a genre filter.
- **Discover:** "See all" pages for its sections.
- **Data:** change-driven refresh from TMDB's `/movie/changes` feed.
- **Measurement:** real-user Web Vitals collection, and a privacy-friendly pageview tool.
- **Legal:** appointing an EU and a UK representative if the beta grows, and a professional legal review if usage grows.
- **Operations:** an always-on replica and an uptime monitor, which bring back the 99.5% target.

**Out of scope entirely:**

- Metacritic ratings and OMDb ([OMDb ratings](./issues/03-omdb-ratings.md)).
- Any revenue: ads, affiliate links, a paid tier, sponsorships or data sales. Reelio is permanently non-commercial, and TMDB's and IMDb's licences depend on it.
- Any AI or ML use of TMDB data, which constrains the PRD's v2 ideas.
- Deep links to Streaming services (TMDB provides none).
- The PRD's non-goals: playback, TV series, social features, public reviews, recommendation algorithms, imports from streaming platforms, native apps and billing.

## Further Notes

### Launch blockers (not spec blockers)

These tickets are still open on the map. The beta is designed to work whichever way each one turns out.

| Ticket                                                                                           | What it gates                                                                                                                                                     |
| ------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [Confirm TMDB's classification of Reelio](./issues/16-tmdb-classification.md)                    | Turning the indexing flag on. A "commercial" answer keeps it off and returns to the map.                                                                          |
| [Confirm IMDb permission for the non-commercial ratings dataset](./issues/18-imdb-permission.md) | Showing IMDb ratings at launch, and the exact credit wording. If IMDb refuses: the TMDB rating only (remove the IMDb adapter), or the paid AWS Data Exchange API. |
| [Clear Houselight on the official registers](./issues/21-houselight-register-clearance.md)       | The rename. If it fails, [Product name](./issues/20-product-name.md) names Lightsdown as the next pick.                                                           |

**The rename** is one implementation ticket, a find-and-replace once clearance passes. It covers:

- `config('app.name')`;
- the wordmark;
- page titles and metadata;
- the mail sender name;
- the credit copy;
- `GLOSSARY.md` and this spec.

The contact address, the Resend sender domain, and the HSTS `includeSubDomains`/`preload` decision wait on the final domain. The prototype runs on a `laravel.cloud` address.

### Build checks (during implementation)

- Measure the IMDb import's duration on a Flex queue. If it doesn't fit in 90 s, move `imports` to Pro ([19](./issues/19-deployment-and-operations.md)).
- Confirm Nightwatch is backend-only, and Sentry's cookie position with replay and IP capture off ([08](./issues/08-analytics-and-monitoring.md)).
- Confirm Sentry's and Resend's retention before publishing them ([30](./issues/30-privacy-terms-and-retention.md)).
- Check whether Cloud's edge access logs hold full query strings, and say so on the Privacy page if they do ([23](./issues/23-search-experience.md), [30](./issues/30-privacy-terms-and-retention.md)).
- Confirm the Cloud region code in the dashboard ([30](./issues/30-privacy-terms-and-retention.md)).

### First-deploy checklist (one-time smoke test)

- The edge country header reaches the app; if not, detection falls back to US ([11](./issues/11-country-and-availability.md)).
- `$request->ip()` returns the real visitor IP, and a forged `X-Forwarded-For` or `CF-Connecting-IP` doesn't change it ([28](./issues/28-rate-limits-and-security.md)).
- The SSR toggle serves server-rendered public pages, and a crash falls back to client rendering ([14](./issues/14-public-pages-seo.md), [19](./issues/19-deployment-and-operations.md)).
- The scheduler wakes the app for 05:00 UTC without holding it awake. The App cluster is a new Flex size with a sleep timeout.
- Nightwatch sees jobs on the `cloud` queue driver; otherwise the Queues dashboard is the failed-job alert source.
- Static assets served from the edge cache don't count toward the edge rate limit.
- In production:
    - HSTS is on (one year, no `includeSubDomains` or `preload`);
    - the edge rate limit is on with Throttle;
    - `X-Frame-Options: DENY` and `nosniff` are still set;
    - Sentry's "don't store IP addresses" is on;
    - Nightwatch's spend cap is set.

### Runbooks and recurring owner checks

- **Restore drill:** restore to a new cluster, check the data, re-delete accounts found in the `account_deleted` log lines since the restore point, then delete the cluster. Run it before launch and after large schema changes ([19](./issues/19-deployment-and-operations.md)).
- **Breach response** ([30](./issues/30-privacy-terms-and-retention.md)):
    1. Contain the breach and rotate secrets.
    2. Assess what data was involved.
    3. If people are at risk, notify the UK ICO and the relevant EU authorities within 72 hours.
    4. If the risk is high, email affected users without delay, and check US state notification duties.
    5. Log every incident.
- **Manual accessibility checklist** ([29](./issues/29-accessibility-verification.md)), with a dated log of runs. The owner runs all of it before launch, and the relevant items on UI changes:
    - keyboard only, through the five journeys: tab arrow keys, dialog focus trap and return, Undo, focus after removing a card;
    - VoiceOver with Safari on macOS and iOS;
    - 200% text zoom and 400% reflow at 320 px (sideways scrolling allowed only in Discover rows);
    - text spacing;
    - reduced motion;
    - contrast over images in both themes (hero text, the bookmark chip, the play button);
    - the sticky right column, the 410 page's actions, the trailer controls, and the 429 page and messages.
- **PageSpeed Insights** (mobile) against production for Discover, a Movie page and Search, before launch and on releases touching the shell, the heroes or images. Results go in a runbook table ([27](./issues/27-images-and-performance.md)).

### Accepted risks

- No EU or UK GDPR representative during the beta.
- No uptime monitoring, so a silent full outage raises no alert.
- Cold starts after idle exceed the LCP target.
- Turning the unviewed-Movie purge off lets the `movies` table grow. The cap guard still keeps every row within TMDB's cap.
- Up to 7 days of deleted accounts in backups and logs.
- TMDB's "destination site" and SEO language is unresolved until TMDB answers.

### Owner to confirm

These gaps weren't settled by any ticket. Each comes with the reading this spec assumes.

1. **Invalid `appearance` cookie fallback.** [28](./issues/28-rate-limits-and-security.md) says fall back to `system`, but [06](./issues/06-visual-language-and-shell.md) made `dark` the default. _Assumed:_ treat an invalid value as absent, so the default applies (`dark`).
2. **`/accessibility` page.** It postdates the SSR and indexable list in [14](./issues/14-public-pages-seo.md). _Assumed:_ SSR and indexable, like Privacy and Terms.
3. **Cap-cleared Movies** (cleared by the 170-day guard but not removed). [26](./issues/26-cache-lifetimes-and-refresh.md) only sets their list treatment. _Derived:_ they're excluded from picker candidates, the sitemap and the keyset query, as Removed Movies are. Their Movie page tries the normal in-request refresh. If that fails, the page renders the "source unavailable" state with no stored fields; a cap-cleared Movie never gets the 410.
4. **Analytics roll-up storage.** [08](./issues/08-analytics-and-monitoring.md) says events older than 13 months become aggregate counts, but the aggregate table was never designed. _Assumed:_ daily counts per event name, with no `user_id`.
5. **How `availability_link_clicked` is recorded.** The TMDB watch-page link opens in a new tab, and no mechanism was chosen. The options are a same-origin redirect endpoint (built only from `tmdb_id` and a supported country, so it can't be an open redirect) or a background POST on click. _Recommended:_ the redirect endpoint, which keeps the event server-side and works without JavaScript.
6. **`queue:prune-failed` vs Cloud managed queues.** [30](./issues/30-privacy-terms-and-retention.md) schedules it daily, while [19](./issues/19-deployment-and-operations.md) says failed jobs live in Cloud's Queues dashboard. _Assumed:_ keep the command for local and database-backed queues, and publish the Cloud dashboard's failed-job retention instead if it differs from 7 days.
7. **Undecided paths:** the Undo endpoint, the picker endpoint and country selection. These are build-time naming, not decisions.
8. **`tagline`:** shown on the Movie page but missing from [09](./issues/09-movie-identity-and-storage.md)'s column list. _Assumed:_ a `movies` column.
9. **PWA.** The PRD calls the platform a "Responsive web application / Progressive Web App", but no ticket designed a manifest or service worker. _Assumed:_ responsive web only; a PWA is a follow-up.
10. **Dependencies:** approve `aws/aws-sdk-php`, `resend/resend-php`, Nightwatch's package and `@sentry/vue` (see Dependencies).
11. **Glossary gap:** there's no domain term for a cap-cleared Movie ("Details temporarily unavailable"). It's worth a `domain-modeling` pass if it's kept distinct from Removed Movie.
