# Analytics and error monitoring

Type: grilling
Status: resolved
Map: [Reelio MVP](../map.md)

## Question

How will Reelio measure its §2.3 success metrics and catch failures, without compromising its §11.4 privacy commitments?

- Product analytics tool (self-hosted vs SaaS vs server-side events only), and whether a cookie or consent banner is needed.
- The final MVP event list (PRD §12.1) and what properties each event carries without collecting personal data.
- Error monitoring for backend and frontend (e.g. Nightwatch, Sentry, or Laravel's built-ins).
- Operational metrics: outbound API latency and failures, cache hit rate.

## Comments

- From [The user–movie lifecycle](./04-user-movie-lifecycle.md) (resolved): One event per user action: marking as watched is one `movie_marked_watched` event, not also `movie_unwatchlisted`. See [The user–movie lifecycle](./04-user-movie-lifecycle.md).

## Answer

**Analytics: first-party and server-side.**

- Events are written to an `events` table in Reelio's own Postgres. No third-party analytics tool, no browser script, no analytics cookies, no consent banner. Pageviews and referrers are not measured; a privacy-friendly pageview tool is a spec follow-up.
- Events carry the signed-in `user_id` and nothing else identifying: no IP, no user agent, no anonymous ID. Anonymous visitors are not measured.
- Events are recorded through the database queue after the response. A recording failure is swallowed and reported to Nightwatch, never shown to the user.
- `events.user_id` is a foreign key with cascade delete, so account deletion removes a user's events. A scheduled job rolls events older than 13 months into aggregate counts and deletes the rows.

**MVP event list** (one event per user action, per [The user–movie lifecycle](./04-user-movie-lifecycle.md); `movie_rated` dropped until personal ratings exist; `streaming_provider_clicked` replaced because TMDB forbids deep links to Streaming services).

| Event | Properties |
| --- | --- |
| `movie_searched` | `query_length`, `result_count` (the query text is not stored) |
| `movie_viewed` | `movie_id`, `referrer` (`discover`, `search`, `watchlist`, `watched`, `picker`, `direct`); signed-in users only |
| `movie_watchlisted`, `movie_unwatchlisted` | `movie_id`, `source` |
| `movie_marked_watched`, `movie_unmarked_watched` | `movie_id`, `source`, `was_on_watchlist` |
| `movie_picker_used` | `pool_size` |
| `availability_link_clicked` | `movie_id`, `country` |
| `account_registered` | none |

**Error monitoring and operations.**

- **Backend:** Laravel Nightwatch, connected through Laravel Cloud. Requests sampled at Cloud's default 10%; errors, jobs and outbound requests at 100%. Redaction runs in the agent. A spend cap is set so a traffic spike cannot become a bill. Free plan: 300k events a month, 14-day lookback. Region chosen at app creation.
- **Frontend:** Sentry free plan, browser JS errors only. No session replay, no tracing, IP collection explicitly off (the Vue SDK infers IPs by default), PII scrubbed, `userInfo` off, EU region (fixed at org creation).
- **PRD §12.2 coverage:** TMDB latency and failures, cache hit rate, search latency, queue failures and database performance come from Nightwatch; frontend errors from Sentry. Authentication failures are a counted log line with no email or IP. The search target (under 500 ms) is checked against Nightwatch request timing for the search route.
- **Alerts:** email to the owner only, for an exception spike, any failed job, and a sustained TMDB outbound failure rate.
- **99.5% availability:** an external uptime check on the health endpoint; what that endpoint checks is for [Deployment and operations](./19-deployment-and-operations.md).

**Reporting the success metrics.** A documented set of SQL queries behind one `metrics:report` Artisan command, run by hand. No dashboard UI, no admin screen. Definitions such as "weekly returning" live in one place. LCP at p75 is not an events metric and stays with the Images and performance budget patch.

**Carry into the spec.**

- Both Nightwatch and Sentry are third-party data sharing and go on the privacy page ([Privacy, terms and retention](./../map.md) fog).
- The Nightwatch finding that it is backend-only is inferred from its docs, not stated; confirm at build time. Sentry's cookie and consent position is unconfirmed in its docs; with replay and IP capture off, no banner is expected.

- From [Deployment and operations](./19-deployment-and-operations.md) (resolved): events are queued on a Cloud managed queue (at-least-once delivery), so each event carries a client-generated UUID with a unique index to dedupe redeliveries. The alert on any failed job depends on Nightwatch seeing jobs on the `cloud` driver; verify at first deploy, with Cloud's Queues dashboard as the fallback.

- From [Search experience](./23-search-experience.md) (resolved): `movie_searched` is recorded on each page-1 search response, so live search records several events per query. `metrics:report` counts distinct users searching per day and reports no zero-result rate. The `q` query parameter is redacted from Nightwatch request URLs and from Sentry URLs and breadcrumbs.
