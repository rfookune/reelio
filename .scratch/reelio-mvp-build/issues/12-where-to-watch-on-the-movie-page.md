# 12: Where to watch on the Movie page

Spec: [Reelio MVP spec](../../reelio-mvp/spec.md)

**What to build:** The Movie page shows where a Movie can be watched in the visitor's country (US until country choice lands), grouped into Stream, Free, Rent and Buy, with the required credit, caveat and freshness. See the spec's "Country and Availability" section.

**Blocked by:** [04: Browser test harness and the sign-in journey](./04-browser-tests-and-sign-in-journey.md), [05: Analytics event recording](./05-analytics-event-recording.md), [07: Movie page layout and details](./07-movie-page-layout-and-details.md)

**Status:** ready-for-agent

- [ ] `streaming_services` and `availabilities` tables (offer types `stream`, `free`, `rent`, `buy`, plus `display_priority`); the adapter maps TMDB's `flatrate`/`free`/`ads`/`rent`/`buy` (free and ads both to `free`); a Movie's rows are replaced wholesale on each sync, only for the ten config-listed supported countries
- [ ] The block: "Where to watch" with "Showing {country}", tabs Stream, Free (captioned "Free, may include ads"), Rent and Buy with counts, empty tabs hidden, first non-empty selected, following the WAI-ARIA tabs pattern with arrow keys
- [ ] Entries are logo (`w92`, alt = service name) and name rows in `display_priority` order, not clickable, channel variants not merged
- [ ] Every block, empty state included, shows the caveat "Availability changes often and may be incomplete. Not listed doesn't mean unavailable.", the text credit "Streaming data from JustWatch" (no JustWatch logo), "Updated today / yesterday / N days ago" from `synced_at`, and "may be out of date" when that's over 7 days old
- [ ] Empty state: "We don't have streaming information for this movie in {country}."
- [ ] "See all watch options on TMDB" (new tab, `rel="nofollow noopener noreferrer"`) goes through a same-origin redirect route that records `availability_link_clicked` (`movie_id`, `country`; signed-in only) and 302s to the TMDB watch page built from `tmdb_id` and a supported country; the route is `noindex` and can't redirect anywhere else
- [ ] Placed in the right column after Details on desktop, after the overview on mobile
- [ ] The "see Availability on a Movie page" browser journey at desktop and mobile
