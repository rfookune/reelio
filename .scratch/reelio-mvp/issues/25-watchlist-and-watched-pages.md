# Watchlist and Watched pages

Type: grilling
Status: open
Map: [Reelio MVP](../map.md)

## Question

How do the Watchlist and Watched pages list, sort and page through Movies?

- Whether FR-008's list layout earns its place next to the decided grid card ([Visual language and app shell](./06-visual-language-and-shell.md)).
- Sort options and defaults (date added, Watched date, title, release year, rating) and any filters. The watched-status filter is dropped ([The user–movie lifecycle](./04-user-movie-lifecycle.md)); the picker's genre filter is separate ([Movie picker](./12-movie-picker.md)).
- Pagination or infinite scroll, and the empty states.
- How shells (Movies TMDB has dropped) appear.

## Comments

- From [Movie detail page](./24-movie-detail-page.md) (resolved): a dropped Movie's page is a 410 with no title (every TMDB field is cleared), so a shell card here has no title or poster to show either; decide what identifies it. The detail page shows a Movie that is both Watched and on the Watchlist (a rewatch queued) as "On your Watchlist" plus a quiet "Last watched {date}" line; the Watched card should agree.
