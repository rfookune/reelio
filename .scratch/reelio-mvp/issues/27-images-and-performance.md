# Images and performance budget

Type: grilling
Status: open
Blocked by: 22, 24
Map: [Reelio MVP](../map.md)

## Question

What image sizes, loading strategy and performance budget does the app use?

- Poster, backdrop and logo size variants per breakpoint from TMDB's image sizes ([TMDB API coverage](./02-tmdb-api-coverage.md)), with `srcset`, lazy loading and the hero's priority loading. Images are hotlinked only ([TMDB terms](./01-tmdb-terms.md)).
- The LCP, CLS and INP targets and how they're checked (warm requests only; cold-start allowance from [Deployment and operations](./19-deployment-and-operations.md)).
- The likely LCP elements are the Discover hero ([Homepage curation](./22-homepage-curation.md)) and the Movie page backdrop ([Movie detail page](./24-movie-detail-page.md)).

## Comments

- From [Movie detail page](./24-movie-detail-page.md) (resolved): the Movie page's LCP element is the hero backdrop at `w1280` with `fetchpriority="high"`; a blurred glow reuses the same URL, so it costs no second download. Without a backdrop, a blurred poster fills the hero. The poster sits beside the title from `sm`. The trailer's cover image is that same backdrop, so nothing loads from YouTube until play. Cast photos are `w185` (12, lazy) and service logos `w92`. Set the `srcset` sizes here.
