# Movie detail page

Type: prototype
Status: open
Map: [Reelio MVP](../map.md)

## Question

What does the Movie page look like and contain?

- Layout of the backdrop, poster, title block, overview, director and cast, genres and runtime, both External ratings with credits, the trailer, and the Availability block ([Country and Availability presentation](./11-country-and-availability.md)).
- Where the Watchlist and Watched actions sit ([Watchlist interactions](./13-watchlist-interactions.md)).
- Trailer behaviour: inline `youtube-nocookie` embed vs click-to-load facade, and the fallback link when embedding is disabled.
- States: loading skeletons, `stale` data, a dropped Movie (shell, 410), and no trailer, ratings or Availability.
- Builds on [Visual language and app shell](./06-visual-language-and-shell.md).
