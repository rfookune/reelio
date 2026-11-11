# 01: Browse replaces Trending on Search

Status: resolved

Parent: [spec](../spec.md). Read [ADR 0003](../../../docs/adr/0003-browse-and-search-by-name-are-separate.md) and the glossary terms **Browse** and **Streaming service** first.

**Blocked by:** None (can start immediately)

**What to build:** When the Search page has no query, it shows Browse: a Popular list of Movies that loads more as you scroll, replacing the Trending list. Each card is the same card as a search result, with the Watchlist toggle. Typing a Name still switches to a search by Name exactly as today. This is the tracer bullet: it adds the Catalog source's new read of a Browse page (one page per call, no text query, backed by the source's Movie discover list) through the source interface, the TMDB source, the fake source used in tests and the guarded wrapper, cached like a search page with the stale fallback when the source fails. If the Search page's props are awkward to extend, prefactor them first so Browse can sit beside search by Name without disturbing it.

- [x] With no query, the Search page shows a Popular list of Movies; Trending no longer shows there, and the Discover page is unchanged
- [x] Results load page by page with infinite scroll, with a loading placeholder
- [x] Cards match search result cards, including the External rating rule, the `search` referrer and the Watchlist toggle with the viewer's facts
- [x] A query of at least the minimum length shows a search by Name with unchanged behaviour
- [x] A source failure shows the unavailable message, and a stale cached Browse page is served when the source fails
- [x] Each listed Movie's TMDB score is recorded, as search does
- [x] The TMDB source's request for a Browse page is tested with faked HTTP: it sends no text query, excludes adult listings, drops malformed results, and the page is capped to the source's last page
- [x] A feature test on the Search page covers the Browse and search modes through the fake source
- [x] The page stays client-rendered and `noindex`
