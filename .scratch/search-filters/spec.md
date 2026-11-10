# Browse filters on Search

Status: ready-for-agent

## Problem Statement

A person who opens Reelio wanting "something good to watch tonight" has only two ways in: the curated Discover page, which is the same for everyone, or a search by Name, which needs a Title in mind. They cannot say "a highly rated comedy on Netflix and Max", "a short Movie that's At home now", or "a Korean series from the 2010s". The Search page's empty state shows only Trending, so it does not help them decide either. Titles they have already seen keep turning up, and there is no way to narrow by where they can actually watch.

## Solution

The Search page gains **Browse**: a list narrowed by filters, shown whenever there is no query. With no filters it is a fresh Popular list, replacing Trending; with filters it is ranked by quality. The filters are Streaming service (several), genre, Release stage, Highly rated, runtime, Era and Original language. Browse has a Movies tab and a TV Shows tab. Typing a Name leaves Browse for a search by Name, and picking a filter leaves the search; the two are never combined ([ADR 0003](../../docs/adr/0003-browse-and-search-by-name-are-separate.md)).

For a signed-in user, Browse hides Titles they have already seen, with a toggle to show them. Filters apply instantly and live in the URL, so a filtered view can be shared and returned to.

## User Stories

1. As a visitor, I want the Search page to show a list of Titles when I haven't typed anything, so that I can find something to watch without already knowing a Name.
2. As a visitor, I want that unfiltered list to be fresh and popular, so that it doesn't show the same all-time classics every day.
3. As a visitor, I want a Movies tab and a TV Shows tab, so that I can browse the kind I'm in the mood for.
4. As a visitor, I want Movies to be the default tab, so that I don't need to choose to start.
5. As a visitor, I want to filter by one or more Streaming services, so that I only see Titles I can watch where I'm subscribed.
6. As a visitor, I want several chosen Streaming services to mean "any of them", so that selecting Netflix and Max shows what is on either.
7. As a visitor, I want the Streaming service filter to count only subscription and free offers, so that "on Netflix" means I can watch it now at no extra cost.
8. As a visitor, I want the Streaming services offered to be the ones in my country, so that I never pick a service that isn't available to me.
9. As a visitor, I want to see the most common Streaming services first, so that I can pick quickly.
10. As a visitor, I want a "More" option with a search box for the other Streaming services, so that I can still find a smaller one.
11. As a visitor, I want to filter by one genre, so that I can pick the mood of what I watch.
12. As a visitor, I want the genres offered to match the tab I'm on, so that I never choose a genre that doesn't exist for that kind.
13. As a visitor, I want my genre cleared when I switch tabs to one that has no genre of that name, so that I'm not left with an invisible filter.
14. As a visitor, I want my genre kept when I switch tabs to one that has a genre of the same name, so that I don't have to pick it again.
15. As a visitor on the Movies tab, I want to filter by Release stage, so that I can see what is In theater, At home or Coming soon.
16. As a visitor, I want In theater, At home and Coming soon to be mutually exclusive, so that one choice gives one clear list.
17. As a visitor, I want Release stage to follow my country, so that "Coming soon" means coming soon where I live.
18. As a visitor, I want Release stage to be clear that it is an estimate and not a showtimes list, so that I don't expect a cinema timetable.
19. As a visitor, I want a Release stage list to be ordered by popularity, so that unreleased Titles, which have no meaningful rating, still sort sensibly.
20. As a visitor, I want a "Highly rated" toggle, so that I can drop the Titles that aren't well regarded.
21. As a visitor, I want the toggle to say its score comes from TMDB, so that I'm not confused when a card shows a different IMDb score.
22. As a visitor, I want the Highly rated toggle to ignore Titles with only a handful of votes, so that obscure Titles with a few perfect scores don't dominate.
23. As a visitor on the Movies tab, I want to filter by runtime (under 90 minutes, 90 to 120 minutes, over 2 hours), so that I can pick something that fits my evening.
24. As a visitor, I want to filter by decade, so that I can ask for a recent Title or a classic.
25. As a visitor, I want Era to work for both Movies and TV Shows, so that the filter doesn't disappear on the TV Shows tab.
26. As a visitor, I want to filter by original language from a short list, so that I can find a Korean thriller or a Spanish series.
27. As a visitor, I want the language filter labelled "Original language", so that I'm not led to expect dubbing or subtitles.
28. As a visitor, I want the language list shown in each language's own name, so that I recognise it.
29. As a visitor, I want a Movie-only filter to tell me when it limits me to Movies, so that an empty TV Shows list doesn't look like a bug.
30. As a visitor, I want choosing a Movie-only filter while on the TV Shows tab to take me to the Movies tab, so that the choice has an effect.
31. As a visitor, I want filters to combine, so that I can ask for a highly rated comedy on Netflix from the 2010s.
32. As a visitor, I want the list to update as soon as I change a filter, so that I never have to find an Apply button.
33. As a visitor, I want to see how many filters are active, so that I know why the list looks the way it does.
34. As a visitor, I want a one-tap way to clear all filters, so that I can start over quickly.
35. As a visitor, I want a sort choice of Top rated, Popular and Newest, so that I can order the list how I want.
36. As a visitor, I want the sort to default to Top rated once I've chosen a filter, so that I see the best Titles first.
37. As a visitor, I want the sort to stay Popular when I choose In theater or Coming soon, so that the list isn't ranked by scores that don't exist yet.
38. As a visitor, I want to scroll to load more results, so that I can keep browsing the same way as a search by Name.
39. As a visitor, I want the filters kept in the URL, so that I can share a filtered view or return to it.
40. As a visitor, I want the back button to return me to my previous filters, so that browsing feels natural.
41. As a visitor, I want typing a Name to switch to a search by Name, so that I can still look for a specific Title.
42. As a visitor, I want the filters to be cleared when I type a Name, so that I'm not told a search is filtered when it isn't.
43. As a visitor, I want choosing a filter to clear my typed Name, so that the page is never showing a mix I can't explain.
44. As a visitor, I want the All, Movies and TV Shows chips to keep working in a search by Name, so that search is unchanged.
45. As a visitor, I want a clear message when no Titles match my filters, so that I know to loosen them.
46. As a visitor, I want a loading placeholder while a filtered list loads, so that the page doesn't look broken.
47. As a visitor, I want a clear message when the Catalog source can't answer, so that I can try again.
48. As a visitor on a phone, I want the filter bar to fit a small screen, so that I can browse from the sofa.
49. As a keyboard or screen-reader user, I want every filter to be operable and announced, so that I can use Browse without a mouse.
50. As a signed-in user, I want Titles I have already seen hidden by default, so that every suggestion is something I haven't watched.
51. As a signed-in user, I want a Movie I marked as Watched hidden, so that I'm not offered it again.
52. As a signed-in user, I want a Movie or TV Show I gave a User rating hidden, so that rating something counts as having seen it.
53. As a signed-in user, I want a Completed TV Show hidden, so that I'm not offered a show I've finished.
54. As a signed-in user, I want a TV Show that is In progress or Not started to stay visible, so that I can be reminded to continue or start it.
55. As a signed-in user, I want Titles on my Watchlist to stay visible, so that I can see them next to new options.
56. As a signed-in user, I want a "Show seen" toggle, so that I can see everything when I want to.
57. As a signed-in user, I want the Watchlist toggle on each Browse card, so that I can save a Title straight from the list.
58. As a guest, I want Browse to work without an account, so that I can try the app before signing up.
59. As a guest, I want no "Show seen" toggle and no hiding, so that the page doesn't offer something that needs an account.
60. As a guest, I want the Watchlist action on a Browse card to behave as it does on search results, so that the experience is consistent.
61. As the site operator, I want the Browse source calls cached, so that popular filter combinations don't overload the Catalog source.
62. As the site operator, I want Browse to stay usable when the Catalog source is slow or down, so that a failure shows a clear message and not a broken page.
63. As the site operator, I want unknown or malformed filter values ignored, so that a crafted URL cannot produce an error or an unbounded source call.
64. As the site operator, I want Browse recorded as an analytics event without any personal data, so that I can see how the filters are used.

## Implementation Decisions

- **Modes.** The Search page has two modes. With a query of at least the minimum length it is a search by Name, unchanged. Otherwise it is Browse. A request never carries both a query and filters: if a query is present, filters in the URL are ignored, and the client clears the query when a filter is chosen and the filters when a Name is typed. See ADR 0003.
- **Glossary.** Use **Browse**, **Release stage** and **Streaming service** as defined in the glossary. A Browse filter's UI wording may be plainer ("Streaming service", "Genre", "Era"), but code uses the glossary terms.
- **Kinds.** Browse has a Movies tab and a TV Shows tab, with no "All". Movies is the default. The Movie-only filters are Release stage and runtime. Choosing one on the TV Shows tab switches to Movies. The search-by-Name kind chips remain.
- **Catalog source.** The Catalog source gains a way to read a page of Browse results for a kind and a set of filters, one page per call. It is backed by the source's discover lists for Movies and TV Shows; no text query is sent. It is a new method on the source interface, implemented by the TMDB source, the fake source used in tests and the guarded wrapper. A missing page is a failure, not an empty answer, as with the existing list and search reads.
- **Filters passed to the source.**
    - Streaming services: one or more service ids, matched any-of, subscription and free offers only, in the visitor's country.
    - Genre: one genre id for the kind.
    - Release stage (Movies only): translated to release types and a date window in the visitor's country: In theater is a theatrical release within the last 8 weeks; At home is a digital or physical release within the last 6 months that is not within the theatrical window; Coming soon is a theatrical or digital release dated up to 6 months ahead. The stage is an estimate and the UI does not claim showtimes.
    - Highly rated: the source's score of 7 or more with a vote-count floor, labelled as the TMDB score.
    - Runtime (Movies only): one of three bands, under 90, 90 to 120, over 120 minutes.
    - Era: one decade, applied to release date for Movies and first air date for TV Shows; "Older" means before 1990.
    - Original language: one language from a curated list of about twelve (English, Spanish, French, German, Italian, Japanese, Korean, Hindi, Mandarin, Portuguese, Turkish, Swedish).
    - All filters combine with AND. Adult listings are excluded, as in search.
- **Sort.** Top rated, Popular and Newest. Unfiltered Browse defaults to Popular. Once any filter is chosen the default is Top rated, with a vote-count floor so a few perfect votes don't win; In theater and Coming soon default to Popular. An explicit sort in the URL always wins.
- **Streaming service list.** The offered services come from the source's list of services for the visitor's country, filtered to services that offer Movies or TV Shows for the active tab, with the top twelve by the source's display priority shown and the rest behind "More" with a search box. The list is cached per country.
- **Genre list.** The Movies tab uses the Movie genres and the TV Shows tab uses the TV genres. The Catalog source's genre read, today Movies only, gains a kind. Both lists are cached. Genres match across tabs by name when a tab is switched.
- **Country.** Streaming service, Release stage and the offered service list use the visitor's country as the existing Availability reads it: chosen country, else detected, else the default.
- **Hiding seen.** For a signed-in user, Browse by default excludes Movies with a Watched record, Completed TV Shows, and any Title with a User rating. In-progress and Not started TV Shows and Watchlisted Titles stay. A "Show seen" toggle disables the exclusion; guests get neither. Because the source returns pages of 20, hiding happens on our side; a page can therefore come back with fewer than 20 cards, and the next page is requested until a page is filled or the source runs out, with a cap on how many source pages one request may read. A page that cannot be filled within the cap still reports that more pages exist.
- **Result cards.** Browse cards are the same cards as search results: poster, Name, year, External rating chosen by the same rule, and the Watchlist toggle where one exists. A Movie links through its entry route and a TV Show through its own, with the `search` referrer.
- **Page shape.** Browse results are an infinite-scroll list merged page by page, like the search results, and the "user facts" props (`userMovies`, `userTvShows`) cover the Browse results as they do search results. The page is client-rendered and `noindex`, as now.
- **URL.** Filters, the tab, the sort and "Show seen" are query parameters. Unknown parameters and values outside the allowed sets (an unknown genre id, a Streaming service not offered in the country, a language outside the curated list, a malformed page) are ignored; the number of selected services is capped. No filter value is ever passed to the source without being checked against its allowed set first.
- **Caching.** Each Browse page is cached like a search page, keyed by a hash of the filter set and the country, fresh for a short time and served stale for longer if the source fails; the existing fresh and stale settings for search are reused and extended, not duplicated. Each listed Title's TMDB score is recorded, as search does.
- **Analytics.** A Browse results page records one event on the first page, carrying which filters were used (names only), the tab and the result count; never values tied to a person. A new event is added beside `title_searched`, which is unchanged.
- **Trending.** The Search page's Trending list is replaced by Browse. The stored Discover data and the Discover page are untouched.
- **Out-of-scope data.** No schema change and no new stored user data. Filters are not remembered across visits.

## Testing Decisions

- A good test drives external behaviour only: send a request, assert on the response, the Inertia props or the request sent to the source. No test depends on how a filter is mapped inside a class, on a private method or on cache key internals.
- **Seam 1: HTTP feature tests on the Search page**, as in the existing Search page tests. Each uses the fake Catalog source, extended to answer Browse reads and to record the filters it was asked for. Behaviours to cover:
    - Browse shows when there is no query; a query switches to search by Name and ignores filters; filters and a query are never combined.
    - Each filter reaches the source as asked, singly and combined; several Streaming services are any-of; Movie-only filters on the TV Shows tab switch to Movies.
    - Sort defaults: Popular unfiltered, Top rated with a filter, Popular for In theater and Coming soon; an explicit sort wins.
    - Unknown or malformed values are ignored and do not reach the source; the service count is capped.
    - Hiding seen for a signed-in user (Watched Movie, rated Title, Completed TV Show hidden; In progress, Not started and Watchlisted kept), the "Show seen" toggle, no hiding for guests, and short pages being filled from the next source page up to the cap.
    - Source failure shows the unavailable status, and a stale cached page is served when the source fails.
    - The offered Streaming services and genres per tab and country.
    - The analytics event carries filter names, never values tied to a person.
- **Seam 2: the TMDB source's request mapping**, tested with faked HTTP as in the existing TMDB source tests. Given a kind and a set of filters, it asserts the discover request's parameters (provider ids joined as any-of, watch region and monetization types, genre, release types and dates for each Release stage, vote floor, runtime band, decade, original language, sort), that no text query is sent, and that malformed results are dropped as in search. These are the riskiest lines because they cannot be exercised against the fake source.
- **Prior art:** the Search page tests, the Catalog search tests, the TMDB source tests for Movies and TV Shows, and the Discover builder tests, which already cover the Coming soon window and genre collections.
- No new seam, and no browser test in this spec.

## Out of Scope

- Combining a text search with filters (see ADR 0003).
- Age rating, keywords, cast and crew, and production company filters.
- A TV status filter (finished series only).
- Saving a user's preferred Streaming services as a default; a follow-up.
- A "Pick one for me" button on Browse results, handing the filters to the Movie picker; a follow-up.
- Filters on the Watchlist and Watched pages.
- Rent and purchase Availability in the Streaming service filter.
- Live showtimes for the In theater stage.
- Changes to the Discover page or its stored data.

## Further Notes

- The "Highly rated" filter and the cards can disagree: the filter uses the TMDB score, while a card prefers IMDb when it is known. The filter's label names its source for that reason.
- "Hide seen" with a source page size of 20 can need several source calls per Browse page for a heavy user; the cap on source pages per request exists to bound that, and its value should be chosen alongside the source's rate limits.
- Default numbers (the 8-week and 6-month windows, the 7 score, the vote floor, the top twelve services) are first values and are expected to be tuned.
