# Movie identity, URLs and local storage

Type: grilling
Status: open
Blocked by: 01, 02, 03
Map: [Reelio MVP](../map.md)

## Question

How is a Movie identified, addressed, and stored in Reelio?

- Public URL shape for `/movies/{movie}`: Catalog source id, local id, or slug, given that most visited Movies have never been saved by anyone, and given the PRD's "provider independence" principle.
- When a local Movie record is created (on first view, on first save or watch, or never) and what it holds vs what lives only in cache.
- Where External ratings (TMDB's own, and IMDb's from the daily non-commercial dataset import) and Availability are kept (cache vs table), within the limits the research tickets found. Note that the IMDb dataset covers every title, not just Movies Reelio has seen.
- Fields the picker and watchlist sorting will need locally (genres, runtime, release date).
